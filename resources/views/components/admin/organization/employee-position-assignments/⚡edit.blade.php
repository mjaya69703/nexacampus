<?php

use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Organization\EmployeePositionAssignment;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\OrganizationalPosition;
use App\Models\Organization\WorkUnit;
use App\Support\Organization\EmployeePositionAssignmentService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public EmployeePositionAssignment $assignment;
    public array $form = [];
    public ?int $selectedEmployeeProfileId = null;
    public string $employeeSearch = '';

    public function mount(int $id): void
    {
        $this->assignment = EmployeePositionAssignment::with(['employeeProfile.user', 'position'])->findOrFail($id);
        $this->selectedEmployeeProfileId = $this->assignment->employee_profile_id;
        $this->form = [
            'organizational_position_id' => (string) $this->assignment->organizational_position_id,
            'faculty_id' => $this->assignment->faculty_id ? (string) $this->assignment->faculty_id : '',
            'study_program_id' => $this->assignment->study_program_id ? (string) $this->assignment->study_program_id : '',
            'work_unit_id' => $this->assignment->work_unit_id ? (string) $this->assignment->work_unit_id : '',
            'starts_at' => $this->assignment->starts_at?->format('Y-m-d') ?? '',
            'ends_at' => $this->assignment->ends_at?->format('Y-m-d') ?? '',
            'is_primary' => (bool) $this->assignment->is_primary,
            'is_active' => (bool) $this->assignment->is_active,
            'notes' => $this->assignment->notes,
        ];
    }

    public function updatedFormOrganizationalPositionId(): void
    {
        $this->form['faculty_id'] = '';
        $this->form['study_program_id'] = '';
        $this->form['work_unit_id'] = '';
    }

    public function selectEmployee(int $employeeProfileId): void
    {
        $this->selectedEmployeeProfileId = $employeeProfileId;
        $this->employeeSearch = '';
    }

    public function clearEmployee(): void
    {
        $this->selectedEmployeeProfileId = null;
    }

    public function save(EmployeePositionAssignmentService $service): void
    {
        $validated = $this->validate($this->rules());
        $payload = $this->payloadForScope($validated);

        $service->update($this->assignment, array_merge($payload, [
            'updated_by' => auth()->id(),
        ]));

        session()->flash('success', 'Penugasan jabatan berhasil diperbarui.');
        $this->redirectRoute('admin.organization.employee-position-assignments.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.employee-position-assignments.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Edit Penugasan Jabatan',
        ]);
    }

    public function getSelectedEmployeeProperty(): ?EmployeeProfile
    {
        return $this->selectedEmployeeProfileId
            ? EmployeeProfile::with('user')->find($this->selectedEmployeeProfileId)
            : null;
    }

    public function getSelectedPositionProperty(): ?OrganizationalPosition
    {
        return filled($this->form['organizational_position_id'] ?? null)
            ? OrganizationalPosition::find($this->form['organizational_position_id'])
            : null;
    }

    public function getSearchableEmployeesProperty()
    {
        return EmployeeProfile::query()
            ->where('is_active', true)
            ->with('user')
            ->when(filled($this->employeeSearch), function ($query) {
                $search = '%'.$this->employeeSearch.'%';
                $query->where(function ($query) use ($search) {
                    $query->whereHas('user', function ($query) use ($search) {
                        $query->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhere('email', 'like', $search)
                            ->orWhere('username', 'like', $search)
                            ->orWhere('code', 'like', $search)
                            ->orWhere('identity_number', 'like', $search);
                    })->orWhere('employee_number', 'like', $search);
                });
            })
            ->limit(12)
            ->get();
    }

    public function getPositionsProperty()
    {
        return OrganizationalPosition::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'scope_type']);
    }

    public function getFacultiesProperty()
    {
        return Faculty::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }

    public function getStudyProgramsProperty()
    {
        return StudyProgram::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }

    public function getWorkUnitsProperty()
    {
        return WorkUnit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }

    private function rules(): array
    {
        return [
            'selectedEmployeeProfileId' => ['required', 'integer', 'exists:employee_profiles,id'],
            'form.organizational_position_id' => ['required', 'integer', 'exists:organizational_positions,id'],
            'form.faculty_id' => ['nullable', 'exists:faculties,id'],
            'form.study_program_id' => ['nullable', 'exists:study_programs,id'],
            'form.work_unit_id' => ['nullable', 'exists:work_units,id'],
            'form.starts_at' => ['nullable', 'date'],
            'form.ends_at' => ['nullable', 'date', 'after_or_equal:form.starts_at'],
            'form.is_primary' => ['boolean'],
            'form.is_active' => ['boolean'],
            'form.notes' => ['nullable', 'string'],
        ];
    }

    private function payloadForScope(array $validated): array
    {
        $position = OrganizationalPosition::findOrFail($validated['form']['organizational_position_id']);
        $form = $validated['form'];
        $errors = [];

        if ($position->scope_type === 'faculty' && blank($form['faculty_id'] ?? null)) {
            $errors['form.faculty_id'] = 'Scope fakultas wajib dipilih untuk jabatan ini.';
        }

        if ($position->scope_type === 'study_program' && blank($form['study_program_id'] ?? null)) {
            $errors['form.study_program_id'] = 'Scope program studi wajib dipilih untuk jabatan ini.';
        }

        if ($position->scope_type === 'work_unit' && blank($form['work_unit_id'] ?? null)) {
            $errors['form.work_unit_id'] = 'Scope unit kerja wajib dipilih untuk jabatan ini.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'employee_profile_id' => $validated['selectedEmployeeProfileId'],
            'organizational_position_id' => $position->id,
            'faculty_id' => $position->scope_type === 'faculty' ? ($form['faculty_id'] ?: null) : null,
            'study_program_id' => $position->scope_type === 'study_program' ? ($form['study_program_id'] ?: null) : null,
            'work_unit_id' => $position->scope_type === 'work_unit' ? ($form['work_unit_id'] ?: null) : null,
            'starts_at' => $form['starts_at'] ?: null,
            'ends_at' => $form['ends_at'] ?: null,
            'is_primary' => (bool) $form['is_primary'],
            'is_active' => (bool) $form['is_active'],
            'notes' => $form['notes'] ?: null,
        ];
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Edit Penugasan Jabatan</h3>
            <a href="{{ route('admin.organization.employee-position-assignments.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
        <div class="card-body">
            @include('components.admin.organization.employee-position-assignments._form')
        </div>
    </div>
</div>
