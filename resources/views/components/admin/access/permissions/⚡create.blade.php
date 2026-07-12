<?php

use App\Models\Access\Permission;
use App\Models\Access\Role;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

new class extends Component
{
    public array $permissionForm = [];

    public array $availableRoles = [];

    public array $selectedRoles = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.access.permissions.index');
    }

    public function mount(): void
    {
        $this->permissionForm = [
            'name' => '',
            'guard_name' => 'web',
        ];

        $this->availableRoles = Role::query()->pluck('name', 'id')->toArray();
    }

    public function createPermission(): void
    {
        $this->validate([
            'permissionForm.name' => 'required|string|max:255|unique:permissions,name,NULL,id,guard_name,' . $this->permissionForm['guard_name'],
            'permissionForm.guard_name' => 'required|string|max:255',
            'selectedRoles' => 'nullable|array',
            'selectedRoles.*' => 'exists:roles,id',
        ]);

        $permission = Permission::create([
            'name' => $this->permissionForm['name'],
            'guard_name' => $this->permissionForm['guard_name'],
        ]);

        if (! empty($this->selectedRoles)) {
            $permission->syncRoles(Role::whereIn('id', $this->selectedRoles)->get());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        session()->flash('success', 'Permission "' . $this->permissionForm['name'] . '" berhasil ditambahkan.');
        $this->redirectRoute('admin.access.permissions.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Manajemen Akses',
            'pages' => 'Tambah Permission',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.access.header
        title="Tambah Permission Baru"
        description="Tambahkan permission baru, tentukan guard, lalu pasangkan ke role yang relevan agar kontrol akses tetap rapi dan mudah dipelihara."
        icon="shield-halved"
    >
        <a href="{{ route('admin.access.permissions.index') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </a>
    </x-admin.access.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-3 p-md-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-key fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Permission</h4>
                            <div class="text-muted small">Gunakan format yang konsisten agar permission mudah dicari dan dipetakan.</div>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <form wire:submit.prevent="createPermission" class="row g-3">
                        <div class="col-lg-6 col-md-6 col-sm-12">
                            <label for="name" class="form-label fw-semibold text-dark">Nama Permission <span class="text-danger">*</span></label>
                            <input type="text" id="name" class="form-control rounded-3" placeholder="Contoh: user.viewAny" wire:model.defer="permissionForm.name">
                            @error('permissionForm.name') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                            <small class="text-muted d-block mt-1">Format umum: resource.action</small>
                        </div>

                        <div class="col-lg-6 col-md-6 col-sm-12">
                            <label for="guard_name" class="form-label fw-semibold text-dark">Guard Name <span class="text-danger">*</span></label>
                            <select id="guard_name" class="form-select rounded-3" wire:model.defer="permissionForm.guard_name">
                                <option value="web">web</option>
                                <option value="api">api</option>
                            </select>
                            @error('permissionForm.guard_name') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-12">
                            <label for="roles" class="form-label fw-semibold text-dark">Assign ke Role</label>
                            <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                                <div wire:ignore>
                                    <select id="roles-select" class="form-control" multiple>
                                        @foreach($availableRoles as $roleId => $roleName)
                                            <option value="{{ $roleId }}">{{ ucfirst($roleName) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @error('selectedRoles') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                            @error('selectedRoles.*') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                            <small class="text-muted d-block mt-1">Pilih role yang akan otomatis mendapatkan permission ini.</small>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2 pt-3 border-top">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-semibold" wire:click="cancel">Batal</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2" wire:loading.attr="disabled">
                                <i class="fas fa-save"></i> <span>Simpan Permission</span>
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
                            <div class="text-muted small">Buat permission yang jelas, spesifik, dan konsisten dengan resource lain.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pakai pola <strong>resource.action</strong> agar mudah dibaca.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Guard harus sesuai alur autentikasi yang dipakai modul.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Role assignment langsung mengurangi kerja konfigurasi manual.</span></li>
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
                selectId: 'roles-select',
                property: 'selectedRoles',
                placeholder: 'Pilih role',
                removeButtonTitle: 'Hapus role',
            });
        });
    </script>
@endpush
