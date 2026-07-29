<?php

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionDocument;
use App\Support\ActivePermission;
use App\Support\Admission\AdmissionConversionService;
use App\Support\Admission\NimGenerationService;
use App\Support\Admission\AdmissionStatusService;
use Livewire\Component;

new class extends Component
{
    public AdmissionApplication $application;

    public array $reviewForm = [
        'status' => 'submitted',
        'review_notes' => '',
        'final_score' => null,
    ];

    public array $documentNotes = [];

    public ?string $nimPreview = null;

    public function mount($id): void
    {
        $this->loadApplication($id);

        $this->reviewForm = [
            'status' => $this->application->status,
            'review_notes' => $this->application->review_notes ?? '',
            'final_score' => $this->application->final_score,
        ];

        $this->refreshNimPreview();
    }

    public function updateReview(AdmissionStatusService $statusService): void
    {
        if (! ActivePermission::check('admission-application.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status seleksi.');
            return;
        }

        $validated = $this->validate([
            'reviewForm.status' => 'required|in:submitted,under_review,accepted,rejected,waitlisted',
            'reviewForm.review_notes' => 'nullable|string',
            'reviewForm.final_score' => 'nullable|numeric|min:0|max:100',
        ]);

        $this->application->update([
            'final_score' => $validated['reviewForm']['final_score'],
            'review_notes' => $validated['reviewForm']['review_notes'] ?: null,
            'updated_by' => auth()->id(),
        ]);

        if ($validated['reviewForm']['status'] !== $this->application->status) {
            $statusService->change(
                $this->application,
                $validated['reviewForm']['status'],
                $validated['reviewForm']['review_notes'] ?: null,
                auth()->id(),
            );
        }

        session()->flash('success', 'Review aplikasi pendaftaran berhasil diperbarui.');
        $this->loadApplication($this->application->id);
        $this->refreshNimPreview();
    }

    public function convertToStudent(AdmissionConversionService $conversionService): void
    {
        if (! ActivePermission::check('admission-application.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk melakukan konversi mahasiswa.');
            return;
        }

        try {
            $studentProfile = $conversionService->convert($this->application, auth()->id());
            session()->flash('success', 'Calon mahasiswa berhasil dikonversi menjadi mahasiswa aktif dengan NIM '.$studentProfile->nim.'.');
            $this->loadApplication($this->application->id);
            $this->refreshNimPreview();
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function verifyDocument(int $documentId, string $status): void
    {
        if (! ActivePermission::check('admission-application.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk memverifikasi dokumen.');
            return;
        }
        if (! in_array($status, ['verified', 'rejected', 'pending'], true)) {
            return;
        }

        $document = $this->application->documents()->whereKey($documentId)->firstOrFail();

        $document->update([
            'verification_status' => $status,
            'verification_notes' => $this->documentNotes[$documentId] ?? null,
            'verified_by' => auth()->id(),
            'verified_at' => $status === 'pending' ? null : now(),
        ]);

        session()->flash('success', 'Status verifikasi dokumen berhasil diperbarui.');
        $this->loadApplication($this->application->id);
    }

    public function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'accepted', 'verified', 'present' => 'bg-success bg-opacity-10 text-success border border-success border-opacity-25',
            'under_review', 'pending', 'waitlisted' => 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25',
            'rejected', 'absent' => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',
            default => 'bg-info bg-opacity-10 text-info border border-info border-opacity-25',
        };
    }

    public function documentPreviewUrl($document): string
    {
        return route('admin.admission.documents.preview', ['document' => $document->id]);
    }

    public function isImageDocument($document): bool
    {
        return in_array(strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    public function canPreviewDocument($document): bool
    {
        return $this->isImageDocument($document)
            || strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION)) === 'pdf';
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Detail Aplikasi Pendaftar',
        ]);
    }

    private function loadApplication($id): void
    {
        $this->application = AdmissionApplication::with([
            'period.academicYear',
            'period.documentRequirements',
            'faculty',
            'studyProgram',
            'documents.requirement',
            'documents.verifiedBy',
            'examParticipants.schedule',
            'scores.schedule',
            'scores.scorer',
            'statusHistories.changedBy',
            'reviewedBy',
            'user.studentProfile',
        ])->findOrFail($id);

        $this->documentNotes = $this->application->documents
            ->mapWithKeys(fn (AdmissionDocument $document) => [$document->id => $document->verification_notes])
            ->toArray();
    }

    private function refreshNimPreview(): void
    {
        if ($this->application->status !== 'accepted' || $this->application->converted_at) {
            $this->nimPreview = null;
            return;
        }

        try {
            $this->nimPreview = app(NimGenerationService::class)->preview($this->application);
        } catch (\Throwable) {
            $this->nimPreview = null;
        }
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.admission.header
        title="{{ $application->full_name }}"
        description="No. Pendaftaran: {{ $application->application_number }} &bull; Gelombang: {{ $application->period?->name ?? '-' }} &bull; Program Studi: {{ $application->studyProgram?->name ?? '-' }}"
        icon="user-check"
    >
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('root.admission.portal', ['applicationNumber' => $application->application_number, 'token' => $application->access_token]) }}" target="_blank" class="btn btn-light rounded-pill px-4 py-2 text-primary fw-bold shadow-sm d-inline-flex align-items-center gap-2 border-0">
                <i class="fas fa-external-link-alt"></i> Portal Pendaftar
            </a>
            <a href="{{ route('admin.admission.admission-applications.index') }}" class="btn btn-outline-light rounded-pill px-4 py-2 fw-bold d-inline-flex align-items-center gap-2">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar
            </a>
        </div>

        <x-slot:stats>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-clipboard-check text-warning fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Status Seleksi</div>
                    <div class="fw-bold fs-6 mb-0">{{ str($application->status)->replace('_', ' ')->title() }}</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-file-circle-check text-success fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Dokumen Terverifikasi</div>
                    <div class="fw-bold fs-6 mb-0">{{ $application->documents->where('verification_status', 'verified')->count() }}/{{ $application->documents->count() }} <small class="fs-8 fw-normal">Berkas</small></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-star text-info fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Skor Akhir</div>
                    <div class="fw-bold fs-6 mb-0">{{ $application->final_score ?? '-' }} <small class="fs-8 fw-normal">Poin</small></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-calendar-alt text-white fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Tanggal Submit</div>
                    <div class="fw-bold fs-6 mb-0">{{ $application->submitted_at?->format('d M Y') ?? '-' }}</div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.admission.header>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <h4 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="fas fa-user-circle text-primary"></i> Profil Calon Mahasiswa
                    </h4>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <small class="text-muted fw-bold d-block mb-1">Email Pendaftar</small>
                                <span class="fw-bold text-dark fs-6">{{ $application->email }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <small class="text-muted fw-bold d-block mb-1">Nomor Telepon / WhatsApp</small>
                                <span class="fw-bold text-dark fs-6">{{ $application->phone }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <small class="text-muted fw-bold d-block mb-1">Tanggal Lahir & Jenis Kelamin</small>
                                <span class="fw-bold text-dark fs-6">{{ $application->birth_date?->format('d F Y') }} / {{ ucfirst($application->gender) }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <small class="text-muted fw-bold d-block mb-1">Kontak Darurat</small>
                                <span class="fw-bold text-dark fs-6">{{ $application->emergency_contact_name }} - {{ $application->emergency_contact_phone }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <small class="text-muted fw-bold d-block mb-1">Tahun Akademik</small>
                                <span class="fw-bold text-dark fs-6">{{ $application->period?->academicYear?->name ?? $application->period?->academic_year ?? '-' }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <small class="text-muted fw-bold d-block mb-1">Program Studi & Kelas Pilihan</small>
                                <span class="fw-bold text-dark fs-6">{{ $application->studyProgram?->name ?? '-' }} ({{ ucfirst($application->class_type ?? 'Regular') }})</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3 border">
                                <small class="text-muted fw-bold d-block mb-1">Alamat Domisili</small>
                                <span class="text-dark">{{ $application->address }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <h4 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="fas fa-graduation-cap text-primary"></i> Latar Belakang Pendidikan
                    </h4>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded-3 border">
                                <small class="text-muted fw-bold d-block mb-1">Asal Sekolah (SMA/SMK)</small>
                                <span class="fw-bold text-dark">{{ $application->high_school_name }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded-3 border">
                                <small class="text-muted fw-bold d-block mb-1">Jurusan Sekolah</small>
                                <span class="fw-bold text-dark">{{ $application->high_school_major }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded-3 border">
                                <small class="text-muted fw-bold d-block mb-1">Tahun Lulus</small>
                                <span class="fw-bold text-dark">{{ $application->high_school_graduation_year }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <h4 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="fas fa-folder-open text-primary"></i> Verifikasi Berkas & Dokumen
                    </h4>
                </div>
                <div class="card-body p-4">
                    @forelse ($application->documents as $document)
                        <div class="p-4 bg-light rounded-4 border mb-3">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark">{{ $document->requirement?->label ?? $document->document_type }}</h6>
                                    <small class="text-muted"><i class="fas fa-file me-1"></i> {{ $document->file_name }} &bull; {{ number_format($document->file_size / 1024, 1) }} KB</small>
                                </div>
                                <span class="badge rounded-pill px-3 py-1 fs-7 {{ $this->statusBadgeClass($document->verification_status) }}">
                                    {{ ucfirst($document->verification_status) }}
                                </span>
                            </div>

                            @if($this->isImageDocument($document))
                                <div class="mb-3 text-center bg-white border rounded-3 p-2 overflow-hidden" style="max-height: 320px;">
                                    <a href="{{ $this->documentPreviewUrl($document) }}" target="_blank">
                                        <img src="{{ $this->documentPreviewUrl($document) }}" alt="{{ $document->file_name }}" class="img-fluid rounded" style="max-height: 300px; object-fit: contain;">
                                    </a>
                                </div>
                            @elseif($this->canPreviewDocument($document))
                                <div class="mb-3 bg-white border rounded-3 overflow-hidden" style="height: 360px;">
                                    <iframe src="{{ $this->documentPreviewUrl($document) }}" title="{{ $document->file_name }}" style="width: 100%; height: 100%; border: 0;"></iframe>
                                </div>
                            @endif

                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted fs-8 mb-1">Catatan Verifikator Dokumen</label>
                                <textarea class="form-control rounded-3 fs-7 border-secondary border-opacity-25" rows="2" placeholder="Tuliskan alasan penolakan atau keterangan tambahan berkas ini..." wire:model.defer="documentNotes.{{ $document->id }}"></textarea>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="{{ $this->documentPreviewUrl($document) }}" target="_blank" class="btn btn-outline-primary rounded-pill px-3 py-1 fw-bold fs-7 d-inline-flex align-items-center gap-1">
                                    <i class="fas fa-external-link-alt"></i> Buka Penuh
                                </a>
                                @activecan('admission-application.update')
                                    <button class="btn btn-outline-success rounded-pill px-3 py-1 fw-bold fs-7 d-inline-flex align-items-center gap-1" wire:click="verifyDocument({{ $document->id }}, 'verified')">
                                        <i class="fas fa-check"></i> Verifikasi Sah
                                    </button>
                                    <button class="btn btn-outline-danger rounded-pill px-3 py-1 fw-bold fs-7 d-inline-flex align-items-center gap-1" wire:click="verifyDocument({{ $document->id }}, 'rejected')">
                                        <i class="fas fa-times"></i> Tolak / Tidak Sah
                                    </button>
                                    <button class="btn btn-outline-secondary rounded-pill px-3 py-1 fw-bold fs-7 d-inline-flex align-items-center gap-1" wire:click="verifyDocument({{ $document->id }}, 'pending')">
                                        <i class="fas fa-undo"></i> Reset Status
                                    </button>
                                @endactivecan
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-folder-minus fs-1 text-secondary opacity-50 mb-3"></i>
                            <div class="mt-2 fw-semibold">Belum ada dokumen pendaftaran yang diunggah.</div>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <h4 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="fas fa-calendar-check text-primary"></i> Jadwal Ujian & Penilaian Seleksi
                    </h4>
                </div>
                <div class="card-body p-4 row g-3">
                    <div class="col-lg-6">
                        <div class="p-3 bg-light rounded-4 border h-100">
                            <small class="text-muted fw-bold d-block mb-3">Sesi Seleksi Ditugaskan</small>
                            @forelse($application->examParticipants as $participant)
                                <div class="mb-3 pb-3 border-bottom border-secondary border-opacity-10">
                                    <div class="fw-bold text-dark">{{ $participant->schedule?->title }}</div>
                                    <div class="text-muted fs-7 mb-1">
                                        {{ str($participant->schedule?->exam_type)->replace('_', ' ')->title() }}
                                        &bull; {{ $participant->schedule?->exam_date?->format('d M Y') }}
                                        pukul {{ $participant->schedule?->exam_time?->format('H:i') }}
                                    </div>
                                    <span class="badge rounded-pill px-2.5 py-1 fs-8 {{ $this->statusBadgeClass($participant->attendance_status) }}">
                                        {{ ucfirst($participant->attendance_status) }}
                                    </span>
                                </div>
                            @empty
                                <div class="text-muted fs-7">Belum ada jadwal ujian atau wawancara yang ditetapkan.</div>
                            @endforelse
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="p-3 bg-light rounded-4 border h-100">
                            <small class="text-muted fw-bold d-block mb-3">Komponen Penilaian (Scores)</small>
                            @forelse($application->scores as $score)
                                <div class="d-flex justify-content-between align-items-start mb-3 pb-3 border-bottom border-secondary border-opacity-10">
                                    <div>
                                        <div class="fw-bold text-dark">{{ str($score->score_type)->replace('_', ' ')->title() }}</div>
                                        <div class="text-muted fs-7">{{ $score->schedule?->title ?? 'Penilaian Manual' }} &bull; Bobot: {{ $score->weight }}</div>
                                    </div>
                                    <span class="badge bg-primary rounded-pill px-3 py-1 fs-6">{{ $score->score }}</span>
                                </div>
                            @empty
                                <div class="text-muted fs-7">Belum ada skor penilaian yang tercatat.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <h4 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="fas fa-history text-primary"></i> Riwayat Perubahan Status Pendaftaran
                    </h4>
                </div>
                <div class="card-body p-4">
                    @forelse ($application->statusHistories->sortByDesc('created_at') as $history)
                        <div class="d-flex gap-3 mb-3 pb-3 border-bottom border-light">
                            <div class="pt-1">
                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                    <i class="fas fa-exchange-alt fs-8"></i>
                                </div>
                            </div>
                            <div>
                                <div class="fw-bold text-dark fs-7">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border rounded-pill">{{ $history->from_status ?: 'Baru' }}</span>
                                    <i class="fas fa-arrow-right mx-1 text-muted fs-8"></i>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border rounded-pill">{{ $history->to_status }}</span>
                                </div>
                                <div class="text-muted fs-8 mt-1">
                                    <i class="fas fa-clock me-1"></i> {{ $history->created_at?->format('d F Y, H:i') }} oleh <strong>{{ $history->changedBy?->name ?? 'Sistem Otomatis' }}</strong>
                                </div>
                                @if($history->notes)
                                    <div class="mt-2 p-2 bg-light rounded-3 text-dark fs-8 border-start border-primary border-3">
                                        {{ $history->notes }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-muted fs-7">Belum ada riwayat perubahan status pendaftaran.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <h4 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="fas fa-pen-to-square text-primary"></i> Keputusan Review & Skor
                    </h4>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-7">Status Kelulusan / Seleksi</label>
                        <select class="form-select rounded-3 border-secondary border-opacity-25" wire:model.defer="reviewForm.status">
                            <option value="submitted">Baru Disubmit (Submitted)</option>
                            <option value="under_review">Dalam Penilaian (Under Review)</option>
                            <option value="accepted">Lulus / Diterima (Accepted)</option>
                            <option value="waitlisted">Cadangan (Waitlisted)</option>
                            <option value="rejected">Tidak Lulus / Ditolak (Rejected)</option>
                        </select>
                        @error('reviewForm.status') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-7">Skor Akhir Gabungan (0 - 100)</label>
                        <input type="number" step="0.01" class="form-control rounded-3 border-secondary border-opacity-25" wire:model.defer="reviewForm.final_score" placeholder="85.50">
                        @error('reviewForm.final_score') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark fs-7">Catatan Keputusan / Alasan</label>
                        <textarea class="form-control rounded-3 border-secondary border-opacity-25" rows="4" wire:model.defer="reviewForm.review_notes" placeholder="Catatan internal penguji atau panitia seleksi..."></textarea>
                        @error('reviewForm.review_notes') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
                    </div>
                    @activecan('admission-application.update')
                        <button class="btn btn-primary rounded-pill fw-bold py-2.5 w-100 shadow-sm d-flex align-items-center justify-content-center gap-2" wire:click="updateReview">
                            <i class="fas fa-save"></i> Simpan Hasil Review
                        </button>
                    @endactivecan
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <h4 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="fas fa-link text-primary"></i> Portal Pendaftar Mandiri
                    </h4>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted fs-7 mb-3">Bagikan tautan portal ini kepada pendaftar untuk mengecek status kelulusan, jadwal ujian, atau melengkapi ulang dokumen.</p>
                    <a href="{{ route('root.admission.portal', ['applicationNumber' => $application->application_number, 'token' => $application->access_token]) }}" target="_blank" class="btn btn-outline-primary rounded-pill fw-bold py-2 w-100 shadow-sm d-flex align-items-center justify-content-center gap-2">
                        <i class="fas fa-external-link-alt"></i> Buka Halaman Portal
                    </a>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <h4 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="fas fa-user-graduate text-primary"></i> Konversi ke Mahasiswa Aktif
                    </h4>
                </div>
                <div class="card-body p-4">
                    @if($application->converted_at)
                        <div class="alert alert-success border-0 shadow-sm rounded-3 mb-3 p-3">
                            <div class="d-flex align-items-center gap-2 mb-1 fw-bold">
                                <i class="fas fa-check-circle fs-5"></i> Berhasil Dikonversi
                            </div>
                            <small class="d-block mb-2">Pada tanggal {{ $application->converted_at?->format('d F Y, H:i') }}</small>
                            <div class="p-2 bg-white rounded border border-success border-opacity-25 fw-bold text-center text-success fs-6">
                                NIM: {{ $application->user?->studentProfile?->nim ?? '-' }}
                            </div>
                        </div>
                        <a href="{{ route('admin.admission.applications.acceptance-letter', ['application' => $application->id]) }}" target="_blank" class="btn btn-outline-success rounded-pill fw-bold py-2 w-100 shadow-sm d-flex align-items-center justify-content-center gap-2">
                            <i class="fas fa-file-pdf"></i> Unduh Surat Diterima (Acceptance Letter)
                        </a>
                    @elseif($application->status === 'accepted')
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <small class="text-muted fw-bold d-block mb-1">Preview Calon NIM yang Akan Diterima</small>
                            <div class="fw-bold text-primary fs-5">{{ $nimPreview ?? 'Belum ada aturan NIM aktif' }}</div>
                        </div>
                        @activecan('admission-application.update')
                            <button class="btn btn-primary rounded-pill fw-bold py-2.5 w-100 shadow-sm d-flex align-items-center justify-content-center gap-2" wire:click="convertToStudent" wire:loading.attr="disabled" wire:target="convertToStudent">
                                <i class="fas fa-user-plus" wire:loading.remove wire:target="convertToStudent"></i>
                                <span wire:loading.remove wire:target="convertToStudent">Generate NIM & Konversi Mahasiswa</span>
                                <span wire:loading wire:target="convertToStudent"><i class="fas fa-spinner fa-spin me-2"></i> Memproses Konversi...</span>
                            </button>
                        @endactivecan
                    @else
                        <div class="p-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-3 text-warning-emphasis fs-7">
                            <i class="fas fa-exclamation-triangle me-1"></i> Calon mahasiswa harus berstatus <strong>Lulus / Diterima (Accepted)</strong> terlebih dahulu sebelum dapat dikonversi menjadi mahasiswa aktif dan mendapatkan NIM.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
