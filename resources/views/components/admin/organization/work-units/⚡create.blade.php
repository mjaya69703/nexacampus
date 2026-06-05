<?php

use App\Models\Organization\WorkUnit;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = [];
    public array $members = [];
    public array $selectedUserIds = [];
    public string $memberSearch = '';
    public string $bulkPosition = 'member';
    public bool $bulkIsActive = true;

    public function mount(): void
    {
        $this->form = [
            'name' => '',
            'code' => '',
            'description' => '',
            'is_active' => true,
        ];
    }

    public function addSelectedMembers(): void
    {
        $selectedIds = collect($this->selectedUserIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            session()->flash('error', 'Pilih minimal satu user terlebih dahulu.');

            return;
        }

        $existingIds = collect($this->members)
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($selectedIds as $userId) {
            if (in_array($userId, $existingIds, true)) {
                continue;
            }

            $this->members[] = [
                'user_id' => (string) $userId,
                'position' => $this->bulkPosition,
                'is_active' => $this->bulkIsActive,
            ];
        }

        $this->selectedUserIds = [];
        $this->memberSearch = '';
    }

    public function removeMember(int $index): void
    {
        unset($this->members[$index]);
        $this->members = array_values($this->members);
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.work-units.index');
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        $unit = WorkUnit::create([
            'name' => $validated['form']['name'],
            'code' => strtoupper($validated['form']['code']),
            'description' => $validated['form']['description'] ?: null,
            'is_active' => (bool) $validated['form']['is_active'],
            'created_by' => auth()->id(),
        ]);

        $this->syncMembers($unit, $validated['members'] ?? []);

        session()->flash('success', 'Unit kerja berhasil dibuat.');
        $this->redirectRoute('admin.organization.work-units.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Buat Unit Kerja',
        ]);
    }

    public function getSearchableUsersProperty()
    {
        $existingIds = collect($this->members)
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        return User::query()
            ->where('is_active', true)
            ->when($existingIds !== [], fn ($query) => $query->whereNotIn('id', $existingIds))
            ->when(filled($this->memberSearch), function ($query) {
                $search = '%'.$this->memberSearch.'%';

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
            ->get(['id', 'first_name', 'last_name', 'email', 'username', 'code']);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:30', Rule::unique('work_units', 'code')],
            'form.description' => ['nullable', 'string'],
            'form.is_active' => ['boolean'],
            'members' => ['array'],
            'members.*.user_id' => ['required', 'integer', 'exists:users,id', 'distinct'],
            'members.*.position' => ['required', 'string', 'in:member,coordinator,head'],
            'members.*.is_active' => ['boolean'],
        ];
    }

    private function syncMembers(WorkUnit $unit, array $members): void
    {
        $payload = collect($members)
            ->filter(fn (array $member) => filled($member['user_id'] ?? null))
            ->mapWithKeys(fn (array $member) => [
                (int) $member['user_id'] => [
                    'position' => $member['position'] ?? 'member',
                    'is_active' => (bool) ($member['is_active'] ?? true),
                ],
            ])
            ->all();

        $unit->members()->sync($payload);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Buat Unit Kerja</h3>
            <a href="{{ route('admin.organization.work-units.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
        <div class="card-body">
            @include('components.admin.organization.work-units._form')
        </div>
    </div>
</div>
