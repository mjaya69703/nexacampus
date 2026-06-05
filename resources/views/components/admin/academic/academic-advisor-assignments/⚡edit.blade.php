<?php

use App\Models\Academic\AcademicAdvisorAssignment;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentProfile;
use App\Support\AcademicAdvisorService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

new class extends Component
{
    public AcademicAdvisorAssignment $assignment;
    public array $assignmentForm = [];
    public array $academicYears = [];
    public string $studentSearch = '';
    public string $lecturerSearch = '';

    public function mount($id): void
    {
        $this->assignment = AcademicAdvisorAssignment::query()
            ->with(['studentProfile.user', 'lecturerProfile.user'])
            ->findOrFail($id);

        $this->assignmentForm = [
            'student_profile_id' => $this->assignment->student_profile_id,
            'lecturer_profile_id' => $this->assignment->lecturer_profile_id,
            'academic_year_id' => $this->assignment->academic_year_id,
            'start_date' => $this->assignment->start_date?->format('Y-m-d'),
            'end_date' => $this->assignment->end_date?->format('Y-m-d'),
            'is_active' => (bool) $this->assignment->is_active,
            'notes' => $this->assignment->notes,
        ];

        $this->academicYears = AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name'])
            ->map(fn (AcademicYear $year) => ['id' => $year->id, 'label' => $year->name])
            ->all();
    }

    public function selectStudent(int $studentId): void
    {
        $this->assignmentForm['student_profile_id'] = $studentId;
    }

    public function selectLecturer(int $lecturerId): void
    {
        $this->assignmentForm['lecturer_profile_id'] = $lecturerId;
    }

    public function updateAssignment(AcademicAdvisorService $advisorService): void
    {
        $validated = $this->validate([
            'assignmentForm.student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'assignmentForm.lecturer_profile_id' => ['required', 'integer', 'exists:lecturer_profiles,id'],
            'assignmentForm.academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'assignmentForm.start_date' => ['nullable', 'date'],
            'assignmentForm.end_date' => ['nullable', 'date', 'after_or_equal:assignmentForm.start_date'],
            'assignmentForm.is_active' => ['boolean'],
            'assignmentForm.notes' => ['nullable', 'string'],
        ]);

        $advisorService->updateAssignment($this->assignment, [
            'student_profile_id' => (int) $validated['assignmentForm']['student_profile_id'],
            'lecturer_profile_id' => (int) $validated['assignmentForm']['lecturer_profile_id'],
            'academic_year_id' => $validated['assignmentForm']['academic_year_id'] ?: null,
            'start_date' => $validated['assignmentForm']['start_date'] ?: null,
            'end_date' => $validated['assignmentForm']['end_date'] ?: null,
            'is_active' => (bool) $validated['assignmentForm']['is_active'],
            'notes' => $validated['assignmentForm']['notes'] ?: null,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Assignment dosen PA berhasil diperbarui.');
        $this->redirectRoute('admin.academic.academic-advisor-assignments.index');
    }

    public function studentResults(): array
    {
        return StudentProfile::query()
            ->with(['user', 'studyProgram'])
            ->when(trim($this->studentSearch) !== '', function (Builder $query) {
                $search = '%'.trim($this->studentSearch).'%';

                $query->where(function (Builder $nested) use ($search) {
                    $nested->where('nim', 'like', $search)
                        ->orWhere('entry_year', 'like', $search)
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhere('email', 'like', $search));
                });
            })
            ->orderBy('nim')
            ->limit(8)
            ->get()
            ->map(fn (StudentProfile $student) => [
                'id' => $student->id,
                'label' => trim(($student->nim ?? '-').' - '.($student->user?->name ?? '-')),
                'meta' => trim(($student->studyProgram?->name ?? '-').' / Angkatan '.($student->entry_year ?? '-')),
            ])
            ->all();
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

    public function selectedStudentLabel(): ?string
    {
        $student = StudentProfile::query()
            ->with(['user', 'studyProgram'])
            ->find($this->assignmentForm['student_profile_id'] ?? null);

        return $student ? trim(($student->nim ?? '-').' - '.($student->user?->name ?? '-')) : null;
    }

    public function selectedLecturerLabel(): ?string
    {
        $lecturer = LecturerProfile::query()
            ->with('user')
            ->find($this->assignmentForm['lecturer_profile_id'] ?? null);

        return $lecturer ? app(AcademicAdvisorService::class)->lecturerLabel($lecturer) : null;
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.academic-advisor-assignments.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Edit Assignment Dosen PA',
        ]);
    }
};
?>

<div>
    <x-alert />

    <form wire:submit.prevent="updateAssignment">
        <div class="card" style="border:0;border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.08);">
            <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <h3 class="card-title mb-1" style="font-weight:800;">Edit Assignment Dosen PA</h3>
                    <div class="text-secondary">Ubah mahasiswa, dosen PA, atau periode assignment aktif.</div>
                </div>
                <button type="button" class="btn btn-light" wire:click="cancel">
                    <i class="fas fa-arrow-left me-1"></i>Kembali
                </button>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-xl-6">
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

                    <div class="col-xl-6">
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

                    <div class="col-lg-4">
                        <label class="form-label">Tahun Akademik</label>
                        <select class="form-control" wire:model.defer="assignmentForm.academic_year_id">
                            <option value="">Umum (Semua Tahun)</option>
                            @foreach ($academicYears as $year)
                                <option value="{{ $year['id'] }}">{{ $year['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" class="form-control" wire:model.defer="assignmentForm.start_date">
                    </div>
                    <div class="col-lg-4">
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
            <div class="card-footer d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light" wire:click="cancel">Batal</button>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove><i class="fas fa-save me-1"></i>Simpan Perubahan</span>
                    <span wire:loading><i class="fas fa-spinner fa-spin me-1"></i>Menyimpan...</span>
                </button>
            </div>
        </div>
    </form>
</div>
