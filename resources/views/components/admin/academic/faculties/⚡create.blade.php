<?php

use App\Models\Academic\Faculty;
use Livewire\Component;

new class extends Component
{
    public array $facultyForm = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.faculties.index');
    }

    public function mount(): void
    {
        $this->facultyForm = [
            'name' => '',
            'code' => '',
            'short_name' => '',
            'is_active' => true,
            'desc' => '',
        ];
    }

    public function createFaculty(): void
    {
        $validatedData = $this->validate([
            'facultyForm.name' => 'required|string|max:255',
            'facultyForm.code' => 'required|string|max:20|unique:faculties,code',
            'facultyForm.short_name' => 'nullable|string|max:50',
            'facultyForm.is_active' => 'boolean',
            'facultyForm.desc' => 'nullable|string',
        ]);

        Faculty::create([
            'name' => $validatedData['facultyForm']['name'],
            'code' => $validatedData['facultyForm']['code'],
            'short_name' => $validatedData['facultyForm']['short_name'] ?: null,
            'is_active' => (bool) $validatedData['facultyForm']['is_active'],
            'desc' => $validatedData['facultyForm']['desc'] ?: null,
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Fakultas berhasil ditambahkan.');
        $this->redirectRoute('admin.academic.faculties.index');
    }

    public function render()
    {
        $data = [
            'menus' => 'Academic Management',
            'pages' => 'Tambah Fakultas',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Tambah Fakultas</h3>
        </div>
        <div class="card-body row">
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="name">Nama Fakultas</label>
                <input type="text" id="name" class="form-control" placeholder="Contoh: Fakultas Teknik" wire:model.defer="facultyForm.name">
                @error('facultyForm.name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="code">Kode</label>
                <input type="text" id="code" class="form-control" placeholder="Contoh: FT" wire:model.defer="facultyForm.code">
                @error('facultyForm.code')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="short_name">Nama Singkat</label>
                <input type="text" id="short_name" class="form-control" placeholder="Contoh: Teknik" wire:model.defer="facultyForm.short_name">
                @error('facultyForm.short_name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-12 mt-2">
                <label for="desc">Deskripsi</label>
                <textarea id="desc" class="form-control" rows="3" wire:model.defer="facultyForm.desc" placeholder="Deskripsi fakultas (opsional)"></textarea>
                @error('facultyForm.desc')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-12 mt-2">
                <div class="form-check form-switch mt-2">
                    <input id="is_active" class="form-check-input" type="checkbox" wire:model.defer="facultyForm.is_active">
                    <label for="is_active" class="form-check-label">Aktif</label>
                </div>
                @error('facultyForm.is_active')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
                <button class="btn btn-primary" wire:click="createFaculty">
                    <i class="fas fa-save me-2"></i> Simpan Perubahan
                </button>
                <button class="btn btn-secondary" wire:click="cancel">
                    <i class="fas fa-times me-2"></i> Batal
                </button>
            </div>
        </div>
    </div>
</div>
