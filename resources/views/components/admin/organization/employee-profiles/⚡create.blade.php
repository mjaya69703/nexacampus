<?php

use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\WorkUnit;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = [];
    public ?int $selectedUserId = null;
    public string $userSearch = '';

    public function mount(): void
    {
        $this->form = [
            'employee_number' => '',
            'employment_type' => 'staff',
            'employment_status' => 'active',
            'join_date' => '',
            'end_date' => '',
            'primary_work_unit_id' => '',
            'notes' => '',
            'is_active' => true,
        ];
    }

    public function selectUser(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->userSearch = '';
    }

    public function clearUser(): void
    {
        $this->selectedUserId = null;
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        EmployeeProfile::create(array_merge($validated['form'], [
            'user_id' => $validated['selectedUserId'],
            'employee_number' => $validated['form']['employee_number'] ?: null,
            'join_date' => $validated['form']['join_date'] ?: null,
            'end_date' => $validated['form']['end_date'] ?: null,
            'primary_work_unit_id' => $validated['form']['primary_work_unit_id'] ?: null,
            'notes' => $validated['form']['notes'] ?: null,
            'created_by' => auth()->id(),
        ]));

        session()->flash('success', 'Profil pegawai berhasil dibuat.');
        $this->redirectRoute('admin.organization.employee-profiles.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.employee-profiles.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Tambah Pegawai',
        ]);
    }

    public function getSelectedUserProperty(): ?User
    {
        return $this->selectedUserId ? User::find($this->selectedUserId) : null;
    }

    public function getSearchableUsersProperty()
    {
        $usedUserIds = EmployeeProfile::query()->pluck('user_id')->all();

        return User::query()
            ->where('is_active', true)
            ->when($usedUserIds !== [], fn ($query) => $query->whereNotIn('id', $usedUserIds))
            ->when(filled($this->userSearch), function ($query) {
                $search = '%'.$this->userSearch.'%';
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('username', 'like', $search)
                        ->orWhere('code', 'like', $search)
                        ->orWhere('identity_number', 'like', $search);
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(12)
            ->get(['id', 'first_name', 'last_name', 'email', 'username', 'code', 'identity_number']);
    }

    public function getWorkUnitsProperty()
    {
        return WorkUnit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }

    private function rules(): array
    {
        return [
            'selectedUserId' => ['required', 'integer', 'exists:users,id', Rule::unique('employee_profiles', 'user_id')],
            'form.employee_number' => ['nullable', 'string', 'max:50', Rule::unique('employee_profiles', 'employee_number')],
            'form.employment_type' => ['required', 'string', 'in:lecturer,tendik,admin_staff,contract,guest,staff'],
            'form.employment_status' => ['required', 'string', 'in:active,inactive,suspended,resigned'],
            'form.join_date' => ['nullable', 'date'],
            'form.end_date' => ['nullable', 'date', 'after_or_equal:form.join_date'],
            'form.primary_work_unit_id' => ['nullable', 'exists:work_units,id'],
            'form.notes' => ['nullable', 'string'],
            'form.is_active' => ['boolean'],
        ];
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Tambah Pegawai</h3>
            <a href="{{ route('admin.organization.employee-profiles.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
        <div class="card-body">
            @include('components.admin.organization.employee-profiles._form')
        </div>
    </div>
</div>
