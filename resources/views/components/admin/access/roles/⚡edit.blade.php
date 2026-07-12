<?php

use App\Models\Access\Permission;
use App\Models\Access\Role;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

new class extends Component
{
    public $roleId;

    public array $roleForm = [];

    public array $availablePermissions = [];

    public array $selectedPermissions = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.access.roles.index');
    }

    public function mount($id): void
    {
        $this->roleId = $id;

        $role = Role::findOrFail($id);

        $this->roleForm = $role->toArray();
        $this->availablePermissions = Permission::query()
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($permission) => ucfirst(str($permission->name)->before('.')->replace('-', ' ')->toString()))
            ->map(fn ($permissions) => $permissions->pluck('name', 'id')->values()->toArray())
            ->toArray();
        $this->selectedPermissions = $role->permissions->pluck('id')->toArray();
    }

    public function updateRole(): void
    {
        $this->validate([
            'roleForm.name' => 'required|string|max:255|unique:roles,name,' . $this->roleId . ',id,guard_name,' . $this->roleForm['guard_name'],
            'roleForm.guard_name' => 'required|string|max:255',
            'selectedPermissions' => 'nullable|array',
            'selectedPermissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::findOrFail($this->roleId);
        $role->name = $this->roleForm['name'];
        $role->guard_name = $this->roleForm['guard_name'];
        $role->save();

        $role->syncPermissions(Permission::whereIn('id', $this->selectedPermissions)->get());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        session()->flash('success', 'Peran "' . $this->roleForm['name'] . '" berhasil diperbarui.');
        $this->redirectRoute('admin.access.roles.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Manajemen Akses',
            'pages' => 'Edit Peran',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.access.header
        title="Edit Peran: {{ $roleForm['name'] ?? '' }}"
        description="Perbarui nama peran, guard, dan permission yang melekat tanpa mengganggu struktur akses yang sudah ada."
        icon="user-shield"
    >
        <a href="{{ route('admin.access.roles.index') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </a>
    </x-admin.access.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-3 p-md-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-user-shield fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Peran</h4>
                            <div class="text-muted small">Sesuaikan role agar kebutuhan akses user tetap sesuai peran organisasinya.</div>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <form wire:submit.prevent="updateRole" class="row g-3">
                        <div class="col-lg-6 col-md-6 col-sm-12">
                            <label for="name" class="form-label fw-semibold text-dark">Nama Peran <span class="text-danger">*</span></label>
                            <input type="text" id="name" class="form-control rounded-3" wire:model.defer="roleForm.name">
                            @error('roleForm.name') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-lg-6 col-md-6 col-sm-12">
                            <label for="guard_name" class="form-label fw-semibold text-dark">Guard Name <span class="text-danger">*</span></label>
                            <select id="guard_name" class="form-select rounded-3" wire:model.defer="roleForm.guard_name">
                                <option value="web">web</option>
                                <option value="api">api</option>
                            </select>
                            @error('roleForm.guard_name') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-12">
                            <label for="permissions" class="form-label fw-semibold text-dark">Assign Permissions</label>
                            <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                                <div wire:ignore>
                                    <select id="permissions-select" class="form-control" multiple>
                                        @foreach($availablePermissions as $group => $permissions)
                                            <optgroup label="{{ $group }}">
                                                @foreach($permissions as $permissionId => $permissionName)
                                                    <option value="{{ $permissionId }}" @selected(in_array($permissionId, $selectedPermissions, true))>
                                                        {{ $permissionName }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @error('selectedPermissions') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                            @error('selectedPermissions.*') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                            <small class="text-muted d-block mt-1">Gunakan grouping permission agar lebih mudah dipilih.</small>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2 pt-3 border-top">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-semibold" wire:click="cancel">Batal</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2" wire:loading.attr="disabled">
                                <i class="fas fa-save"></i> <span>Simpan Perubahan</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-circle-info"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Panduan Singkat</h5>
                            <div class="text-muted small">Role yang baik memudahkan assignment user dan membuat audit akses lebih jelas.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Jangan tumpuk permission yang tidak benar-benar dibutuhkan.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Gunakan nama role yang dikenal oleh tim operasional.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Jaga guard konsisten agar integrasi login tetap aman.</span></li>
                    </ul>
                </div>
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
                placeholder: 'Pilih permissions',
                removeButtonTitle: 'Hapus permission',
            });
        });
    </script>
@endpush
