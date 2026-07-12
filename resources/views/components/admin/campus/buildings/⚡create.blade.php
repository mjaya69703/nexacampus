<?php

use App\Models\Campus\Building;
use Livewire\Component;

new class extends Component
{
    public array $buildingForm = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.campus.buildings.index');
    }

    public function mount(): void
    {
        $this->buildingForm = [
            'name' => '',
            'code' => '',
            'address' => '',
            'floor_count' => '',
            'is_active' => true,
            'desc' => '',
        ];
    }

    public function createBuilding(): void
    {
        $validatedData = $this->validate([
            'buildingForm.name' => 'required|string|max:255',
            'buildingForm.code' => 'nullable|string|max:50|unique:buildings,code',
            'buildingForm.address' => 'nullable|string',
            'buildingForm.floor_count' => 'nullable|integer|min:1',
            'buildingForm.is_active' => 'boolean',
            'buildingForm.desc' => 'nullable|string',
        ]);

        $payload = [
            'name' => $validatedData['buildingForm']['name'],
            'code' => $validatedData['buildingForm']['code'] ?: null,
            'address' => $validatedData['buildingForm']['address'] ?: null,
            'floor_count' => $validatedData['buildingForm']['floor_count'] ?: null,
            'is_active' => (bool) $validatedData['buildingForm']['is_active'],
            'desc' => $validatedData['buildingForm']['desc'] ?: null,
            'created_by' => auth()->id(),
        ];

        Building::create($payload);

        session()->flash('success', 'Gedung berhasil ditambahkan.');
        $this->redirectRoute('admin.campus.buildings.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Campus Management',
            'pages' => 'Tambah Gedung',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.campus.header
        title="Tambah Gedung Kampus"
        description="Masukkan data gedung baru beserta kode, alamat, jumlah lantai, dan deskripsi singkat agar inventaris fasilitas kampus tetap rapi."
        icon="building"
    >
        <a href="{{ route('admin.campus.buildings.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.campus.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-building fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Gedung Baru</h4>
                            <div class="text-muted small">Lengkapi data gedung secara singkat dan konsisten.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label for="name" class="form-label fw-semibold">Nama Gedung <span class="text-danger">*</span></label>
                            <input type="text" id="name" class="form-control rounded-3" placeholder="Contoh: Gedung Rektorat" wire:model.defer="buildingForm.name">
                            @error('buildingForm.name')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-lg-6">
                            <label for="code" class="form-label fw-semibold">Kode Gedung</label>
                            <input type="text" id="code" class="form-control rounded-3" placeholder="Contoh: BLD-RT" wire:model.defer="buildingForm.code">
                            @error('buildingForm.code')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-lg-6">
                            <label for="address" class="form-label fw-semibold">Alamat</label>
                            <input type="text" id="address" class="form-control rounded-3" placeholder="Alamat gedung" wire:model.defer="buildingForm.address">
                            @error('buildingForm.address')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-lg-6">
                            <label for="floor_count" class="form-label fw-semibold">Jumlah Lantai</label>
                            <input type="number" id="floor_count" class="form-control rounded-3" placeholder="Contoh: 5" wire:model.defer="buildingForm.floor_count" min="1">
                            @error('buildingForm.floor_count')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label for="desc" class="form-label fw-semibold">Deskripsi</label>
                            <textarea id="desc" class="form-control rounded-3" placeholder="Deskripsi singkat gedung" wire:model.defer="buildingForm.desc" rows="4"></textarea>
                            @error('buildingForm.desc')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="buildingForm.is_active">
                                    <label class="form-check-label fw-semibold" for="is_active">Gedung aktif dan siap dipakai</label>
                                </div>
                                <div class="text-muted small mt-2">Gedung aktif akan tampil di pilihan ruangan dan proses penjadwalan.</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="cancel">
                            <i class="fa fa-times me-2"></i> Batal
                        </button>
                        <button type="button" class="btn btn-primary rounded-pill px-4" wire:click="createBuilding">
                            <i class="fa fa-save me-2"></i> Simpan Gedung
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-circle-info"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Panduan Singkat</h5>
                            <div class="text-muted small">Isi data yang benar sejak awal agar ruangan dan jadwal tidak bermasalah.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Gunakan kode gedung yang konsisten dengan papan penunjuk fisik.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Jumlah lantai membantu inventaris fasilitas dan navigasi kampus.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Menonaktifkan gedung akan menonaktifkan seluruh ruangan di dalamnya.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status awal</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Aktif</div>
                    <p class="text-muted small mb-0">Gedung baru langsung siap dipakai kecuali Anda menonaktifkannya sebelum simpan.</p>
                </div>
            </div>
        </div>
        </div>
</div>
