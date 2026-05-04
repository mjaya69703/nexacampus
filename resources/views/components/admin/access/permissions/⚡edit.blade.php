<?php

use Livewire\Component;
use App\Models\Access\Permission;
use App\Models\Access\Role;
use Spatie\Permission\PermissionRegistrar;

new class extends Component {
    public $permissionId;
    public $permissionForm = [];
    public array $availableRoles = [];
    public array $selectedRoles = [];

    public function cancel()
    {
        $this->redirectRoute('admin.access.permissions.index');
    }

    public function mount($id)
    {
        $this->permissionId = $id;
        $permission = Permission::findOrFail($id);
        $this->permissionForm = $permission->toArray();
        $this->availableRoles = Role::query()->pluck('name')->toArray();
        $this->selectedRoles = $permission->roles->pluck('name')->toArray();
    }

    public function updatePermission()
    {
        $this->validate([
            'permissionForm.name' => 'required|string|max:255',
            'permissionForm.guard_name' => 'required|string|max:255',
            'selectedRoles' => 'nullable|array',
            'selectedRoles.*' => 'string|exists:roles,name',
        ]);

        $permission = Permission::findOrFail($this->permissionId);
        $permission->name = $this->permissionForm['name'];
        $permission->guard_name = $this->permissionForm['guard_name'];
        $permission->save();
        $permission->syncRoles($this->selectedRoles);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        session()->flash('success', 'Permission berhasil diperbarui.');
        $this->redirectRoute('admin.access.permissions.index');
    }

    public function render()
    {
        $data = [
            'menus' => 'Permission Management',
            'pages' => 'Edit Permission',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Edit Permission {{ $this->permissionForm['name'] }} </h3>
        </div>
        <div class="card-body row">
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="name">Nama Permission</label>
                <input type="text" id="name" class="form-control" wire:model.defer="permissionForm.name">
                @error('permissionForm.name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="guard_name">Guard Name</label>
                <input type="text" id="guard_name" class="form-control" wire:model.defer="permissionForm.guard_name">
                @error('permissionForm.guard_name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-12 mt-2">
                <label for="roles">Roles</label>
                <div wire:ignore>
                    <select id="roles-select" class="form-control" multiple>
                        @foreach($availableRoles as $roleName)
                            <option value="{{ $roleName }}" @selected(in_array($roleName, $selectedRoles, true))>
                                {{ ucfirst($roleName) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @error('selectedRoles')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
                @error('selectedRoles.*')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
                <button class="btn btn-primary" wire:click="updatePermission">
                    <i class="fas fa-save me-2"></i> Simpan Perubahan</button>
                <button class="btn btn-secondary" wire:click="cancel">
                    <i class="fas fa-times me-2"></i> Batal</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('livewire:initialized', () => {
            window.initLivewireTomSelect({
                selectId: 'roles-select',
                property: 'selectedRoles',
                placeholder: 'Pilih role',
                removeButtonTitle: 'Hapus role'
            });
        });
    </script>
@endpush
