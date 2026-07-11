<?php

use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\GradeAppeal;
use App\Models\Academic\StudentGrade;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public bool $hasProfile = false;
    public bool $showForm = false;
    public array $gradeOptions = [];
    public array $appealRows = [];
    public array $attachments = [];
    public array $summary = [
        'available_grades' => 0,
        'active_appeals' => 0,
        'resolved_appeals' => 0,
    ];
    public ?int $studentGradeId = null;
    public string $reasonCategory = 'calculation';
    public string $reason = '';
    public ?string $expectedOutcome = null;
    public ?string $requestedScore = null;

    public function mount(): void
    {
        $this->loadData();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Keberatan Nilai',
        ]);
    }

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
    }

    public function submitAppeal(): void
    {
        $studentProfile = auth()->user()?->studentProfile()->first();

        if (! $studentProfile) {
            abort(403);
        }

        $validGradeIds = collect($this->gradeOptions)->pluck('id')->all();

        $this->validate([
            'studentGradeId' => ['required', 'integer', Rule::in($validGradeIds)],
            'reasonCategory' => ['required', Rule::in(array_keys($this->categoryLabels()))],
            'reason' => ['required', 'string', 'min:20', 'max:4000'],
            'expectedOutcome' => ['nullable', 'string', 'max:4000'],
            'requestedScore' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'attachments' => ['nullable', 'array', 'max:4'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt', 'max:5120'],
        ], [
            'studentGradeId.required' => 'Pilih mata kuliah yang ingin diajukan.',
            'reason.min' => 'Tuliskan alasan minimal 20 karakter agar dosen punya konteks yang cukup.',
        ]);

        $grade = StudentGrade::query()
            ->with(['studyPlanDetail.studyPlan', 'studyPlanDetail.courseOffering.lecturers'])
            ->where('grade_status', 'Published')
            ->whereHas('studyPlanDetail.studyPlan', function ($query) use ($studentProfile) {
                $query->where('student_profile_id', $studentProfile->id);
            })
            ->findOrFail($this->studentGradeId);

        $hasActiveAppeal = GradeAppeal::query()
            ->where('student_grade_id', $grade->id)
            ->where('student_profile_id', $studentProfile->id)
            ->whereIn('status', ['submitted', 'under_review'])
            ->exists();

        if ($hasActiveAppeal) {
            $this->addError('studentGradeId', 'Masih ada pengajuan aktif untuk nilai ini.');

            return;
        }

        $offering = $grade->studyPlanDetail?->courseOffering;
        $lecturerId = CourseOfferingLecturer::query()
            ->where('course_offering_id', $offering?->id)
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN role = 'Primary' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->value('lecturer_profile_id');

        $appeal = GradeAppeal::query()->create([
            'student_grade_id' => $grade->id,
            'student_profile_id' => $studentProfile->id,
            'course_offering_id' => $offering?->id,
            'lecturer_profile_id' => $lecturerId,
            'student_user_id' => auth()->id(),
            'status' => 'submitted',
            'reason_category' => $this->reasonCategory,
            'reason' => $this->reason,
            'expected_outcome' => $this->expectedOutcome ?: null,
            'original_score' => $grade->final_score,
            'requested_score' => $this->requestedScore !== '' ? $this->requestedScore : null,
            'submitted_at' => now(),
            'created_by' => auth()->id(),
        ]);

        foreach ($this->attachments as $file) {
            $originalName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();
            $extension = $file->getClientOriginalExtension() ?: 'bin';
            $storedName = Str::uuid().'.'.$extension;
            $path = $file->storeAs('private/grade-appeals/'.$appeal->id, $storedName);

            $appeal->attachments()->create([
                'file_path' => $path,
                'file_name' => $originalName,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
            ]);
        }

        $this->reset(['studentGradeId', 'reason', 'expectedOutcome', 'requestedScore', 'attachments']);
        $this->reasonCategory = 'calculation';
        $this->showForm = false;
        $this->loadData();

        session()->flash('success', 'Pengajuan keberatan nilai berhasil dikirim.');
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            'submitted' => 'Terkirim',
            'under_review' => 'Direview',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'closed' => 'Ditutup',
            default => $status ?: '-',
        };
    }

    public function statusStyle(?string $status): string
    {
        return match ($status) {
            'approved' => 'background:#dcfce7;color:#15803d;',
            'rejected' => 'background:#fee2e2;color:#dc2626;',
            'under_review' => 'background:#dbeafe;color:#1d4ed8;',
            'closed' => 'background:#f1f5f9;color:#64748b;',
            default => 'background:#fef3c7;color:#b45309;',
        };
    }

    public function categoryLabels(): array
    {
        return [
            'calculation' => 'Perhitungan nilai',
            'component' => 'Komponen penilaian',
            'input_error' => 'Kesalahan input',
            'feedback' => 'Butuh klarifikasi',
            'other' => 'Lainnya',
        ];
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    private function loadData(): void
    {
        $studentProfile = auth()->user()?->studentProfile()->first();

        if (! $studentProfile) {
            $this->hasProfile = false;
            $this->gradeOptions = [];
            $this->appealRows = [];

            return;
        }

        $this->hasProfile = true;

        $grades = StudentGrade::query()
            ->with([
                'studyPlanDetail.studyPlan.academicYear',
                'studyPlanDetail.courseOffering.course',
                'studyPlanDetail.courseOffering.lecturers.lecturerProfile.user',
            ])
            ->where('grade_status', 'Published')
            ->whereHas('studyPlanDetail.studyPlan', function ($query) use ($studentProfile) {
                $query->where('student_profile_id', $studentProfile->id);
            })
            ->orderByDesc('graded_at')
            ->orderByDesc('id')
            ->get();

        $activeGradeIds = GradeAppeal::query()
            ->where('student_profile_id', $studentProfile->id)
            ->whereIn('status', ['submitted', 'under_review'])
            ->pluck('student_grade_id')
            ->all();

        $this->gradeOptions = $grades->map(function (StudentGrade $grade) use ($activeGradeIds) {
            $detail = $grade->studyPlanDetail;
            $offering = $detail?->courseOffering;
            $course = $offering?->course;
            $lecturer = $offering?->lecturers
                ?->first(fn ($row) => (bool) $row->is_active)
                ?->lecturerProfile?->user?->name;

            return [
                'id' => $grade->id,
                'course' => trim(($course?->code ? $course->code.' - ' : '').($course?->name ?? '-')),
                'academic_year' => $detail?->studyPlan?->academicYear?->name ?? '-',
                'lecturer' => $lecturer ?? '-',
                'score' => $grade->final_score,
                'letter' => $grade->letter_grade ?? '-',
                'has_active_appeal' => in_array($grade->id, $activeGradeIds, true),
            ];
        })->values()->all();

        $appeals = GradeAppeal::query()
            ->with(['studentGrade.studyPlanDetail.courseOffering.course', 'courseOffering.academicYear', 'lecturerProfile.user', 'attachments'])
            ->where('student_profile_id', $studentProfile->id)
            ->latest('submitted_at')
            ->latest('id')
            ->get();

        $this->appealRows = $appeals->map(function (GradeAppeal $appeal) {
            $course = $appeal->courseOffering?->course;

            return [
                'id' => $appeal->id,
                'course' => trim(($course?->code ? $course->code.' - ' : '').($course?->name ?? '-')),
                'academic_year' => $appeal->courseOffering?->academicYear?->name ?? '-',
                'lecturer' => $appeal->lecturerProfile?->user?->name ?? '-',
                'status' => $appeal->status,
                'category' => $this->categoryLabels()[$appeal->reason_category] ?? $appeal->reason_category,
                'original_score' => $appeal->original_score,
                'requested_score' => $appeal->requested_score,
                'resolved_score' => $appeal->resolved_score,
                'submitted_at' => $appeal->submitted_at?->format('d M Y H:i') ?? '-',
                'resolved_at' => $appeal->resolved_at?->format('d M Y H:i'),
                'response' => $appeal->lecturer_response,
                'attachments' => $appeal->attachments
                    ->map(fn ($attachment) => [
                        'id' => $attachment->id,
                        'name' => $attachment->file_name,
                        'size' => $attachment->file_size,
                    ])
                    ->values()
                    ->all(),
            ];
        })->values()->all();

        $this->summary = [
            'available_grades' => count($this->gradeOptions),
            'active_appeals' => $appeals->whereIn('status', ['submitted', 'under_review'])->count(),
            'resolved_appeals' => $appeals->whereIn('status', ['approved', 'rejected', 'closed'])->count(),
        ];
    }
};
?>

