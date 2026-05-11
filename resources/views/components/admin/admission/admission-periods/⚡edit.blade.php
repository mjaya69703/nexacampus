<?php

use App\Models\Admission\AdmissionPeriod;
use App\Models\Academic\AcademicYear;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public AdmissionPeriod $period;

    public array $periodForm = [];

    public array $documentRequirements = [];

    public array $academicYears = [];

    public function mount($id): void
    {
        $this->period = AdmissionPeriod::with('documentRequirements')->findOrFail($id);

        $this->periodForm = [
            'name' => $this->period->name,
            'code' => $this->period->code,
            'academic_year_id' => $this->period->academic_year_id,
            'academic_year' => $this->period->academic_year,
            'wave' => $this->period->wave,
            'opens_at' => $this->period->opens_at?->toDateString(),
            'closes_at' => $this->period->closes_at?->toDateString(),
            'is_active' => $this->period->is_active,
            'is_published' => $this->period->is_published,
            'description' => $this->period->description,
        ];

        $this->documentRequirements = $this->period->documentRequirements
            ->sortBy('sort_order')
            ->map(fn ($requirement) => [
                'id' => $requirement->id,
                'document_type' => $requirement->document_type,
                'label' => $requirement->label,
                'is_required' => $requirement->is_required,
                'allowed_extensions' => $requirement->allowed_extensions,
                'max_size_kb' => $requirement->max_size_kb,
            ])
            ->values()
            ->toArray();

        $this->academicYears = AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'code', 'start_date'])
            ->map(fn (AcademicYear $academicYear) => [
                'id' => $academicYear->id,
                'name' => $academicYear->name,
                'year' => (int) $academicYear->start_date?->format('Y'),
            ])
            ->toArray();
    }

    public function updatedPeriodFormAcademicYearId($value): void
    {
        $selected = collect($this->academicYears)->firstWhere('id', (int) $value);

        if ($selected) {
            $this->periodForm['academic_year'] = $selected['year'];
        }
    }

    public function addRequirement(): void
    {
        $this->documentRequirements[] = [
            'id' => null,
            'document_type' => '',
            'label' => '',
            'is_required' => true,
            'allowed_extensions' => 'pdf,jpg,jpeg,png',
            'max_size_kb' => 2048,
        ];
    }

    public function removeRequirement(int $index): void
    {
        $id = $this->documentRequirements[$index]['id'] ?? null;

        if ($id) {
            $this->period->documentRequirements()->whereKey($id)->delete();
        }

        unset($this->documentRequirements[$index]);
        $this->documentRequirements = array_values($this->documentRequirements);
    }

    public function updatePeriod(): void
    {
        $validated = $this->validate([
            'periodForm.name' => 'required|string|max:255',
            'periodForm.code' => ['required', 'string', 'max:50', Rule::unique('admission_periods', 'code')->ignore($this->period->id)],
            'periodForm.academic_year_id' => 'nullable|exists:academic_years,id',
            'periodForm.academic_year' => 'required|integer|min:2000|max:2100',
            'periodForm.wave' => 'required|integer|min:1|max:20',
            'periodForm.opens_at' => 'required|date',
            'periodForm.closes_at' => 'required|date|after_or_equal:periodForm.opens_at',
            'periodForm.is_active' => 'boolean',
            'periodForm.is_published' => 'boolean',
            'periodForm.description' => 'nullable|string',
            'documentRequirements' => 'array',
            'documentRequirements.*.id' => 'nullable|integer',
            'documentRequirements.*.document_type' => 'required|string|max:80|distinct',
            'documentRequirements.*.label' => 'required|string|max:255',
            'documentRequirements.*.is_required' => 'boolean',
            'documentRequirements.*.allowed_extensions' => 'nullable|string|max:255',
            'documentRequirements.*.max_size_kb' => 'nullable|integer|min:1|max:10240',
        ]);

        $this->period->update([
            ...$validated['periodForm'],
            'updated_by' => auth()->id(),
        ]);

        foreach ($validated['documentRequirements'] as $index => $requirement) {
            $id = $requirement['id'] ?? null;
            unset($requirement['id']);
            $requirement['allowed_extensions'] = $this->sanitizeAllowedExtensions($requirement['allowed_extensions'] ?? null);

            $this->period->documentRequirements()->updateOrCreate(
                ['id' => $id],
                [
                    ...$requirement,
                    'sort_order' => $index + 1,
                ],
            );
        }

        session()->flash('success', 'Admission period berhasil diperbarui.');
        $this->redirectRoute('admin.admission.admission-periods.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.admission.admission-periods.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Edit Admission Period',
        ]);
    }

    private function sanitizeAllowedExtensions(?string $extensions): string
    {
        $safeAllowList = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

        $allowed = collect(explode(',', $extensions ?: 'pdf,jpg,jpeg,png'))
            ->map(fn (string $extension) => strtolower(trim($extension)))
            ->filter()
            ->unique()
            ->intersect($safeAllowList)
            ->values()
            ->all();

        return implode(',', $allowed ?: ['pdf', 'jpg', 'jpeg', 'png']);
    }
};
?>

