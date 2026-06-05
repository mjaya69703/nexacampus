<?php

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use App\Models\Organization\LecturerWorkloadPeriod;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public LecturerWorkloadPeriod $period;
    public array $form = [];

    public function mount($id): void
    {
        $this->period = LecturerWorkloadPeriod::findOrFail($id);
        $this->form = [
            'academic_year_id' => (string) $this->period->academic_year_id,
            'academic_period_id' => (string) $this->period->academic_period_id,
            'name' => $this->period->name,
            'code' => $this->period->code,
            'starts_at' => $this->period->starts_at?->toDateString(),
            'ends_at' => $this->period->ends_at?->toDateString(),
            'status' => $this->period->status,
            'minimum_sks' => (string) $this->period->minimum_sks,
            'maximum_sks' => (string) $this->period->maximum_sks,
            'notes' => $this->period->notes,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'form.academic_period_id' => ['nullable', 'integer', 'exists:academic_periods,id'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:80', Rule::unique('lecturer_workload_periods', 'code')->ignore($this->period->id)],
            'form.starts_at' => ['nullable', 'date'],
            'form.ends_at' => ['nullable', 'date', 'after_or_equal:form.starts_at'],
            'form.status' => ['required', 'in:draft,open,review,closed'],
            'form.minimum_sks' => ['required', 'numeric', 'min:0'],
            'form.maximum_sks' => ['required', 'numeric', 'gte:form.minimum_sks'],
            'form.notes' => ['nullable', 'string'],
        ])['form'];

        $validated['updated_by'] = auth()->id();
        $validated['academic_year_id'] = $validated['academic_year_id'] ?: null;
        $validated['academic_period_id'] = $validated['academic_period_id'] ?: null;

        $this->period->update($validated);
        session()->flash('success', 'Periode BKD berhasil diperbarui.');
        $this->redirectRoute('admin.organization.lecturer-workload-periods.index');
    }

    public function render()
    {
        return $this->view([
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
            'academicPeriods' => AcademicPeriod::orderByDesc('start_at')->get(),
        ])->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Edit Periode BKD']);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header"><h3 class="card-title mb-0">Edit Periode BKD</h3></div>
        <div class="card-body">
            @include('components.admin.organization.lecturer-workload-periods._form')
        </div>
    </div>
</div>