@include('components.lecturer.assignments.assignment-styles')

<div>
    <x-alert />

    <div class="card assignment-card assignment-hero mb-4">
        <div class="card-body p-4 p-lg-5" style="position:relative;">
            <div class="d-flex justify-content-between gap-3 flex-wrap">
                <div class="d-flex gap-3">
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.75rem;"><i class="fas fa-scale-balanced"></i></span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Ruang Akademik Mahasiswa</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">Keberatan Nilai</h1>
                        <div style="opacity:.9;">Ajukan klarifikasi nilai yang sudah dipublikasikan dan pantau respons dosen pengampu.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-book"></i>{{ $summary['available_grades'] }} nilai</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-hourglass-half"></i>{{ $summary['active_appeals'] }} aktif</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-check-circle"></i>{{ $summary['resolved_appeals'] }} selesai</span>
                        </div>
                    </div>
                </div>
                <button type="button" class="assignment-action" style="background:#fff;color:#4f46e5;" wire:click="toggleForm">
                    <i class="fas {{ $showForm ? 'fa-xmark' : 'fa-plus' }}"></i>{{ $showForm ? 'Tutup Form' : 'Ajukan Keberatan' }}
                </button>
            </div>
        </div>
    </div>

    @if (! $hasProfile)
        <div class="assignment-panel text-center py-5 text-secondary">Profil mahasiswa belum tersedia.</div>
    @else
        @if ($showForm)
            <div class="card assignment-card mb-4">
                <div class="card-header py-3">
                    <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-pen-to-square me-2 text-primary"></i>Form Pengajuan</h3>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label class="form-label">Nilai</label>
                            <select class="form-select" wire:model="studentGradeId">
                                <option value="">Pilih nilai published</option>
                                @foreach ($gradeOptions as $option)
                                    <option value="{{ $option['id'] }}" @disabled($option['has_active_appeal'])>
                                        {{ $option['course'] }} - {{ $option['letter'] }} ({{ $option['score'] !== null ? number_format((float) $option['score'], 2) : '-' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('studentGradeId') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label">Kategori</label>
                            <select class="form-select" wire:model="reasonCategory">
                                @foreach ($this->categoryLabels() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('reasonCategory') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label">Skor yang Diharapkan</label>
                            <input type="number" min="0" max="100" step="0.01" class="form-control" wire:model="requestedScore">
                            @error('requestedScore') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Alasan</label>
                            <textarea class="form-control" rows="4" wire:model="reason"></textarea>
                            @error('reason') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Hasil yang Diharapkan</label>
                            <textarea class="form-control" rows="3" wire:model="expectedOutcome"></textarea>
                            @error('expectedOutcome') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Bukti Pendukung</label>
                            <div class="assignment-dropzone" x-data="{ uploading: false, progress: 0 }" x-on:livewire-upload-start="uploading = true; progress = 0" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-progress="progress = $event.detail.progress">
                                <input type="file" class="form-control" wire:model="attachments" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                                <div class="form-hint mt-2">Opsional. Maksimal 4 file, masing-masing 5MB. Format: gambar, PDF, Word, Excel, atau TXT.</div>
                                <div x-show="uploading" class="mt-3" style="display:none;">
                                    <div class="d-flex justify-content-between small text-primary mb-1">
                                        <span><i class="fas fa-spinner fa-spin me-1"></i>Mengunggah bukti...</span>
                                        <span x-text="progress + '%'"></span>
                                    </div>
                                    <div class="assignment-progress"><span x-bind:style="'width:' + progress + '%'"></span></div>
                                </div>
                                @if ($attachments)
                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        @foreach ($attachments as $index => $file)
                                            <span class="assignment-attachment">
                                                <i class="fas fa-paperclip"></i>{{ $file->getClientOriginalName() }}
                                                <button type="button" class="btn btn-sm p-0 ms-1 text-danger" wire:click="removeAttachment({{ $index }})" title="Hapus lampiran">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            @error('attachments') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            @error('attachments.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="button" class="btn btn-primary" wire:click="submitAppeal" wire:loading.attr="disabled" wire:target="submitAppeal,attachments">
                        <span wire:loading.remove wire:target="submitAppeal"><i class="fas fa-paper-plane me-1"></i>Kirim Pengajuan</span>
                        <span wire:loading wire:target="submitAppeal">Mengirim...</span>
                    </button>
                </div>
            </div>
        @endif

        <div class="card assignment-card">
            <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-list-check me-2 text-primary"></i>Riwayat Pengajuan</h3>
                <span class="assignment-pill">{{ count($appealRows) }} pengajuan</span>
            </div>
            <div class="card-body p-4">
                <div class="assignment-shell">
                    @forelse ($appealRows as $appeal)
                        <div class="assignment-list-item">
                            <div class="row g-3 align-items-start">
                                <div class="col-xl-7">
                                    <div class="d-flex gap-3">
                                        <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-file-signature"></i></span>
                                        <div>
                                            <div class="d-flex flex-wrap gap-2 mb-2">
                                                <span class="assignment-pill" style="{{ $this->statusStyle($appeal['status']) }}"><i class="fas fa-circle-info"></i>{{ $this->statusLabel($appeal['status']) }}</span>
                                                <span class="assignment-pill"><i class="fas fa-tag"></i>{{ $appeal['category'] }}</span>
                                            </div>
                                            <div class="fw-bold">{{ $appeal['course'] }}</div>
                                            <div class="text-secondary small">{{ $appeal['academic_year'] }} · {{ $appeal['lecturer'] }}</div>
                                            @if ($appeal['response'])
                                                <div class="assignment-panel mt-3">
                                                    <div class="text-secondary small mb-1">Respons dosen</div>
                                                    <div>{{ $appeal['response'] }}</div>
                                                </div>
                                            @endif
                                            @if ($appeal['attachments'])
                                                <div class="d-flex flex-wrap gap-2 mt-3">
                                                    @foreach ($appeal['attachments'] as $attachment)
                                                        <a class="assignment-attachment" href="{{ route('student.grade-appeal-attachments.preview', $attachment['id']) }}" target="_blank">
                                                            <i class="fas fa-paperclip"></i>{{ $attachment['name'] }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-5">
                                    <div class="row g-2">
                                        <div class="col-md-4"><div class="assignment-panel h-100"><div class="text-secondary small">Awal</div><div class="fw-bold">{{ $appeal['original_score'] !== null ? number_format((float) $appeal['original_score'], 2) : '-' }}</div></div></div>
                                        <div class="col-md-4"><div class="assignment-panel h-100"><div class="text-secondary small">Diminta</div><div class="fw-bold">{{ $appeal['requested_score'] !== null ? number_format((float) $appeal['requested_score'], 2) : '-' }}</div></div></div>
                                        <div class="col-md-4"><div class="assignment-panel h-100"><div class="text-secondary small">Final</div><div class="fw-bold">{{ $appeal['resolved_score'] !== null ? number_format((float) $appeal['resolved_score'], 2) : '-' }}</div></div></div>
                                        <div class="col-12 text-secondary small"><i class="fas fa-clock me-1"></i>Dikirim {{ $appeal['submitted_at'] }} @if($appeal['resolved_at']) · selesai {{ $appeal['resolved_at'] }} @endif</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-secondary py-5">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <div>Belum ada pengajuan keberatan nilai.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