<div>
    <x-alert />

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Edit Admission Period</h3>
        </div>
        <div class="card-body row">
            <div class="form-group col-lg-6 mt-2">
                <label>Name</label>
                <input type="text" class="form-control" wire:model.defer="periodForm.name">
                @error('periodForm.name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-3 mt-2">
                <label>Code</label>
                <input type="text" class="form-control" wire:model.defer="periodForm.code">
                @error('periodForm.code') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-3 mt-2">
                <label>Academic Year Master</label>
                <select class="form-control" wire:model.live="periodForm.academic_year_id">
                    <option value="">Select Academic Year</option>
                    @foreach ($academicYears as $academicYear)
                        <option value="{{ $academicYear['id'] }}">{{ $academicYear['name'] }}</option>
                    @endforeach
                </select>
                @error('periodForm.academic_year_id') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-3 mt-2">
                <label>Academic Year</label>
                <input type="number" class="form-control" wire:model.defer="periodForm.academic_year">
                @error('periodForm.academic_year') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-3 mt-2">
                <label>Wave</label>
                <input type="number" class="form-control" wire:model.defer="periodForm.wave">
                @error('periodForm.wave') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-3 mt-2">
                <label>Opens At</label>
                <input type="date" class="form-control" wire:model.defer="periodForm.opens_at">
                @error('periodForm.opens_at') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-3 mt-2">
                <label>Closes At</label>
                <input type="date" class="form-control" wire:model.defer="periodForm.closes_at">
                @error('periodForm.closes_at') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-3 mt-4">
                <div class="form-check form-switch mt-2">
                    <input id="is_active" class="form-check-input" type="checkbox" wire:model.defer="periodForm.is_active">
                    <label for="is_active" class="form-check-label">Active</label>
                </div>
                <div class="form-check form-switch mt-2">
                    <input id="is_published" class="form-check-input" type="checkbox" wire:model.defer="periodForm.is_published">
                    <label for="is_published" class="form-check-label">Published</label>
                </div>
            </div>
            <div class="form-group col-12 mt-2">
                <label>Description</label>
                <textarea class="form-control" rows="3" wire:model.defer="periodForm.description"></textarea>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Document Requirements</h3>
            <button type="button" class="btn btn-ghost-primary" wire:click="addRequirement">
                <i class="fas fa-plus me-1"></i> Add Requirement
            </button>
        </div>
        <div class="card-body">
            @foreach ($documentRequirements as $index => $requirement)
                <div class="row g-2 align-items-end border-bottom pb-3 mb-3" wire:key="requirement-edit-{{ $index }}">
                    <div class="col-lg-3">
                        <label>Type</label>
                        <input type="text" class="form-control" wire:model.defer="documentRequirements.{{ $index }}.document_type">
                        @error("documentRequirements.$index.document_type") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-lg-4">
                        <label>Label</label>
                        <input type="text" class="form-control" wire:model.defer="documentRequirements.{{ $index }}.label">
                        @error("documentRequirements.$index.label") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-lg-2">
                        <label>Extensions</label>
                        <input type="text" class="form-control" wire:model.defer="documentRequirements.{{ $index }}.allowed_extensions">
                    </div>
                    <div class="col-lg-2">
                        <label>Max KB</label>
                        <input type="number" class="form-control" wire:model.defer="documentRequirements.{{ $index }}.max_size_kb">
                    </div>
                    <div class="col-lg-1 d-flex gap-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" wire:model.defer="documentRequirements.{{ $index }}.is_required">
                            <label class="form-check-label">Req</label>
                        </div>
                        <button type="button" class="btn btn-danger btn-icon" wire:click="removeRequirement({{ $index }})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
        <button class="btn btn-primary" wire:click="updatePeriod">
            <i class="fas fa-save me-2"></i> Save
        </button>
        <button class="btn btn-secondary" wire:click="cancel">
            <i class="fas fa-times me-2"></i> Cancel
        </button>
    </div>
</div>
