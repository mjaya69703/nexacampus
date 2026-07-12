<?php

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionExamParticipant;
use App\Models\Admission\AdmissionExamSchedule;
use App\Support\ActivePermission;
use App\Support\Admission\AdmissionSelectionService;
use Livewire\Component;

new class extends Component
{
    public AdmissionExamSchedule $schedule;

    public string $applicationId = '';

    public array $attendanceForms = [];

    public array $scoreForms = [];

    public function mount($id): void
    {
        $this->loadSchedule($id);
    }

    public function assignParticipant(): void
    {
        if (! ActivePermission::check('admission-exam-schedule.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengelola peserta ujian.');
            return;
        }

        $validated = $this->validate([
            'applicationId' => 'required|exists:admission_applications,id',
        ]);

        $exists = $this->schedule->participants()
            ->where('admission_application_id', $validated['applicationId'])
            ->exists();

        if ($exists) {
            session()->flash('error', 'Pendaftar ini sudah terdaftar pada jadwal ujian yang dipilih.');
            return;
        }

        if ($this->schedule->quota > 0 && $this->schedule->participants()->count() >= $this->schedule->quota) {
            session()->flash('error', 'Kuota maksimal sesi ujian ini sudah penuh.');
            return;
        }

        $this->schedule->participants()->create([
            'admission_application_id' => $validated['applicationId'],
            'created_by' => auth()->id(),
        ]);

        $this->syncRegisteredCount();
        $this->applicationId = '';
        session()->flash('success', 'Peserta baru berhasil ditambahkan ke jadwal ujian.');
        $this->loadSchedule($this->schedule->id);
    }

    public function assignAllFromPeriod(): void
    {
        if (! ActivePermission::check('admission-exam-schedule.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengelola peserta ujian massal.');
            return;
        }

        $assignedIds = $this->schedule->participants()
            ->pluck('admission_application_id');

        $remainingSlots = $this->schedule->quota > 0
            ? max(0, $this->schedule->quota - $this->schedule->participants()->count())
            : null;

        if ($remainingSlots === 0) {
            session()->flash('error', 'Kuota maksimal sesi ujian ini sudah penuh.');
            return;
        }

        $applications = AdmissionApplication::query()
            ->where('admission_period_id', $this->schedule->admission_period_id)
            ->whereIn('status', ['submitted', 'under_review', 'waitlisted'])
            ->whereNotIn('id', $assignedIds)
            ->orderBy('submitted_at')
            ->orderBy('full_name')
            ->when($remainingSlots !== null, fn ($query) => $query->limit($remainingSlots))
            ->get(['id']);

        if ($applications->isEmpty()) {
            session()->flash('error', 'Tidak ada pendaftar baru dari gelombang ini yang siap ditambahkan.');
            return;
        }

        $now = now();
        $rows = $applications->map(fn (AdmissionApplication $application) => [
            'admission_exam_schedule_id' => $this->schedule->id,
            'admission_application_id' => $application->id,
            'attendance_status' => 'registered',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        AdmissionExamParticipant::insert($rows);
        $this->syncRegisteredCount();

        session()->flash('success', $applications->count().' peserta berhasil didaftarkan secara massal dari gelombang yang sama.');
        $this->loadSchedule($this->schedule->id);
    }

    public function updateAttendance(int $participantId): void
    {
        if (! ActivePermission::check('admission-exam-schedule.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk memperbarui kehadiran.');
            return;
        }

        $participant = $this->schedule->participants()->whereKey($participantId)->firstOrFail();
        $form = $this->attendanceForms[$participantId] ?? [];

        validator($form, [
            'attendance_status' => 'required|in:registered,present,absent',
            'notes' => 'nullable|string',
        ])->validate();

        $participant->update([
            'attendance_status' => $form['attendance_status'],
            'notes' => $form['notes'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Rekap kehadiran peserta berhasil diperbarui.');
        $this->loadSchedule($this->schedule->id);
    }

    public function saveScore(int $participantId, AdmissionSelectionService $selectionService): void
    {
        if (! ActivePermission::check('admission-exam-schedule.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menyimpan penilaian.');
            return;
        }

        $participant = $this->schedule->participants()->with('application')->whereKey($participantId)->firstOrFail();
        $form = $this->scoreForms[$participantId] ?? [];

        validator($form, [
            'score_type' => 'required|string|max:100',
            'score' => 'required|numeric|min:0|max:100',
            'weight' => 'required|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ])->validate();

        $participant->application->scores()->updateOrCreate(
            [
                'admission_exam_schedule_id' => $this->schedule->id,
                'score_type' => $form['score_type'],
            ],
            [
                'score' => $form['score'],
                'weight' => $form['weight'],
                'notes' => $form['notes'] ?? null,
                'scored_by' => auth()->id(),
            ],
        );

        $selectionService->recalculateFinalScore($participant->application);

        session()->flash('success', 'Skor peserta berhasil disimpan dan nilai akhir seleksi diperbarui.');
        $this->loadSchedule($this->schedule->id);
    }

    public function removeParticipant(int $participantId): void
    {
        if (! ActivePermission::check('admission-exam-schedule.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus peserta.');
            return;
        }

        $this->schedule->participants()->whereKey($participantId)->delete();
        $this->syncRegisteredCount();
        session()->flash('success', 'Peserta berhasil dihapus dari jadwal ujian ini.');
        $this->loadSchedule($this->schedule->id);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Detail Jadwal Ujian',
        ]);
    }

    public function eligibleApplications()
    {
        $assignedIds = $this->schedule->participants->pluck('admission_application_id');

        return AdmissionApplication::query()
            ->where('admission_period_id', $this->schedule->admission_period_id)
            ->whereIn('status', ['submitted', 'under_review', 'waitlisted'])
            ->whereNotIn('id', $assignedIds)
            ->orderBy('full_name')
            ->get(['id', 'application_number', 'full_name']);
    }

    private function loadSchedule($id): void
    {
        $this->schedule = AdmissionExamSchedule::with([
            'period',
            'participants.application.studyProgram',
            'participants.application.scores',
        ])->findOrFail($id);

        $this->attendanceForms = $this->schedule->participants
            ->mapWithKeys(fn (AdmissionExamParticipant $participant) => [
                $participant->id => [
                    'attendance_status' => $participant->attendance_status,
                    'notes' => $participant->notes,
                ],
            ])
            ->toArray();

        $this->scoreForms = $this->schedule->participants
            ->mapWithKeys(function (AdmissionExamParticipant $participant) {
                $score = $participant->application->scores
                    ->where('admission_exam_schedule_id', $this->schedule->id)
                    ->first();

                return [
                    $participant->id => [
                        'score_type' => $score?->score_type ?? $this->schedule->exam_type,
                        'score' => $score?->score,
                        'weight' => $score?->weight ?? 100,
                        'notes' => $score?->notes,
                    ],
                ];
            })
            ->toArray();
    }

    private function syncRegisteredCount(): void
    {
        $this->schedule->update([
            'registered_count' => $this->schedule->participants()->count(),
            'updated_by' => auth()->id(),
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.admission.header
        title="{{ $schedule->title }}"
        description="Gelombang: {{ $schedule->period?->name ?? '-' }} &bull; Jenis Seleksi: {{ str($schedule->exam_type)->replace('_', ' ')->title() }} &bull; Lokasi: {{ $schedule->venue ?? 'Daring / Online' }}"
        icon="calendar-check"
    >
        <div class="d-flex gap-2 flex-wrap">
            @activecan('admission-exam-schedule.update')
                <a href="{{ route('admin.admission.admission-exam-schedules.edit', ['id' => $schedule->id]) }}" class="btn btn-warning rounded-pill px-4 py-2 fw-bold shadow-sm d-inline-flex align-items-center gap-2 border-0">
                    <i class="fas fa-edit"></i> Edit Jadwal
                </a>
            @endactivecan
            <a href="{{ route('admin.admission.admission-exam-schedules.index') }}" class="btn btn-outline-light rounded-pill px-4 py-2 fw-bold d-inline-flex align-items-center gap-2">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar
            </a>
        </div>

        <x-slot:stats>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-calendar-day text-warning fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Tanggal Ujian</div>
                    <div class="fw-bold fs-6 mb-0">{{ $schedule->exam_date?->format('d M Y') }}</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-clock text-info fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Waktu Mulai</div>
                    <div class="fw-bold fs-6 mb-0">{{ $schedule->exam_time?->format('H:i') }} WIB</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-users text-success fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Kapasitas Peserta</div>
                    <div class="fw-bold fs-6 mb-0">{{ $schedule->participants->count() }} / {{ $schedule->quota ?: '∞' }} <small class="fs-8 fw-normal">Kursi</small></div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.admission.header>

    @if($schedule->meeting_link || $schedule->notes)
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h6 class="fw-bold mb-1 text-dark"><i class="fas fa-info-circle text-primary me-2"></i> Keterangan Pelaksanaan Ujian</h6>
                    <p class="text-muted fs-7 mb-0">{{ $schedule->notes ?? 'Sesi ujian berlangsung tepat waktu sesuai jadwal tertera.' }}</p>
                </div>
                @if($schedule->meeting_link)
                    <a href="{{ $schedule->meeting_link }}" target="_blank" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-bold shadow-sm d-flex align-items-center gap-2">
                        <i class="fas fa-video"></i> Buka Tautan Daring (Zoom / GMeet)
                    </a>
                @endif
            </div>
        </div>
    @endif

    @activecan('admission-exam-schedule.update')
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-primary bg-opacity-10 border-start border-primary border-4">
            <div class="card-body p-4">
                <h5 class="fw-bold text-primary mb-3 d-flex align-items-center gap-2">
                    <i class="fas fa-user-plus"></i> Daftarkan Peserta ke Sesi Ujian Ini
                </h5>
                <div class="row g-2 align-items-center">
                    <div class="col-md-7">
                        <select class="form-select rounded-3 border-0 shadow-sm" wire:model="applicationId">
                            <option value="">-- Pilih Pendaftar dari Gelombang yang Sama --</option>
                            @foreach($this->eligibleApplications() as $application)
                                <option value="{{ $application->id }}">{{ $application->application_number }} &bull; {{ $application->full_name }}</option>
                            @endforeach
                        </select>
                        @error('applicationId') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-5 d-flex gap-2">
                        <button class="btn btn-primary rounded-pill fw-bold px-4 py-2 shadow-sm d-flex align-items-center gap-1" wire:click="assignParticipant">
                            <i class="fas fa-plus"></i> Tambahkan
                        </button>
                        <button class="btn btn-outline-primary rounded-pill fw-bold px-4 py-2 shadow-sm d-flex align-items-center gap-2 flex-grow-1" wire:click="assignAllFromPeriod" wire:loading.attr="disabled" wire:target="assignAllFromPeriod">
                            <i class="fas fa-users" wire:loading.remove wire:target="assignAllFromPeriod"></i>
                            <i class="fas fa-spinner fa-spin" wire:loading wire:target="assignAllFromPeriod"></i>
                            <span wire:loading.remove wire:target="assignAllFromPeriod">Daftarkan Semua Pendaftar Gelombang Ini</span>
                            <span wire:loading wire:target="assignAllFromPeriod">Mendaftarkan Massal...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endactivecan

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-4">
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="fas fa-list-ol text-primary"></i> Daftar Hadir & Penilaian Peserta Ujian
            </h4>
            <p class="text-muted fs-7 mb-0">Catat kehadiran dan masukkan nilai skor komponen seleksi untuk tiap peserta.</p>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 fw-bold text-muted fs-7">PENDAFTAR</th>
                        <th class="fw-bold text-muted fs-7">PROGRAM STUDI</th>
                        <th class="fw-bold text-muted fs-7" style="min-width: 220px;">KEHADIRAN & CATATAN</th>
                        <th class="fw-bold text-muted fs-7" style="min-width: 320px;">KOMPONEN PENILAIAN (SKOR / BOBOT)</th>
                        <th class="fw-bold text-muted fs-7 text-center">SKOR AKHIR</th>
                        <th class="pe-4 text-end fw-bold text-muted fs-7">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedule->participants as $participant)
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark fs-6">{{ $participant->application?->full_name }}</div>
                                <span class="text-muted fs-7">{{ $participant->application?->application_number }}</span>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $participant->application?->studyProgram?->name ?? '-' }}</span>
                            </td>
                            <td>
                                <select class="form-select form-select-sm rounded-3 mb-1 border-secondary border-opacity-25" wire:model.defer="attendanceForms.{{ $participant->id }}.attendance_status">
                                    <option value="registered">Terdaftar (Registered)</option>
                                    <option value="present">Hadir (Present)</option>
                                    <option value="absent">Tidak Hadir (Absent)</option>
                                </select>
                                <input type="text" class="form-control form-control-sm rounded-3 border-secondary border-opacity-25" placeholder="Catatan kehadiran..." wire:model.defer="attendanceForms.{{ $participant->id }}.notes">
                            </td>
                            <td>
                                <div class="row g-1">
                                    <div class="col-5">
                                        <input class="form-control form-control-sm rounded-3 border-secondary border-opacity-25" wire:model.defer="scoreForms.{{ $participant->id }}.score_type" placeholder="Komponen (Contoh: Wawancara)">
                                    </div>
                                    <div class="col-3">
                                        <input type="number" step="0.01" class="form-control form-control-sm rounded-3 border-secondary border-opacity-25" wire:model.defer="scoreForms.{{ $participant->id }}.score" placeholder="Nilai (0-100)">
                                    </div>
                                    <div class="col-4">
                                        <input type="number" step="0.01" class="form-control form-control-sm rounded-3 border-secondary border-opacity-25" wire:model.defer="scoreForms.{{ $participant->id }}.weight" placeholder="Bobot (Contoh: 30%)">
                                    </div>
                                    <div class="col-12 mt-1">
                                        <input class="form-control form-control-sm rounded-3 border-secondary border-opacity-25" wire:model.defer="scoreForms.{{ $participant->id }}.notes" placeholder="Catatan penilaian penguji...">
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary rounded-pill px-3 py-1 fs-6">{{ $participant->application?->final_score ?? '-' }}</span>
                            </td>
                            <td class="pe-4 text-end">
                                @activecan('admission-exam-schedule.update')
                                    <div class="d-inline-flex flex-column gap-1">
                                        <div class="d-inline-flex gap-1 justify-content-end">
                                            <button class="btn btn-outline-info rounded-pill px-2.5 py-1 text-info fw-medium shadow-sm d-inline-flex align-items-center gap-1 fs-8" wire:click="updateAttendance({{ $participant->id }})" title="Simpan Kehadiran">
                                                <i class="fas fa-clipboard-check"></i> Hadir
                                            </button>
                                            <button class="btn btn-outline-success rounded-pill px-2.5 py-1 text-success fw-medium shadow-sm d-inline-flex align-items-center gap-1 fs-8" wire:click="saveScore({{ $participant->id }})" title="Simpan Nilai">
                                                <i class="fas fa-save"></i> Nilai
                                            </button>
                                        </div>
                                        <div class="d-inline-flex justify-content-end">
                                            <button class="btn btn-outline-danger rounded-pill px-2.5 py-1 text-danger fw-medium shadow-sm d-inline-flex align-items-center gap-1 fs-8" wire:click="removeParticipant({{ $participant->id }})" title="Hapus dari Sesi">
                                                <i class="fas fa-trash"></i> Hapus Peserta
                                            </button>
                                        </div>
                                    </div>
                                @endactivecan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <div class="py-3">
                                    <i class="fas fa-user-clock fs-1 text-secondary opacity-50 mb-3"></i>
                                    <p class="fs-6 fw-medium mb-1">Belum ada peserta yang didaftarkan pada jadwal ujian ini.</p>
                                    <small class="text-muted">Gunakan form di atas untuk mendaftarkan pendaftar ke sesi ujian ini.</small>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
