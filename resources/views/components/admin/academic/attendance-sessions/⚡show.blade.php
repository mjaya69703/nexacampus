<?php

use App\Models\Academic\AttendanceRecord;
use App\Models\Academic\AttendanceSession;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudyPlanDetail;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public AttendanceSession $session;
    public int $offeringId;
    public array $sessionForm = [];
    public array $lecturerOptions = [];
    public array $students = [];
    public array $recordForms = [];

    public function mount($offeringId, $id): void
    {
        $this->offeringId = (int) $offeringId;
        $this->session = AttendanceSession::query()
            ->with([
                'courseOffering.course',
                'courseOffering.studyProgram',
                'lecturerProfile.user',
                'records.studentProfile.user',
            ])
            ->where('course_offering_id', $this->offeringId)
            ->findOrFail($id);

        $this->sessionForm = [
            'meeting_no' => $this->session->meeting_no,
            'meeting_date' => $this->session->meeting_date?->format('Y-m-d'),
            'start_time' => $this->session->start_time?->format('H:i'),
            'end_time' => $this->session->end_time?->format('H:i'),
            'lecturer_profile_id' => $this->session->lecturer_profile_id,
            'status' => $this->session->status,
            'topic' => $this->session->topic,
            'notes' => $this->session->notes,
        ];

        $this->lecturerOptions = CourseOfferingLecturer::query()
            ->where('course_offering_id', $this->offeringId)
            ->with('lecturerProfile.user')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->lecturer_profile_id,
                'name' => $row->lecturerProfile?->user?->name ?? '-',
            ])
            ->unique('id')
            ->values()
            ->toArray();

        $this->loadStudentsAndRecords();
    }

    public function loadStudentsAndRecords(): void
    {
        $details = StudyPlanDetail::query()
            ->where('course_offering_id', $this->offeringId)
            ->where('status', 'Taken')
            ->whereHas('studyPlan', fn ($query) => $query->where('status', 'Approved'))
            ->with('studyPlan.studentProfile.user')
            ->get();

        $students = $details
            ->map(fn ($detail) => $detail->studyPlan?->studentProfile)
            ->filter()
            ->unique('id')
            ->values();

        $existingRecords = AttendanceRecord::query()
            ->where('attendance_session_id', $this->session->id)
            ->get()
            ->keyBy('student_profile_id');

        $this->students = $students
            ->map(fn ($studentProfile) => [
                'id' => $studentProfile->id,
                'name' => $studentProfile->user?->name ?? '-',
                'nim' => $studentProfile->nim,
            ])
            ->toArray();

        foreach ($this->students as $student) {
            $record = $existingRecords->get($student['id']);
            $this->recordForms[$student['id']] = [
                'status' => $record?->status ?? 'Absent',
                'notes' => $record?->notes,
            ];
        }
    }

    public function saveSession(): void
    {
        $validated = $this->validate([
            'sessionForm.meeting_no' => 'nullable|integer|min:1|max:64',
            'sessionForm.meeting_date' => 'required|date',
            'sessionForm.start_time' => 'nullable|date_format:H:i',
            'sessionForm.end_time' => 'nullable|date_format:H:i|after:sessionForm.start_time',
            'sessionForm.lecturer_profile_id' => 'nullable|integer|exists:lecturer_profiles,id',
            'sessionForm.status' => 'required|in:Draft,Opened,Closed,Cancelled',
            'sessionForm.topic' => 'nullable|string|max:255',
            'sessionForm.notes' => 'nullable|string',
        ]);

        $this->session->update([
            'meeting_no' => $validated['sessionForm']['meeting_no'] ?: null,
            'meeting_date' => $validated['sessionForm']['meeting_date'],
            'start_time' => $validated['sessionForm']['start_time'] ?: null,
            'end_time' => $validated['sessionForm']['end_time'] ?: null,
            'lecturer_profile_id' => $validated['sessionForm']['lecturer_profile_id'] ?: null,
            'status' => $validated['sessionForm']['status'],
            'topic' => $validated['sessionForm']['topic'] ?: null,
            'notes' => $validated['sessionForm']['notes'] ?: null,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Session berhasil diperbarui.');
    }

    public function saveRecord(int $studentProfileId): void
    {
        if (! isset($this->recordForms[$studentProfileId])) {
            return;
        }

        $status = $this->recordForms[$studentProfileId]['status'] ?? 'Absent';
        $notes = $this->recordForms[$studentProfileId]['notes'] ?? null;

        if (! in_array($status, ['Present', 'Absent', 'Excused', 'Sick', 'Late'], true)) {
            session()->flash('error', 'Status kehadiran tidak valid.');

            return;
        }

        $record = AttendanceRecord::query()->firstOrNew([
            'attendance_session_id' => $this->session->id,
            'student_profile_id' => $studentProfileId,
        ]);

        if (! $record->exists) {
            $record->created_by = auth()->id();
        }

        $record->status = $status;
        $record->notes = $notes ?: null;
        $record->recorded_at = now();
        $record->recorded_by = auth()->id();
        $record->updated_by = auth()->id();
        $record->save();

        session()->flash('success', 'Absensi mahasiswa berhasil disimpan.');
    }

    public function saveAllRecords(): void
    {
        DB::transaction(function (): void {
            foreach ($this->students as $student) {
                $studentProfileId = (int) $student['id'];
                $status = $this->recordForms[$studentProfileId]['status'] ?? 'Absent';
                $notes = $this->recordForms[$studentProfileId]['notes'] ?? null;

                $record = AttendanceRecord::query()->firstOrNew([
                    'attendance_session_id' => $this->session->id,
                    'student_profile_id' => $studentProfileId,
                ]);

                if (! $record->exists) {
                    $record->created_by = auth()->id();
                }

                $record->status = in_array($status, ['Present', 'Absent', 'Excused', 'Sick', 'Late'], true) ? $status : 'Absent';
                $record->notes = $notes ?: null;
                $record->recorded_at = now();
                $record->recorded_by = auth()->id();
                $record->updated_by = auth()->id();
                $record->save();
            }
        });

        session()->flash('success', 'Semua attendance record berhasil disimpan.');
    }

    public function goBack(): void
    {
        $this->redirectRoute('admin.academic.course-offerings.show', ['id' => $this->offeringId]);
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Attendance Session Detail',
            ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Detail & Absensi Pertemuan Ke-{{ $session->meeting_no ?? '-' }}"
        description="Perbarui informasi sesi pertemuan perkuliahan serta catat status kehadiran mahasiswa secara individu maupun massal."
        icon="calendar-check"
    >
        <button type="button" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2" wire:click="goBack">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Course Offering</span>
        </button>
    </x-admin.academic.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-clock fs-5"></i>
                            </div>
                            <div>
                                <h4 class="card-title fw-bold mb-1 text-dark">Informasi Sesi Pertemuan</h4>
                                <div class="text-muted small">
                                    <strong>{{ $session->courseOffering?->course?->code }} - {{ $session->courseOffering?->course?->name }}</strong>
                                    | {{ $session->courseOffering?->studyProgram?->name ?? '-' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form wire:submit.prevent="saveSession">
                        <div class="row g-3">
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <label class="form-label fw-semibold">Pertemuan Ke <span class="text-danger">*</span></label>
                                <input type="number" min="1" max="64" class="form-control" wire:model.defer="sessionForm.meeting_no">
                                @error('sessionForm.meeting_no') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <label class="form-label fw-semibold">Tanggal <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" wire:model.defer="sessionForm.meeting_date">
                                @error('sessionForm.meeting_date') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <label class="form-label fw-semibold">Jam Mulai</label>
                                <input type="time" class="form-control" wire:model.defer="sessionForm.start_time">
                                @error('sessionForm.start_time') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <label class="form-label fw-semibold">Jam Selesai</label>
                                <input type="time" class="form-control" wire:model.defer="sessionForm.end_time">
                                @error('sessionForm.end_time') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-lg-3 col-md-8 col-sm-12">
                                <label class="form-label fw-semibold">Status Session <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model.defer="sessionForm.status">
                                    <option value="Draft">Draft</option>
                                    <option value="Opened">Opened</option>
                                    <option value="Closed">Closed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                                @error('sessionForm.status') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-lg-4 col-md-6 col-sm-12">
                                <label class="form-label fw-semibold">Dosen Pengajar</label>
                                <select class="form-select" wire:model.defer="sessionForm.lecturer_profile_id">
                                    <option value="">- Pilih Dosen -</option>
                                    @foreach ($lecturerOptions as $lecturer)
                                        <option value="{{ $lecturer['id'] }}">{{ $lecturer['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('sessionForm.lecturer_profile_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-lg-8 col-md-6 col-sm-12">
                                <label class="form-label fw-semibold">Topik Perkuliahan</label>
                                <input type="text" class="form-control" wire:model.defer="sessionForm.topic" placeholder="Contoh: Pengantar Konsep Basis Data">
                                @error('sessionForm.topic') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Catatan Sesi</label>
                                <textarea rows="2" class="form-control" wire:model.defer="sessionForm.notes" placeholder="Catatan tambahan mengenai jalannya sesi..."></textarea>
                                @error('sessionForm.notes') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-12 border-top pt-3 mt-4 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm">
                                    <i class="fa fa-save me-1"></i> Simpan Perubahan Sesi
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-users fs-5"></i>
                            </div>
                            <div>
                                <h4 class="card-title fw-bold mb-1 text-dark">Daftar Kehadiran Mahasiswa</h4>
                                <div class="text-muted small">Catat kehadiran mahasiswa secara individu atau klik Simpan Semua Kehadiran sekaligus.</div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-success rounded-pill px-4 py-2 shadow-sm fw-semibold d-inline-flex align-items-center gap-2" wire:click="saveAllRecords">
                            <i class="fa fa-save"></i> <span>Simpan Semua Kehadiran</span>
                        </button>
                    </div>
                </div>
                <div class="card-body p-4">
                    @if (count($students) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;">No</th>
                                        <th>Mahasiswa</th>
                                        <th style="width: 200px;">Status Kehadiran</th>
                                        <th>Catatan / Keterangan</th>
                                        <th style="width: 140px; text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        <tr>
                                            <td class="text-muted">{{ $index + 1 }}</td>
                                            <td>
                                                <div class="fw-bold text-dark">{{ $student['name'] }}</div>
                                                <div class="text-muted small">NIM: {{ $student['nim'] ?? '-' }}</div>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm shadow-sm" wire:model.defer="recordForms.{{ $student['id'] }}.status">
                                                    <option value="Present">Present (Hadir)</option>
                                                    <option value="Absent">Absent (Alpa)</option>
                                                    <option value="Excused">Excused (Izin)</option>
                                                    <option value="Sick">Sick (Sakit)</option>
                                                    <option value="Late">Late (Terlambat)</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm shadow-sm" wire:model.defer="recordForms.{{ $student['id'] }}.notes" placeholder="Catatan absensi...">
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm" wire:click="saveRecord({{ $student['id'] }})">
                                                    <i class="fa fa-check me-1"></i> Simpan
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-warning border-0 rounded-3 mb-0 d-flex align-items-center gap-3">
                            <i class="fa fa-exclamation-triangle fs-4 text-warning"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Belum Ada Mahasiswa Terdaftar</h6>
                                <div class="small">Belum ada mahasiswa yang mengambil mata kuliah pada penawaran kelas ini (atau KRS belum disetujui).</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
