<?php

use App\Models\Financial\FinancialClearancePolicy;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $invoiceTypes = [];

    public array $holdTypes = [];

    public array $form = [
        'invoice_type' => 'tuition',
        'hold_type' => 'study_plan',
        'mode' => 'blocking',
        'grace_days' => 7,
        'is_active' => 1,
        'description' => '',
    ];

    public function mount(): void
    {
        $this->loadOptions();
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];

        FinancialClearancePolicy::create([
            ...$validated,
            'is_active' => (bool) $validated['is_active'],
        ]);

        session()->flash('success', 'Clearance policy berhasil dibuat.');
        $this->redirectRoute('admin.financial.clearance-policies.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Create Clearance Policy',
        ]);
    }

    private function rules(): array
    {
        return [
            'form.invoice_type' => ['required', Rule::in(array_column($this->invoiceTypes, 'id'))],
            'form.hold_type' => [
                'required',
                Rule::in(array_column($this->holdTypes, 'id')),
                Rule::unique('financial_clearance_policies', 'hold_type')
                    ->where('invoice_type', $this->form['invoice_type'] ?? null),
            ],
            'form.mode' => ['required', Rule::in(['warning', 'blocking'])],
            'form.grace_days' => ['required', 'integer', 'min:0', 'max:365'],
            'form.is_active' => ['required', 'boolean'],
            'form.description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function loadOptions(): void
    {
        $this->invoiceTypes = $this->options(config('financial.invoice_types', []));
        $this->holdTypes = $this->options($this->holdTargetOptions());
    }

    private function options(array $values): array
    {
        return collect($values)
            ->map(fn (string $label, string $value) => [
                'id' => $value,
                'label' => $label,
            ])
            ->all();
    }

    private function holdTargetOptions(): array
    {
        return collect(config('financial.hold_targets', []))
            ->mapWithKeys(fn (array $target, string $key) => [
                $key => $target['label'] ?? str($key)->replace('_', ' ')->title()->toString(),
            ])
            ->all();
    }
};
?>

<div class="row">
    <div class="col-12">
        <x-alert />
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Create Clearance Policy</h3>
                <a href="{{ route('admin.financial.clearance-policies.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    @include('components.admin.financial.clearance-policies._form')
                </form>
            </div>
        </div>
    </div>
</div>
