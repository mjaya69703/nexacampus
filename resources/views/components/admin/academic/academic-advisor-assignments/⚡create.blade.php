<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Support\AcademicAdvisorService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

new class extends Component
{
    public string $mode = 'single';
    public string $studentSearch = '';
    public string $lecturerSearch = '';
    public array $selectedStudentIds = [];
    public array $assignmentForm = [];
    public array $bulkFilters = [];
    public array $academicYears = [];
    public array $studyPrograms = [];
    public array $entryYears = [];

    public function mount(): void
    {
        $this->assignmentForm = [
            'student_profile_id' => '',
            'lecturer_profile_id' => '',
            'academic_year_id' => '',
            'start_date' => '',
            'end_date' => '',
            'is_active' => true,
            'notes' => '',
        ];

        $this->bulkFilters = [
            'study_program_id' => '',
            'entry_year' => '',
            'search' => '',
        ];

        $this->academicYears = AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name'])
            ->map(fn (AcademicYear $year) => ['id' => $year->id, 'label' => $year->name])
            ->all();

        $this->studyPrograms = StudyProgram::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (StudyProgram $program) => ['id' => $program->id, 'label' => $program->name])
            ->all();

        $this->entryYears = StudentProfile::query()
            ->whereNotNull('entry_year')
            ->distinct()
            ->orderByDesc('entry_year')
            ->pluck('entry_year')
            ->values()
            ->all();
    }

    public function updatedMode(): void
    {
        $this->resetValidation();
    }

    public function selectStudent(int $studentId): void
    {
        $this->assignmentForm['student_profile_id'] = $studentId;
    }

    public function selectLecturer(int $lecturerId): void
    {
        $this->assignmentForm['lecturer_profile_id'] = $lecturerId;
    }

    public function selectAllCandidates(): void
    {
        $ids = collect($this->candidateStudents())->pluck('id')->all();
        $this->selectedStudentIds = collect($this->selectedStudentIds)->merge($ids)->unique()->values()->all();
    }

    public function clearSelectedStudents(): void
    {
        $this->selectedStudentIds = [];
    }

    public function createAssignment(AcademicAdvisorService $advisorService): void
    {
        $validated = $this->validate($this->rules());

        $payload = [
            'lecturer_profile_id' => (int) $validated['assignmentForm']['lecturer_profile_id'],
            'academic_year_id' => $validated['assignmentForm']['academic_year_id'] ?: null,
            'start_date' => $validated['assignmentForm']['start_date'] ?: null,
            'end_date' => $validated['assignmentForm']['end_date'] ?: null,
            'is_active' => (bool) $validated['assignmentForm']['is_active'],
            'notes' => $validated['assignmentForm']['notes'] ?: null,
            'created_by' => auth()->id(),
        ];

        if ($this->mode === 'single') {
            $advisorService->createAssignment(array_merge($payload, [
                'student_profile_id' => (int) $validated['assignmentForm']['student_profile_id'],
            ]));

            session()->flash('success', 'Assignment dosen PA berhasil dibuat.');
            $this->redirectRoute('admin.academic.academic-advisor-assignments.index');

            return;
        }

        $result = $advisorService->bulkAssign($validated['selectedStudentIds'], $payload);

        session()->flash(
            $result['created'] > 0 ? 'success' : 'warning',
            "Bulk assignment selesai. Dibuat: {$result['created']}. Dilewati: ".count($result['skipped']).'.'
        );

        if ($result['skipped'] !== []) {
            session()->flash('warning', 'Dilewati karena sudah punya PA aktif: '.implode(', ', array_slice($result['skipped'], 0, 6)));
        }

        $this->redirectRoute('admin.academic.academic-advisor-assignments.index');
    }

    public function studentResults(): array
    {
        return $this->studentQuery($this->studentSearch)
            ->limit(8)
            ->get()
            ->map(fn (StudentProfile $student) => $this->studentOption($student))
            ->all();
    }

    public function selectedStudentLabel(): ?string
    {
        $studentId = $this->assignmentForm['student_profile_id'] ?? null;

        if (! $studentId) {
            return null;
        }

        $student = StudentProfile::query()->with(['user', 'studyProgram'])->find($studentId);

        return $student ? $this->studentOption($student)['label'] : null;
    }

    public function lecturerResults(): array
    {
        return LecturerProfile::query()
            ->with('user')
            ->when(trim($this->lecturerSearch) !== '', function (Builder $query) {
                $search = '%'.trim($this->lecturerSearch).'%';

                $query->where(function (Builder $nested) use ($search) {
                    $nested->where('nidn', 'like', $search)
                        ->orWhere('nidk', 'like', $search)
                        ->orWhere('nip', 'like', $search)
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhere('email', 'like', $search));
                });
            })
            ->orderBy('nidn')
            ->limit(8)
            ->get()
            ->map(fn (LecturerProfile $lecturer) => [
                'id' => $lecturer->id,
                'label' => app(AcademicAdvisorService::class)->lecturerLabel($lecturer),
            ])
            ->all();
    }

    public function selectedLecturerLabel(): ?string
    {
        $lecturerId = $this->assignmentForm['lecturer_profile_id'] ?? null;

        if (! $lecturerId) {
            return null;
        }

        $lecturer = LecturerProfile::query()->with('user')->find($lecturerId);

        return $lecturer ? app(AcademicAdvisorService::class)->lecturerLabel($lecturer) : null;
    }

    public function candidateStudents(): array
    {
        return $this->studentQuery($this->bulkFilters['search'] ?? '')
            ->when(filled($this->bulkFilters['study_program_id'] ?? null), fn (Builder $query) => $query->where('study_program_id', $this->bulkFilters['study_program_id']))
            ->when(filled($this->bulkFilters['entry_year'] ?? null), fn (Builder $query) => $query->where('entry_year', $this->bulkFilters['entry_year']))
            ->limit(40)
            ->get()
            ->map(fn (StudentProfile $student) => $this->studentOption($student))
            ->all();
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.academic-advisor-assignments.index');
    }

    private function rules(): array
    {
        $rules = [
            'mode' => ['required', 'in:single,bulk'],
            'assignmentForm.lecturer_profile_id' => ['required', 'integer', 'exists:lecturer_profiles,id'],
            'assignmentForm.academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'assignmentForm.start_date' => ['nullable', 'date'],
            'assignmentForm.end_date' => ['nullable', 'date', 'after_or_equal:assignmentForm.start_date'],
            'assignmentForm.is_active' => ['boolean'],
            'assignmentForm.notes' => ['nullable', 'string'],
        ];

        if ($this->mode === 'single') {
            $rules['assignmentForm.student_profile_id'] = ['required', 'integer', 'exists:student_profiles,id'];
        } else {
            $rules['selectedStudentIds'] = ['required', 'array', 'min:1'];
            $rules['selectedStudentIds.*'] = ['integer', 'exists:student_profiles,id'];
        }

        return $rules;
    }

    private function studentQuery(?string $search): Builder
    {
        return StudentProfile::query()
            ->with(['user', 'studyProgram'])
            ->when(trim((string) $search) !== '', function (Builder $query) use ($search) {
                $keyword = '%'.trim((string) $search).'%';

                $query->where(function (Builder $nested) use ($keyword) {
                    $nested->where('nim', 'like', $keyword)
                        ->orWhere('entry_year', 'like', $keyword)
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('first_name', 'like', $keyword)
                            ->orWhere('last_name', 'like', $keyword)
                            ->orWhere('email', 'like', $keyword));
                });
            })
            ->orderBy('nim');
    }

    private function studentOption(StudentProfile $student): array
    {
        return [
            'id' => $student->id,
            'label' => trim(($student->nim ?? '-').' - '.($student->user?->name ?? '-')),
            'meta' => trim(($student->studyProgram?->name ?? '-').' / Angkatan '.($student->entry_year ?? '-')),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Tambah Assignment Dosen PA',
        ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Tambah Assignment Dosen PA"
        description="Pilih mode penugasan single atau bulk untuk menetapkan dosen pembimbing akademik kepada mahasiswa aktif."
        icon="chalkboard-teacher"
    >
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-light text-dark fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2 border" wire:click="cancel">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </button>
        </div>
    </x-admin.academic.header>

    <form wire:submit.prevent="createAssignment">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white border-bottom p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-user-plus fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Penugasan Pembimbing Akademik</h4>
                            <div class="text-muted small">Pilih mode penugasan, tentukan mahasiswa, dosen pembimbing, dan periode aktif bimbingan.</div>
                        </div>
                    </div>
                    <div>
                        <div class="btn-group shadow-sm rounded-pill p-1 bg-light border" role="group">
                            <input type="radio" class="btn-check" id="mode-single" value="single" wire:model.live="mode">
                            <label class="btn btn-sm rounded-pill px-3 py-1 fw-semibold {{ $mode === 'single' ? 'btn-primary' : 'btn-light border-0 text-dark' }}" for="mode-single"><i class="fas fa-user me-1"></i> Single</label>
                            <input type="radio" class="btn-check" id="mode-bulk" value="bulk" wire:model.live="mode">
                            <label class="btn btn-sm rounded-pill px-3 py-1 fw-semibold {{ $mode === 'bulk' ? 'btn-primary' : 'btn-light border-0 text-dark' }}" for="mode-bulk"><i class="fas fa-users me-1"></i> Bulk</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-xl-7">
                        <div class="card border border-light bg-light bg-opacity-50 rounded-4 p-4 h-100">
                            <h6 class="fw-bold text-dark mb-3"><i class="fa fa-users text-primary me-2"></i>Pilihan Mahasiswa ({{ $mode === 'single' ? 'Single Assignment' : 'Bulk Assignment' }})</h6>

                            @if ($mode === 'single')
                                <div>
                                    <label class="form-label fw-semibold small">Cari Mahasiswa <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" wire:model.live.debounce.300ms="studentSearch" placeholder="Cari nama, NIM, email, atau angkatan...">
                                    @if ($this->selectedStudentLabel())
                                        <div class="alert alert-info border-0 shadow-sm rounded-3 mt-3 mb-0 d-flex align-items-center gap-2 p-3">
                                            <i class="fa fa-check-circle text-info fs-5"></i>
                                            <div>Terpilih: <strong class="text-dark">{{ $this->selectedStudentLabel() }}</strong></div>
                                        </div>
                                    @endif
                                    <div class="list-group mt-3 shadow-sm rounded-3 overflow-hidden">
                                        @foreach ($this->studentResults() as $student)
                                            <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start p-3 border-bottom" wire:click="selectStudent({{ $student['id'] }})">
                                                <span>
                                                    <strong class="text-dark">{{ $student['label'] }}</strong>
                                                    <span class="d-block text-muted small mt-1">{{ $student['meta'] }}</span>
                                                </span>
                                                <i class="fas fa-check text-primary mt-1"></i>
                                            </button>
                                        @endforeach
                                    </div>
                                    @error('assignmentForm.student_profile_id') <span class="text-danger small mt-2 d-block">{{ $message }}</span> @enderror
                                </div>
                            @else
                                <div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-5">
                                            <label class="form-label small fw-semibold">Program Studi</label>
                                            <select class="form-select" wire:model.live="bulkFilters.study_program_id">
                                                <option value="">Semua Prodi</option>
                                                @foreach ($studyPrograms as $program)
                                                    <option value="{{ $program['id'] }}">{{ $program['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold">Angkatan</label>
                                            <select class="form-select" wire:model.live="bulkFilters.entry_year">
                                                <option value="">Semua</option>
                                                @foreach ($entryYears as $year)
                                                    <option value="{{ $year }}">{{ $year }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold">Cari</label>
                                            <input type="text" class="form-control" wire:model.live.debounce.300ms="bulkFilters.search" placeholder="Nama/NIM/email...">
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold" wire:click="selectAllCandidates">
                                                <i class="fas fa-check-double me-1"></i> Pilih Hasil Tampil
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold" wire:click="clearSelectedStudents">
                                                <i class="fas fa-eraser me-1"></i> Kosongkan
                                            </button>
                                        </div>
                                        <span class="badge rounded-pill bg-primary px-3 py-2">{{ count($selectedStudentIds) }} mahasiswa terpilih</span>
                                    </div>
                                    <div class="list-group shadow-sm rounded-3 overflow-auto border bg-white" style="max-height: 380px;">
                                        @foreach ($this->candidateStudents() as $student)
                                            <label class="list-group-item list-group-item-action d-flex gap-3 align-items-start p-3 border-bottom mb-0">
                                                <input type="checkbox" class="form-check-input mt-1 flex-shrink-0" value="{{ $student['id'] }}" wire:model.live="selectedStudentIds">
                                                <span>
                                                    <strong class="text-dark">{{ $student['label'] }}</strong>
                                                    <span class="d-block text-muted small mt-1">{{ $student['meta'] }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('selectedStudentIds') <span class="text-danger small mt-2 d-block">{{ $message }}</span> @enderror
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="col-xl-5">
                        <div class="card border border-light bg-light bg-opacity-50 rounded-4 p-4 h-100">
                            <h6 class="fw-bold text-dark mb-3"><i class="fa fa-user-tie text-primary me-2"></i>Dosen PA & Aturan Penugasan</h6>

                            <div class="mb-4">
                                <label class="form-label fw-semibold small">Cari Dosen PA <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" wire:model.live.debounce.300ms="lecturerSearch" placeholder="Cari nama, NIDN, NIP, atau email...">
                                @if ($this->selectedLecturerLabel())
                                    <div class="alert alert-info border-0 shadow-sm rounded-3 mt-3 mb-0 d-flex align-items-center gap-2 p-3">
                                        <i class="fa fa-check-circle text-info fs-5"></i>
                                        <div>Terpilih: <strong class="text-dark">{{ $this->selectedLecturerLabel() }}</strong></div>
                                    </div>
                                @endif
                                <div class="list-group mt-3 shadow-sm rounded-3 overflow-hidden">
                                    @foreach ($this->lecturerResults() as $lecturer)
                                        <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3 border-bottom" wire:click="selectLecturer({{ $lecturer['id'] }})">
                                            <strong class="text-dark">{{ $lecturer['label'] }}</strong>
                                            <i class="fas fa-check text-primary"></i>
                                        </button>
                                    @endforeach
                                </div>
                                @error('assignmentForm.lecturer_profile_id') <span class="text-danger small mt-2 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold small">Tahun Akademik</label>
                                    <select class="form-select" wire:model.defer="assignmentForm.academic_year_id">
                                        <option value="">Umum (Semua Tahun / Berlaku Seterusnya)</option>
                                        @foreach ($academicYears as $year)
                                            <option value="{{ $year['id'] }}">{{ $year['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('assignmentForm.academic_year_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Tanggal Mulai</label>
                                    <input type="date" class="form-control" wire:model.defer="assignmentForm.start_date">
                                    @error('assignmentForm.start_date') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Tanggal Selesai</label>
                                    <input type="date" class="form-control" wire:model.defer="assignmentForm.end_date">
                                    @error('assignmentForm.end_date') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold small">Catatan</label>
                                    <textarea rows="2" class="form-control" wire:model.defer="assignmentForm.notes" placeholder="Catatan atau keterangan penugasan..."></textarea>
                                    @error('assignmentForm.notes') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-12 border-top pt-3">
                                    <label class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" wire:model.defer="assignmentForm.is_active">
                                        <span class="form-check-label fw-semibold text-dark">Status Assignment Aktif</span>
                                    </label>
                                    @error('assignmentForm.is_active') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white border-top p-4 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light rounded-pill px-4 py-2 fw-semibold border" wire:click="cancel">
                    <i class="fas fa-times me-1"></i> Batal
                </button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-semibold" wire:loading.attr="disabled">
                    <span wire:loading.remove><i class="fas fa-save me-1"></i> Simpan Assignment</span>
                    <span wire:loading><i class="fas fa-spinner fa-spin me-1"></i> Menyimpan...</span>
                </button>
            </div>
        </div>
    </form>
</div>
