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
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Edit Tahun Akademik {{ $academicYearForm['name'] ?? '' }}</h3>
        </div>
        <div class="card-body row">
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="name">Nama Tahun Akademik</label>
                <input type="text" id="name" class="form-control" wire:model.defer="academicYearForm.name">
                @error('academicYearForm.name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="code">Kode</label>
                <input type="text" id="code" class="form-control" wire:model.defer="academicYearForm.code">
                @error('academicYearForm.code')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="semester">Semester</label>
                <select id="semester" class="form-control" wire:model.defer="academicYearForm.semester">
                    <option value="Ganjil">Ganjil</option>
                    <option value="Genap">Genap</option>
                    <option value="Pendek">Pendek</option>
                </select>
                @error('academicYearForm.semester')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="start_date">Tanggal Mulai</label>
                <input type="date" id="start_date" class="form-control" wire:model.defer="academicYearForm.start_date">
                @error('academicYearForm.start_date')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="end_date">Tanggal Selesai</label>
                <input type="date" id="end_date" class="form-control" wire:model.defer="academicYearForm.end_date">
                @error('academicYearForm.end_date')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-12 mt-2">
                <label for="desc">Deskripsi</label>
                <textarea id="desc" class="form-control" rows="3" wire:model.defer="academicYearForm.desc"></textarea>
                @error('academicYearForm.desc')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-12 mt-2">
                <div class="form-check form-switch mt-2">
                    <input id="is_active" class="form-check-input" type="checkbox" wire:model.defer="academicYearForm.is_active">
                    <label for="is_active" class="form-check-label">Jadikan aktif</label>
                </div>
                @error('academicYearForm.is_active')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
                <button class="btn btn-primary" wire:click="updateAcademicYear">
                    <i class="fas fa-save me-2"></i> Simpan Perubahan</button>
                <button class="btn btn-secondary" wire:click="cancel">
                    <i class="fas fa-times me-2"></i> Batal</button>
            </div>
        </div>
    </div>
</div>
