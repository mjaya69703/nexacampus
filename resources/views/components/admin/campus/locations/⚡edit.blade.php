<?php

use App\Models\Campus\CampusLocation;
use Livewire\Component;

new class extends Component
{
    public CampusLocation $location;
    public array $form = [];

    public function mount(int $id): void
    {
        $this->location = CampusLocation::findOrFail($id);
        $this->form = [
            'name' => $this->location->name,
            'code' => $this->location->code,
            'address' => $this->location->address,
            'phone' => $this->location->phone,
            'email' => $this->location->email,
            'latitude' => $this->location->latitude,
            'longitude' => $this->location->longitude,
            'radius_meters' => $this->location->radius_meters,
            'is_main' => $this->location->is_main,
            'is_active' => $this->location->is_active,
            'desc' => $this->location->desc,
        ];
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.campus.locations.index');
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.name' => 'required|string|max:255',
            'form.code' => 'required|string|max:50|unique:campus_locations,code,' . $this->location->id,
            'form.address' => 'nullable|string',
            'form.phone' => 'nullable|string|max:50',
            'form.email' => 'nullable|email|max:255',
            'form.latitude' => 'nullable|numeric',
            'form.longitude' => 'nullable|numeric',
            'form.radius_meters' => 'required|integer|min:10',
            'form.is_main' => 'boolean',
            'form.is_active' => 'boolean',
            'form.desc' => 'nullable|string',
        ]);

        $payload = $validated['form'];
        $payload['updated_by'] = auth()->id();
        $this->location->update($payload);

        session()->flash('success', 'Lokasi kampus berhasil diperbarui!');
        $this->redirectRoute('admin.campus.locations.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Campus Management',
            'pages' => 'Edit Lokasi Kampus',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.campus.header
        title="Edit Lokasi Kampus"
        description="Perbarui informasi lokasi kampus, alamat, kontak, dan koordinat GPS geofencing."
        icon="location-dot"
    >
        <a href="{{ route('admin.campus.locations.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.campus.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-edit fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Edit Lokasi: {{ $location->name }}</h4>
                            <div class="text-muted small">Perbarui data lokasi kampus dan simpan perubahan.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form wire:submit.prevent="save">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Nama Lokasi Kampus <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3 @error('form.name') is-invalid @enderror" wire:model="form.name">
                                @error('form.name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Kode Lokasi <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3 @error('form.code') is-invalid @enderror" wire:model="form.code">
                                @error('form.code') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Alamat Lengkap</label>
                                <textarea class="form-control rounded-3 @error('form.address') is-invalid @enderror" wire:model="form.address" rows="2"></textarea>
                                @error('form.address') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Telepon</label>
                                <input type="text" class="form-control rounded-3 @error('form.phone') is-invalid @enderror" wire:model="form.phone">
                                @error('form.phone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Email Kontak</label>
                                <input type="email" class="form-control rounded-3 @error('form.email') is-invalid @enderror" wire:model="form.email">
                                @error('form.email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-4">
                                <label class="form-label fw-semibold">Latitude GPS</label>
                                <input type="number" step="any" class="form-control rounded-3 @error('form.latitude') is-invalid @enderror" wire:model="form.latitude">
                                @error('form.latitude') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-4">
                                <label class="form-label fw-semibold">Longitude GPS</label>
                                <input type="number" step="any" class="form-control rounded-3 @error('form.longitude') is-invalid @enderror" wire:model="form.longitude">
                                @error('form.longitude') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-4">
                                <label class="form-label fw-semibold">Radius Geofence (Meter)</label>
                                <input type="number" class="form-control rounded-3 @error('form.radius_meters') is-invalid @enderror" wire:model="form.radius_meters">
                                @error('form.radius_meters') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="is_main" wire:model="form.is_main">
                                        <label class="form-check-label fw-semibold" for="is_main">Tandai Sebagai Kampus Utama</label>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="is_active" wire:model="form.is_active">
                                        <label class="form-check-label fw-semibold" for="is_active">Status Lokasi Aktif</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="cancel">
                                <i class="fa fa-times me-2"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="fa fa-save me-2"></i> Simpan Perubahan
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
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-circle-info"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Status Lokasi</h5>
                            <div class="text-muted small">Informasi status kampus.</div>
                        </div>
                    </div>

                    <div class="border rounded-3 p-3 bg-white mb-3">
                        <div class="text-muted small mb-1">Kampus Utama</div>
                        <div class="fw-bold text-dark">{{ $location->is_main ? 'Ya (Utama)' : 'Kampus Cabang' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
