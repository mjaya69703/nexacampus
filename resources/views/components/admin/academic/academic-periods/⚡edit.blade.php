<?php

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public int $academicPeriodId;
    public array $academicPeriodForm = [];
    public array $availableAcademicYears = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.academic-periods.index');
    }

    public function mount($id): void
    {
        $period = AcademicPeriod::findOrFail($id);
        $this->academicPeriodId = (int) $id;

        $this->academicPeriodForm = [
            'academic_year_id' => $period->academic_year_id,
            'name' => $period->name,
            'code' => $period->code,
            'type' => $period->type,
            'start_at' => optional($period->start_at)->format('Y-m-d\TH:i'),
            'end_at' => optional($period->end_at)->format('Y-m-d\TH:i'),
            'is_active' => (bool) $period->is_active,
            'desc' => $period->desc,
        ];

        $this->availableAcademicYears = AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name'])
            ->map(fn (AcademicYear $year) => [
                'id' => $year->id,
                'name' => $year->name,
            ])
            ->toArray();
    }

    public function updateAcademicPeriod(): void
    {
        $validatedData = $this->validate([
            'academicPeriodForm.academic_year_id' => 'required|integer|exists:academic_years,id',
            'academicPeriodForm.name' => 'required|string|max:255',
            'academicPeriodForm.code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('academic_periods', 'code')
                    ->ignore($this->academicPeriodId)
                    ->whereNull('deleted_at'),
            ],
            'academicPeriodForm.type' => 'required|in:Student Registration,Study Plan,Study Plan Revision,Grading,Exam,Remedial,Academic Leave,Yudisium,Custom',
            'academicPeriodForm.start_at' => 'required|date',
            'academicPeriodForm.end_at' => 'required|date|after:academicPeriodForm.start_at',
            'academicPeriodForm.is_active' => 'boolean',
            'academicPeriodForm.desc' => 'nullable|string',
        ]);

        $period = AcademicPeriod::findOrFail($this->academicPeriodId);

        $period->update([
            'academic_year_id' => $validatedData['academicPeriodForm']['academic_year_id'],
            'name' => $validatedData['academicPeriodForm']['name'],
            'code' => $validatedData['academicPeriodForm']['code'] ?: null,
            'type' => $validatedData['academicPeriodForm']['type'],
            'start_at' => $validatedData['academicPeriodForm']['start_at'],
            'end_at' => $validatedData['academicPeriodForm']['end_at'],
            'is_active' => (bool) $validatedData['academicPeriodForm']['is_active'],
            'desc' => $validatedData['academicPeriodForm']['desc'] ?: null,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Periode akademik berhasil diperbarui.');
        $this->redirectRoute('admin.academic.academic-periods.index');
    }

    public function render()
    {
        $data = [
            'menus' => 'Academic Management',
            'pages' => 'Edit Periode Akademik',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Edit Periode Akademik {{ $academicPeriodForm['name'] ?? '' }}</h3>
        </div>
        <div class="card-body row">
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="academic_year_id">Tahun Akademik</label>
                <select id="academic_year_id" class="form-control" wire:model.defer="academicPeriodForm.academic_year_id">
                    <option value="">Pilih Tahun Akademik</option>
                    @foreach ($availableAcademicYears as $academicYear)
                        <option value="{{ $academicYear['id'] }}">{{ $academicYear['name'] }}</option>
                    @endforeach
                </select>
                @error('academicPeriodForm.academic_year_id')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="type">Tipe Periode</label>
                <select id="type" class="form-control" wire:model.live="academicPeriodForm.type">
                    <option value="Student Registration">Student Registration</option>
                    <option value="Study Plan">Study Plan</option>
                    <option value="Study Plan Revision">Study Plan Revision</option>
                    <option value="Grading">Grading</option>
                    <option value="Exam">Exam</option>
                    <option value="Remedial">Remedial</option>
                    <option value="Academic Leave">Academic Leave</option>
                    <option value="Yudisium">Yudisium</option>
                    <option value="Custom">Custom</option>
                </select>
                @error('academicPeriodForm.type')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-lg-8 col-md-8 col-sm-12 mt-2">
                <label for="name">Nama Periode</label>
                <input type="text" id="name" class="form-control" wire:model.defer="academicPeriodForm.name">
                @error('academicPeriodForm.name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-lg-4 col-md-4 col-sm-12 mt-2">
                <label for="code">Kode</label>
                <input type="text" id="code" class="form-control" wire:model.defer="academicPeriodForm.code">
                @error('academicPeriodForm.code')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="start_at">Mulai</label>
                <input type="datetime-local" id="start_at" class="form-control" wire:model.defer="academicPeriodForm.start_at">
                @error('academicPeriodForm.start_at')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="end_at">Selesai</label>
                <input type="datetime-local" id="end_at" class="form-control" wire:model.defer="academicPeriodForm.end_at">
                @error('academicPeriodForm.end_at')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-12 mt-2">
                <label for="desc">Deskripsi</label>
                <textarea id="desc" class="form-control" rows="3" wire:model.defer="academicPeriodForm.desc"></textarea>
                @error('academicPeriodForm.desc')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-12 mt-2">
                <div class="form-check form-switch mt-2">
                    <input id="is_active" class="form-check-input" type="checkbox" wire:model.defer="academicPeriodForm.is_active">
                    <label for="is_active" class="form-check-label">Aktif</label>
                </div>
                @error('academicPeriodForm.is_active')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
                <button class="btn btn-primary" wire:click="updateAcademicPeriod">
                    <i class="fas fa-save me-2"></i> Simpan Perubahan</button>
                <button class="btn btn-secondary" wire:click="cancel">
                    <i class="fas fa-times me-2"></i> Batal</button>
            </div>
        </div>
    </div>
</div>
