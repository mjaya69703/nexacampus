<?php

use App\Models\Financial\Scholarship;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = [
        'name' => '',
        'description' => '',
        'type' => 'partial',
        'discount_type' => 'percentage',
        'discount_percentage' => 0,
        'fixed_amount' => 0,
        'duration_semesters' => 1,
        'requirements' => '',
        'is_active' => 1,
    ];

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];

        Scholarship::create([
            ...$validated,
            'is_active' => (bool) $validated['is_active'],
            'discount_percentage' => $validated['discount_type'] === 'percentage' ? $validated['discount_percentage'] : null,
            'fixed_amount' => $validated['discount_type'] === 'fixed' ? $validated['fixed_amount'] : null,
        ]);

        session()->flash('success', 'Scholarship berhasil dibuat.');
        $this->redirectRoute('admin.financial.scholarships.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Create Scholarship',
        ]);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.type' => ['required', Rule::in(['full', 'partial', 'merit', 'need_based'])],
            'form.discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'form.discount_percentage' => ['exclude_unless:form.discount_type,percentage', 'required', 'numeric', 'min:0.01', 'max:100'],
            'form.fixed_amount' => ['exclude_unless:form.discount_type,fixed', 'required', 'numeric', 'min:1'],
            'form.duration_semesters' => ['required', 'integer', 'min:1', 'max:20'],
            'form.requirements' => ['nullable', 'string', 'max:2000'],
            'form.is_active' => ['required', 'boolean'],
        ];
    }
};
?>

<div class="row">
    <div class="col-12">
        <x-alert />
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Create Scholarship</h3>
                <a href="{{ route('admin.financial.scholarships.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    @include('components.admin.financial.scholarships._form')
                </form>
            </div>
        </div>
    </div>
</div>
