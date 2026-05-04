<?php

use Livewire\Component;
use App\Models\Access\Permission;
use App\Models\Access\Role;
use Spatie\Permission\PermissionRegistrar;

new class extends Component {
    public $roleId;
    public $roleForm = [];
    public array $availablePermissions = [];
    public array $selectedPermissions = [];
    

    public function cancel()
    {
        $this->redirectRoute('admin.access.roles.index');
    }

    public function mount($id)
    {
        $this->roleId = $id;
        $role = Role::findOrFail($id);

        $this->roleForm = $role->toArray();

        $this->availablePermissions = Permission::query()
            ->orderBy('name')
            ->get()
            ->groupBy(function ($permission) {
                return ucfirst(str($permission->name)->before('.')->toString());
            })
            ->map(function ($permissions) {
                return $permissions->pluck('name')->values()->toArray();
            })
            ->toArray();

        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
    }

    public function updateRole()
    {
        $this->validate([
            'roleForm.name' => 'required|string|max:255',
            'roleForm.guard_name' => 'required|string|max:255',
            'selectedPermissions' => 'nullable|array',
            'selectedPermissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::findOrFail($this->roleId);
        $role->name = $this->roleForm['name'];
        $role->guard_name = $this->roleForm['guard_name'];
        $role->save();
        $role->syncPermissions($this->selectedPermissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        session()->flash('success', 'Peran berhasil diperbarui.');
        $this->redirectRoute('admin.access.roles.index');
    }

    public function render()
    {
        $data = [
            'menus' => 'Role Management',
            'pages' => 'Edit Peran ',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Edit Peran {{ $this->roleForm['name'] }} </h3>
        </div>
        <div class="card-body row">
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="name">Nama Peran</label>
                <input type="text" id="name" class="form-control" wire:model.defer="roleForm.name">
                @error('roleForm.name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="guard_name">Guard Name</label>
                <input type="text" id="guard_name" class="form-control" readonly wire:model.defer="roleForm.guard_name">
                @error('roleForm.guard_name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-12 mt-2">
                <label for="permissions">Permissions</label>
                <div wire:ignore>
                    <select id="permissions-select" class="form-control" multiple>
                        @foreach($availablePermissions as $group => $permissions)
                            <optgroup label="{{ $group }}">
                                @foreach($permissions as $permissionName)
                                    <option value="{{ $permissionName }}" @selected(in_array($permissionName, $selectedPermissions, true))>
                                        {{ $permissionName }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                @error('selectedPermissions')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
                @error('selectedPermissions.*')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
                <button class="btn btn-primary" wire:click="updateRole">
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
                selectId: 'permissions-select',
                property: 'selectedPermissions',
                placeholder: 'Pilih permission',
                removeButtonTitle: 'Hapus permission'
            });
        });
    </script>
@endpush
