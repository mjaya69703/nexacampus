<?php

use App\Models\Alumni\EmployerPartner;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public array $form = [];
    public $logo;

    public function cancel(): void
    {
        $this->redirectRoute('admin.alumni.employer-partners.index');
    }

    public function mount(): void
    {
        $this->form = [
            'name' => '',
            'industry' => '',
            'website' => '',
            'contact_person' => '',
            'contact_email' => '',
            'contact_phone' => '',
            'address' => '',
            'city' => '',
            'province' => '',
            'description' => '',
            'is_active' => true,
        ];
    }

    public function createEmployerPartner(): void
    {
        $validated = $this->validate([
            'form.name' => 'required|string|max:255',
            'form.industry' => 'required|string|max:255',
            'form.website' => 'nullable|url|max:500',
            'form.contact_person' => 'nullable|string|max:255',
            'form.contact_email' => 'nullable|email|max:255',
            'form.contact_phone' => 'nullable|string|max:30',
            'form.address' => 'nullable|string',
            'form.city' => 'nullable|string|max:255',
            'form.province' => 'nullable|string|max:255',
            'form.description' => 'nullable|string',
            'form.is_active' => 'boolean',
            'logo' => 'nullable|image|max:2048',
        ]);

        $logoPath = null;
        if ($this->logo) {
            $logoPath = $this->logo->store('employer-partners/logos', 'public');
        }

        EmployerPartner::create(array_merge(
            collect($validated['form'])->filter(fn ($v) => $v !== '')->toArray(),
            [
                'logo_path' => $logoPath,
                'created_by' => auth()->id(),
            ]
        ));

        session()->flash('success', 'Mitra perusahaan berhasil ditambahkan.');
        $this->redirectRoute('admin.alumni.employer-partners.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni',
            'pages' => 'Tambah Mitra Perusahaan',
        ]);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Tambah Mitra Perusahaan</h3>
        </div>
        <div class="card-body row">
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="name">Nama Perusahaan <span class="text-danger">*</span></label>
                <input type="text" id="name" class="form-control" wire:model.defer="form.name">
                @error('form.name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="industry">Industri <span class="text-danger">*</span></label>
                <input type="text" id="industry" class="form-control" wire:model.defer="form.industry">
                @error('form.industry') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="website">Website</label>
                <input type="url" id="website" class="form-control" wire:model.defer="form.website" placeholder="https://...">
                @error('form.website') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="contact_person">Kontak Person</label>
                <input type="text" id="contact_person" class="form-control" wire:model.defer="form.contact_person">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="contact_email">Email Kontak</label>
                <input type="email" id="contact_email" class="form-control" wire:model.defer="form.contact_email">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="contact_phone">No. Telepon</label>
                <input type="text" id="contact_phone" class="form-control" wire:model.defer="form.contact_phone">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="city">Kota</label>
                <input type="text" id="city" class="form-control" wire:model.defer="form.city">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="province">Provinsi</label>
                <input type="text" id="province" class="form-control" wire:model.defer="form.province">
            </div>
            <div class="form-group col-12 mt-2">
                <label for="address">Alamat</label>
                <textarea id="address" class="form-control" rows="2" wire:model.defer="form.address"></textarea>
            </div>
            <div class="form-group col-12 mt-2">
                <label for="description">Deskripsi</label>
                <textarea id="description" class="form-control" rows="3" wire:model.defer="form.description"></textarea>
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="logo">Logo</label>
                <input type="file" id="logo" class="form-control" wire:model="logo" accept="image/*">
                <div wire:loading wire:target="logo" class="text-muted mt-1">Uploading...</div>
            </div>
            <div class="form-group col-12 mt-2">
                <div class="form-check form-switch mt-2">
                    <input id="is_active" class="form-check-input" type="checkbox" wire:model.defer="form.is_active">
                    <label for="is_active" class="form-check-label">Aktif</label>
                </div>
            </div>

            <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
                <button class="btn btn-primary" wire:click="createEmployerPartner">
                    <i class="fas fa-save me-2"></i> Simpan Perubahan
                </button>
                <button class="btn btn-secondary" wire:click="cancel">
                    <i class="fas fa-times me-2"></i> Batal
                </button>
            </div>
        </div>
    </div>
</div>
