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

    <form wire:submit.prevent="createAssignment">
        <div class="card mb-4" style="border:0;border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.08);">
            <div class="card-header py-3 d-flex justify-content-between gap-3 flex-wrap align-items-center">
                <div>
                    <h3 class="card-title mb-1" style="font-weight:800;">Assignment Dosen PA</h3>
                    <div class="text-secondary">Pilih satu mahasiswa atau assign beberapa mahasiswa sekaligus ke dosen PA.</div>
                </div>
                <button type="button" class="btn btn-light" wire:click="cancel">
                    <i class="fas fa-arrow-left me-1"></i>Kembali
                </button>
            </div>
            <div class="card-body p-4">
                <div class="mb-4">
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" id="mode-single" value="single" wire:model.live="mode">
                        <label class="btn btn-outline-primary" for="mode-single"><i class="fas fa-user me-1"></i>Single</label>
                        <input type="radio" class="btn-check" id="mode-bulk" value="bulk" wire:model.live="mode">
                        <label class="btn btn-outline-primary" for="mode-bulk"><i class="fas fa-users me-1"></i>Bulk</label>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-xl-7">
                        @if ($mode === 'single')
                            <div class="mb-4">
                                <label class="form-label required">Mahasiswa</label>
                                <input type="text" class="form-control" wire:model.live.debounce.300ms="studentSearch" placeholder="Cari nama, NIM, email, atau angkatan...">
                                @if ($this->selectedStudentLabel())
                                    <div class="alert alert-info mt-2 mb-0">Terpilih: <strong>{{ $this->selectedStudentLabel() }}</strong></div>
                                @endif
                                <div class="list-group mt-2">
                                    @foreach ($this->studentResults() as $student)
                                        <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start" wire:click="selectStudent({{ $student['id'] }})">
                                            <span>
                                                <strong>{{ $student['label'] }}</strong>
                                                <span class="d-block text-secondary small">{{ $student['meta'] }}</span>
                                            </span>
                                            <i class="fas fa-check text-primary"></i>
                                        </button>
                                    @endforeach
                                </div>
                                @error('assignmentForm.student_profile_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        @else
                            <div class="row g-2 mb-3">
                                <div class="col-md-5">
                                    <label class="form-label">Program Studi</label>
                                    <select class="form-control" wire:model.live="bulkFilters.study_program_id">
                                        <option value="">Semua Prodi</option>
                                        @foreach ($studyPrograms as $program)
                                            <option value="{{ $program['id'] }}">{{ $program['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Angkatan</label>
                                    <select class="form-control" wire:model.live="bulkFilters.entry_year">
                                        <option value="">Semua</option>
                                        @foreach ($entryYears as $year)
                                            <option value="{{ $year }}">{{ $year }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Cari</label>
                                    <input type="text" class="form-control" wire:model.live.debounce.300ms="bulkFilters.search" placeholder="Nama/NIM/email...">
                                </div>
                            </div>
                            <div class="d-flex gap-2 flex-wrap mb-2">
                                <button type="button" class="btn btn-outline-primary" wire:click="selectAllCandidates">
                                    <i class="fas fa-check-double me-1"></i>Pilih hasil tampil
                                </button>
                                <button type="button" class="btn btn-outline-secondary" wire:click="clearSelectedStudents">
                                    <i class="fas fa-eraser me-1"></i>Kosongkan
                                </button>
                                <span class="badge bg-blue-lt text-blue align-self-center">{{ count($selectedStudentIds) }} terpilih</span>
                            </div>
                            <div class="list-group" style="max-height:420px;overflow:auto;">
                                @foreach ($this->candidateStudents() as $student)
                                    <label class="list-group-item d-flex gap-3 align-items-start">
                                        <input type="checkbox" class="form-check-input mt-1" value="{{ $student['id'] }}" wire:model.live="selectedStudentIds">
                                        <span>
                                            <strong>{{ $student['label'] }}</strong>
                                            <span class="d-block text-secondary small">{{ $student['meta'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('selectedStudentIds') <small class="text-danger">{{ $message }}</small> @enderror
                        @endif
                    </div>

                    <div class="col-xl-5">
                        <div class="mb-4">
                            <label class="form-label required">Dosen PA</label>
                            <input type="text" class="form-control" wire:model.live.debounce.300ms="lecturerSearch" placeholder="Cari nama, NIDN, NIP, atau email...">
                            @if ($this->selectedLecturerLabel())
                                <div class="alert alert-info mt-2 mb-0">Terpilih: <strong>{{ $this->selectedLecturerLabel() }}</strong></div>
                            @endif
                            <div class="list-group mt-2">
                                @foreach ($this->lecturerResults() as $lecturer)
                                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between" wire:click="selectLecturer({{ $lecturer['id'] }})">
                                        <strong>{{ $lecturer['label'] }}</strong>
                                        <i class="fas fa-check text-primary"></i>
                                    </button>
                                @endforeach
                            </div>
                            @error('assignmentForm.lecturer_profile_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Tahun Akademik</label>
                                <select class="form-control" wire:model.defer="assignmentForm.academic_year_id">
                                    <option value="">Umum (Semua Tahun)</option>
                                    @foreach ($academicYears as $year)
                                        <option value="{{ $year['id'] }}">{{ $year['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('assignmentForm.academic_year_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tanggal Mulai</label>
                                <input type="date" class="form-control" wire:model.defer="assignmentForm.start_date">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tanggal Selesai</label>
                                <input type="date" class="form-control" wire:model.defer="assignmentForm.end_date">
                                @error('assignmentForm.end_date') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Catatan</label>
                                <textarea rows="3" class="form-control" wire:model.defer="assignmentForm.notes"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" wire:model.defer="assignmentForm.is_active">
                                    <span class="form-check-label">Aktif</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light" wire:click="cancel">Batal</button>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove><i class="fas fa-save me-1"></i>Simpan Assignment</span>
                    <span wire:loading><i class="fas fa-spinner fa-spin me-1"></i>Menyimpan...</span>
                </button>
            </div>
        </div>
    </form>
</div>
