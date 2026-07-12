<?php

use App\Models\Financial\FinancialClearancePolicy;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public FinancialClearancePolicy $policy;

    public array $invoiceTypes = [];

    public array $holdTypes = [];

    public array $form = [];

    public function mount($id): void
    {
        $this->policy = FinancialClearancePolicy::findOrFail($id);
        $this->form = [
            'invoice_type' => $this->policy->invoice_type,
            'hold_type' => $this->policy->hold_type,
            'mode' => $this->policy->mode,
            'grace_days' => $this->policy->grace_days,
            'is_active' => (int) $this->policy->is_active,
            'description' => $this->policy->description,
        ];
        $this->loadOptions();
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];

        $this->policy->update([
            ...$validated,
            'is_active' => (bool) $validated['is_active'],
        ]);

        session()->flash('success', 'Kebijakan clearance berhasil diperbarui.');
        $this->redirectRoute('admin.financial.clearance-policies.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Edit Kebijakan Clearance',
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
                    ->where('invoice_type', $this->form['invoice_type'] ?? null)
                    ->ignore($this->policy->id),
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

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Edit Kebijakan Clearance"
        description="Perbarui mode pembatasan, masa tenggang (grace period), atau status kebijakan."
        icon="edit"
    >
        <a href="{{ route('admin.financial.clearance-policies.index') }}" class="btn btn-sm btn-light text-secondary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </a>
    </x-admin.financial.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-edit fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Formulir Edit Kebijakan</h4>
                    <span class="text-muted small">Perubahan parameter akan memengaruhi pengecekan pemblokiran secara real-time.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <form wire:submit.prevent="save">
                @include('components.admin.financial.clearance-policies._form')
            </form>
        </div>
    </div>
</div>
