<?php

use App\Models\Academic\AcademicYear;
use Livewire\Component;
use Illuminate\Validation\Rule;

new class extends Component
{
    public int $academicYearId;
    public array $academicYearForm = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.academic-years.index');
    }

    public function mount($id): void
    {
        $academicYear = AcademicYear::findOrFail($id);
        $this->academicYearId = (int) $id;

        $this->academicYearForm = [
            'name' => $academicYear->name,
            'code' => $academicYear->code,
            'semester' => $academicYear->semester,
            'start_date' => optional($academicYear->start_date)->format('Y-m-d'),
            'end_date' => optional($academicYear->end_date)->format('Y-m-d'),
            'is_active' => (bool) $academicYear->is_active,
            'desc' => $academicYear->desc,
        ];
    }

    public function updateAcademicYear(): void
    {
        $validatedData = $this->validate([
            'academicYearForm.name' => 'required|string|max:255',
            'academicYearForm.code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('academic_years', 'code')->ignore($this->academicYearId),
            ],
            'academicYearForm.semester' => 'required|in:Ganjil,Genap,Pendek',
            'academicYearForm.start_date' => 'required|date',
            'academicYearForm.end_date' => 'required|date|after_or_equal:academicYearForm.start_date',
            'academicYearForm.is_active' => 'boolean',
            'academicYearForm.desc' => 'nullable|string',
        ]);

        $academicYear = AcademicYear::findOrFail($this->academicYearId);

        $payload = [
            'name' => $validatedData['academicYearForm']['name'],
            'code' => $validatedData['academicYearForm']['code'],
            'semester' => $validatedData['academicYearForm']['semester'],
            'start_date' => $validatedData['academicYearForm']['start_date'],
            'end_date' => $validatedData['academicYearForm']['end_date'],
            'is_active' => (bool) $validatedData['academicYearForm']['is_active'],
            'desc' => $validatedData['academicYearForm']['desc'] ?: null,
            'updated_by' => auth()->id(),
        ];

        if ($payload['is_active']) {
            AcademicYear::query()
                ->where('id', '!=', $academicYear->id)
                ->update(['is_active' => false]);
        }

        $academicYear->update($payload);

        session()->flash('success', 'Tahun akademik berhasil diperbarui.');
        $this->redirectRoute('admin.academic.academic-years.index');
    }

    public function render()
    {
        $data = [
            'menus' => 'Academic Management',
            'pages' => 'Edit Tahun Akademik',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Edit Tahun Akademik"
        description="Perbarui informasi data tahun akademik, masa aktif kalender, atau status aktif semester."
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
                            <i class="fa fa-calendar fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Edit Data Tahun Akademik</h4>
                            <div class="text-muted small">Perbarui nama tahun ajaran, kode unik, semester, serta status aktif sekarang.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4 row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="name">Nama Tahun Akademik <span class="text-danger">*</span></label>
                        <input type="text" id="name" class="form-control" wire:model.defer="academicYearForm.name">
                        @error('academicYearForm.name')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="code">Kode <span class="text-danger">*</span></label>
                        <input type="text" id="code" class="form-control" wire:model.defer="academicYearForm.code">
                        @error('academicYearForm.code')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="semester">Semester <span class="text-danger">*</span></label>
                        <select id="semester" class="form-select" wire:model.defer="academicYearForm.semester">
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
                            <option value="Pendek">Pendek</option>
                        </select>
                        @error('academicYearForm.semester')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="start_date">Tanggal Mulai <span class="text-danger">*</span></label>
                        <input type="date" id="start_date" class="form-control" wire:model.defer="academicYearForm.start_date">
                        @error('academicYearForm.start_date')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="end_date">Tanggal Selesai <span class="text-danger">*</span></label>
                        <input type="date" id="end_date" class="form-control" wire:model.defer="academicYearForm.end_date">
                        @error('academicYearForm.end_date')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="desc">Deskripsi / Keterangan</label>
                        <textarea id="desc" class="form-control" rows="3" wire:model.defer="academicYearForm.desc"></textarea>
                        @error('academicYearForm.desc')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch mt-2">
                            <input id="is_active" class="form-check-input" type="checkbox" wire:model.defer="academicYearForm.is_active">
                            <label for="is_active" class="form-check-label fw-semibold">Jadikan Status Aktif Sekarang</label>
                        </div>
                        <div class="form-text text-muted small">Jika diaktifkan, tahun akademik lain yang sebelumnya aktif akan otomatis dinonaktifkan.</div>
                        @error('academicYearForm.is_active')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-12 border-top pt-3 mt-4 d-flex align-items-center justify-content-end gap-2">
                        <button class="btn btn-light rounded-pill px-4 py-2" wire:click="cancel" type="button">
                            <i class="fa fa-times me-1"></i> Batal
                        </button>
                        <button class="btn btn-primary rounded-pill px-4 py-2 shadow-sm" wire:click="updateAcademicYear" type="button">
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
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Perubahan Kode:</strong> Pastikan kode tetap unik dan tidak duplikat dengan tahun akademik lain.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Rentang Waktu:</strong> Tanggal selesai harus berada pada atau setelah tanggal mulai.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Dampak Relasi:</strong> Perubahan semester dan masa aktif akan memengaruhi periode akademik dan KRS mahasiswa yang bernaung di bawahnya.</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
