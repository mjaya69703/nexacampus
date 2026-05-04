<?php

use App\Models\Campus\Building;
use Livewire\Component;
use Illuminate\Validation\Rule;

new class extends Component
{
    public int $buildingId;
    public array $buildingForm = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.campus.buildings.index');
    }

    public function mount($id): void
    {
        $building = Building::findOrFail($id);
        $this->buildingId = (int) $id;

        $this->buildingForm = [
            'name' => $building->name,
            'code' => $building->code,
            'address' => $building->address,
            'floor_count' => $building->floor_count,
            'is_active' => (bool) $building->is_active,
            'desc' => $building->desc,
        ];
    }

    public function updateBuilding(): void
    {
        $validatedData = $this->validate([
            'buildingForm.name' => 'required|string|max:255',
            'buildingForm.code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('buildings', 'code')->ignore($this->buildingId),
            ],
            'buildingForm.address' => 'nullable|string',
            'buildingForm.floor_count' => 'nullable|integer|min:1',
            'buildingForm.is_active' => 'boolean',
            'buildingForm.desc' => 'nullable|string',
        ]);

        $building = Building::findOrFail($this->buildingId);

        $payload = [
            'name' => $validatedData['buildingForm']['name'],
            'code' => $validatedData['buildingForm']['code'] ?: null,
            'address' => $validatedData['buildingForm']['address'] ?: null,
            'floor_count' => $validatedData['buildingForm']['floor_count'] ?: null,
            'is_active' => (bool) $validatedData['buildingForm']['is_active'],
            'desc' => $validatedData['buildingForm']['desc'] ?: null,
            'updated_by' => auth()->id(),
        ];

        $building->update($payload);

        session()->flash('success', 'Gedung berhasil diperbarui.');
        $this->redirectRoute('admin.campus.buildings.index');
    }

    public function render()
    {
        $data = [
            'menus' => 'Campus Management',
            'pages' => 'Edit Gedung',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Edit Gedung {{ $buildingForm['name'] ?? '' }}</h3>
        </div>
        <div class="card-body row">
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="name">Nama Gedung <span class="text-danger">*</span></label>
                <input type="text" id="name" class="form-control" wire:model.defer="buildingForm.name">
                @error('buildingForm.name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="code">Kode Gedung</label>
                <input type="text" id="code" class="form-control" wire:model.defer="buildingForm.code">
                @error('buildingForm.code')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="address">Alamat</label>
                <input type="text" id="address" class="form-control" wire:model.defer="buildingForm.address">
                @error('buildingForm.address')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="floor_count">Jumlah Lantai</label>
                <input type="number" id="floor_count" class="form-control" wire:model.defer="buildingForm.floor_count" min="1">
                @error('buildingForm.floor_count')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-12 col-md-12 col-sm-12 mt-2">
                <label for="desc">Deskripsi</label>
                <textarea id="desc" class="form-control" wire:model.defer="buildingForm.desc" rows="4"></textarea>
                @error('buildingForm.desc')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-12 col-md-12 col-sm-12 mt-2">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="buildingForm.is_active">
                    <label class="form-check-label" for="is_active">
                        Gedung Aktif
                    </label>
                </div>
            </div>
            <div class="form-group col-lg-12 col-md-12 col-sm-12 mt-4">
                <button type="button" class="btn btn-primary" wire:click="updateBuilding">
                    <i class="fa fa-save me-2"></i> Simpan Perubahan
                </button>
                <button type="button" class="btn btn-secondary ms-2" wire:click="cancel">
                    <i class="fa fa-times me-2"></i> Batal
                </button>
            </div>
        </div>
    </div>
</div>
