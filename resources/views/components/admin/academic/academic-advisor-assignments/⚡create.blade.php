<?php

use App\Models\Academic\AcademicAdvisorAssignment;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentProfile;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public array $assignmentForm = [];
    public array $students = [];
    public array $lecturers = [];
    public array $academicYears = [];

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

        $this->students = StudentProfile::query()
            ->with('user')
            ->orderBy('nim')
            ->get()
            ->map(fn (StudentProfile $student) => [
                'id' => $student->id,
                'label' => ($student->nim ?? '-') . ' - ' . ($student->user?->name ?? '-'),
            ])
            ->toArray();

        $this->lecturers = LecturerProfile::query()
            ->with('user')
            ->orderBy('nidn')
            ->get()
            ->map(fn (LecturerProfile $lecturer) => [
                'id' => $lecturer->id,
                'label' => ($lecturer->nidn ?? $lecturer->nip ?? '-') . ' - ' . ($lecturer->user?->name ?? '-'),
            ])
            ->toArray();

        $this->academicYears = AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name'])
            ->map(fn (AcademicYear $year) => [
                'id' => $year->id,
                'label' => $year->name,
            ])
            ->toArray();
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.academic-advisor-assignments.index');
    }

    public function createAssignment(): void
    {
        $validated = $this->validate([
            'assignmentForm.student_profile_id' => 'required|integer|exists:student_profiles,id',
            'assignmentForm.lecturer_profile_id' => 'required|integer|exists:lecturer_profiles,id',
            'assignmentForm.academic_year_id' => [
                'nullable',
                'integer',
                'exists:academic_years,id',
                Rule::unique('academic_advisor_assignments', 'academic_year_id')
                    ->whereNull('deleted_at')
                    ->where('student_profile_id', $this->assignmentForm['student_profile_id']),
            ],
            'assignmentForm.start_date' => 'nullable|date',
            'assignmentForm.end_date' => 'nullable|date|after_or_equal:assignmentForm.start_date',
            'assignmentForm.is_active' => 'boolean',
            'assignmentForm.notes' => 'nullable|string',
        ]);

        AcademicAdvisorAssignment::query()->create([
            'student_profile_id' => $validated['assignmentForm']['student_profile_id'],
            'lecturer_profile_id' => $validated['assignmentForm']['lecturer_profile_id'],
            'academic_year_id' => $validated['assignmentForm']['academic_year_id'] ?: null,
            'start_date' => $validated['assignmentForm']['start_date'] ?: null,
            'end_date' => $validated['assignmentForm']['end_date'] ?: null,
            'is_active' => (bool) $validated['assignmentForm']['is_active'],
            'notes' => $validated['assignmentForm']['notes'] ?: null,
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Assignment dosen PA berhasil dibuat.');
        $this->redirectRoute('admin.academic.academic-advisor-assignments.index');
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

<div class="row">
    <div class="col-12">
        <x-alert />

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Form Assignment Dosen PA</h5>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="createAssignment">
                    <div class="row">
                        <div class="form-group col-lg-6 col-md-6 col-sm-12 mb-3">
                            <label>Mahasiswa <span class="text-danger">*</span></label>
                            <select class="form-control" wire:model.defer="assignmentForm.student_profile_id">
                                <option value="">Pilih Mahasiswa</option>
                                @foreach ($students as $student)
                                    <option value="{{ $student['id'] }}">{{ $student['label'] }}</option>
                                @endforeach
                            </select>
                            @error('assignmentForm.student_profile_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-lg-6 col-md-6 col-sm-12 mb-3">
                            <label>Dosen PA <span class="text-danger">*</span></label>
                            <select class="form-control" wire:model.defer="assignmentForm.lecturer_profile_id">
                                <option value="">Pilih Dosen</option>
                                @foreach ($lecturers as $lecturer)
                                    <option value="{{ $lecturer['id'] }}">{{ $lecturer['label'] }}</option>
                                @endforeach
                            </select>
                            @error('assignmentForm.lecturer_profile_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-lg-4 col-md-6 col-sm-12 mb-3">
                            <label>Tahun Akademik</label>
                            <select class="form-control" wire:model.defer="assignmentForm.academic_year_id">
                                <option value="">Umum (Semua Tahun)</option>
                                @foreach ($academicYears as $year)
                                    <option value="{{ $year['id'] }}">{{ $year['label'] }}</option>
                                @endforeach
                            </select>
                            @error('assignmentForm.academic_year_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-lg-4 col-md-6 col-sm-12 mb-3">
                            <label>Tanggal Mulai</label>
                            <input type="date" class="form-control" wire:model.defer="assignmentForm.start_date">
                            @error('assignmentForm.start_date') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-lg-4 col-md-6 col-sm-12 mb-3">
                            <label>Tanggal Selesai</label>
                            <input type="date" class="form-control" wire:model.defer="assignmentForm.end_date">
                            @error('assignmentForm.end_date') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-12 mb-3">
                            <label>Catatan</label>
                            <textarea rows="2" class="form-control" wire:model.defer="assignmentForm.notes"></textarea>
                            @error('assignmentForm.notes') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-12 mb-3">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" wire:model.defer="assignmentForm.is_active">
                                <span class="form-check-label">Aktif</span>
                            </label>
                        </div>

                        <div class="form-group col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Simpan
                            </button>
                            <button type="button" class="btn btn-secondary" wire:click="cancel">
                                <i class="fas fa-times me-1"></i> Batal
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
