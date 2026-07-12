<?php

use App\Models\Academic\Faculty;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public int $facultyId;
    public array $facultyForm = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.faculties.index');
    }

    public function mount($id): void
    {
        $faculty = Faculty::findOrFail($id);
        $this->facultyId = (int) $id;

        $this->facultyForm = [
            'name' => $faculty->name,
            'code' => $faculty->code,
            'short_name' => $faculty->short_name,
            'is_active' => (bool) $faculty->is_active,
            'desc' => $faculty->desc,
        ];
    }

    public function updateFaculty(): void
    {
        $validatedData = $this->validate([
            'facultyForm.name' => 'required|string|max:255',
            'facultyForm.code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('faculties', 'code')->ignore($this->facultyId),
            ],
            'facultyForm.short_name' => 'nullable|string|max:50',
            'facultyForm.is_active' => 'boolean',
            'facultyForm.desc' => 'nullable|string',
        ]);

        $faculty = Faculty::findOrFail($this->facultyId);

        $faculty->update([
            'name' => $validatedData['facultyForm']['name'],
            'code' => $validatedData['facultyForm']['code'],
            'short_name' => $validatedData['facultyForm']['short_name'] ?: null,
            'is_active' => (bool) $validatedData['facultyForm']['is_active'],
            'desc' => $validatedData['facultyForm']['desc'] ?: null,
            'updated_by' => auth()->id(),
        ]);

        if (! $faculty->is_active) {
            $faculty->studyPrograms()->update([
                'is_active' => false,
                'updated_by' => auth()->id(),
            ]);
        }

        session()->flash('success', 'Fakultas berhasil diperbarui.');
        $this->redirectRoute('admin.academic.faculties.index');
    }

    public function render()
    {
        $data = [
            'menus' => 'Academic Management',
            'pages' => 'Edit Fakultas',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Edit Fakultas"
        description="Perbarui informasi data fakultas, kode unit, atau status operasional program studi di bawahnya."
        icon="edit"
    >
        <button type="button" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2" wire:click="cancel">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </button>
    </x-admin.academic.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-building-columns fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Edit Data Fakultas</h4>
                            <div class="text-muted small">Perbarui nama lengkap fakultas, kode unit, nama singkat, serta status operasional.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4 row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="name">Nama Fakultas <span class="text-danger">*</span></label>
                        <input type="text" id="name" class="form-control" wire:model.defer="facultyForm.name">
                        @error('facultyForm.name')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" for="code">Kode <span class="text-danger">*</span></label>
                        <input type="text" id="code" class="form-control" wire:model.defer="facultyForm.code">
                        @error('facultyForm.code')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" for="short_name">Nama Singkat</label>
                        <input type="text" id="short_name" class="form-control" wire:model.defer="facultyForm.short_name">
                        @error('facultyForm.short_name')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="desc">Deskripsi</label>
                        <textarea id="desc" class="form-control" rows="3" wire:model.defer="facultyForm.desc"></textarea>
                        @error('facultyForm.desc')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch mt-2">
                            <input id="is_active" class="form-check-input" type="checkbox" wire:model.defer="facultyForm.is_active">
                            <label for="is_active" class="form-check-label fw-semibold">Aktifkan Fakultas</label>
                        </div>
                        <div class="form-text text-muted small">Jika status diubah menjadi nonaktif, maka seluruh Program Studi yang bernaung di bawah fakultas ini akan turut dinonaktifkan.</div>
                        @error('facultyForm.is_active')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-12 border-top pt-3 mt-4 d-flex align-items-center justify-content-end gap-2">
                        <button class="btn btn-light rounded-pill px-4 py-2" wire:click="cancel" type="button">
                            <i class="fa fa-times me-1"></i> Batal
                        </button>
                        <button class="btn btn-primary rounded-pill px-4 py-2 shadow-sm" wire:click="updateFaculty" type="button">
                            <i class="fa fa-save me-1"></i> Simpan Perubahan
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
                            <h5 class="fw-bold mb-1">Catatan Perubahan</h5>
                            <div class="text-muted small">Panduan pengeditan data.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Perubahan Kode:</strong> Pastikan kode tetap unik dan tidak bentrok dengan fakultas lain.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Dampak Nonaktif:</strong> Menonaktifkan fakultas akan otomatis mengubah status seluruh Program Studi di bawahnya menjadi Nonaktif.</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
