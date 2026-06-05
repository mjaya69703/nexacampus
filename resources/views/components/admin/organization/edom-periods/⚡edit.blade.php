<?php

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use App\Models\Organization\EdomPeriod;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public EdomPeriod $period;
    public array $form = [];

    public function mount($id): void
    {
        $this->period = EdomPeriod::findOrFail($id);
        $this->form = [
            'academic_year_id' => (string) $this->period->academic_year_id,
            'academic_period_id' => (string) $this->period->academic_period_id,
            'name' => $this->period->name,
            'code' => $this->period->code,
            'starts_at' => $this->period->starts_at?->toDateString(),
            'ends_at' => $this->period->ends_at?->toDateString(),
            'status' => $this->period->status,
            'minimum_responses' => $this->period->minimum_responses,
            'notes' => $this->period->notes,
        ];
    }

    public function save(): void
    {
        $data = $this->validate([
            'form.academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'form.academic_period_id' => ['nullable', 'integer', 'exists:academic_periods,id'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:80', Rule::unique('edom_periods', 'code')->ignore($this->period->id)],
            'form.starts_at' => ['nullable', 'date'],
            'form.ends_at' => ['nullable', 'date', 'after_or_equal:form.starts_at'],
            'form.status' => ['required', 'in:draft,open,closed'],
            'form.minimum_responses' => ['required', 'integer', 'min:1'],
            'form.notes' => ['nullable', 'string'],
        ])['form'];

        $data['academic_year_id'] = $data['academic_year_id'] ?: null;
        $data['academic_period_id'] = $data['academic_period_id'] ?: null;
        $data['updated_by'] = auth()->id();
        $this->period->update($data);
        session()->flash('success', 'Periode EDOM berhasil diperbarui.');
        $this->redirectRoute('admin.organization.edom-periods.index');
    }

    public function render()
    {
        return $this->view(['academicYears' => AcademicYear::orderByDesc('start_date')->get(), 'academicPeriods' => AcademicPeriod::orderByDesc('start_at')->get()])->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Edit Periode EDOM']);
    }
};
?>

<div><x-alert /><div class="card"><div class="card-header"><h3 class="card-title mb-0">Edit Periode EDOM</h3></div><div class="card-body">@include('components.admin.organization.edom-periods._form')</div></div></div>
