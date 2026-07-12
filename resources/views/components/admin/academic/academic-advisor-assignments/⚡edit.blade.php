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

    <x-admin.academic.header
        title="Edit Assignment Dosen PA"
        description="Perbarui informasi relasi bimbingan akademik, ganti dosen pembimbing atau mahasiswa, dan sesuaikan periode aktif penugasan."
        icon="chalkboard-teacher"
    >
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-light text-dark fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2 border" wire:click="cancel">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </button>
        </div>
    </x-admin.academic.header>

    <form wire:submit.prevent="updateAssignment">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white border-bottom p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                        <i class="fa fa-user-edit fs-5"></i>
                    </div>
                    <div>
                        <h4 class="card-title fw-bold mb-1 text-dark">Ubah Penugasan Pembimbing Akademik</h4>
                        <div class="text-muted small">Cari dan pilih mahasiswa atau dosen PA pengganti, atau ubah masa berlaku penugasan.</div>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-xl-6">
                        <div class="card border border-light bg-light bg-opacity-50 rounded-4 p-4 h-100">
                            <h6 class="fw-bold text-dark mb-3"><i class="fa fa-user-graduate text-primary me-2"></i>Mahasiswa Bimbingan</h6>
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
                    </div>

                    <div class="col-xl-6">
                        <div class="card border border-light bg-light bg-opacity-50 rounded-4 p-4 h-100">
                            <h6 class="fw-bold text-dark mb-3"><i class="fa fa-user-tie text-primary me-2"></i>Dosen Pembimbing Akademik</h6>
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
                    </div>

                    <div class="col-lg-4">
                        <label class="form-label fw-semibold small">Tahun Akademik</label>
                        <select class="form-select" wire:model.defer="assignmentForm.academic_year_id">
                            <option value="">Umum (Semua Tahun / Berlaku Seterusnya)</option>
                            @foreach ($academicYears as $year)
                                <option value="{{ $year['id'] }}">{{ $year['label'] }}</option>
                            @endforeach
                        </select>
                        @error('assignmentForm.academic_year_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-lg-4">
                        <label class="form-label fw-semibold small">Tanggal Mulai</label>
                        <input type="date" class="form-control" wire:model.defer="assignmentForm.start_date">
                        @error('assignmentForm.start_date') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-lg-4">
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
            <div class="card-footer bg-white border-top p-4 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light rounded-pill px-4 py-2 fw-semibold border" wire:click="cancel">
                    <i class="fas fa-times me-1"></i> Batal
                </button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-semibold" wire:loading.attr="disabled">
                    <span wire:loading.remove><i class="fas fa-save me-1"></i> Simpan Perubahan</span>
                    <span wire:loading><i class="fas fa-spinner fa-spin me-1"></i> Menyimpan...</span>
                </button>
            </div>
        </div>
    </form>
</div>
