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

        session()->flash('success', 'Program beasiswa berhasil dibuat.');
        $this->redirectRoute('admin.financial.scholarships.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Buat Program Beasiswa',
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

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Buat Program Beasiswa Baru"
        description="Tentukan nama beasiswa, kategori, persentase atau nominal potongan tetap, serta persyaratan pendaftaran."
        icon="plus-circle"
    >
        <a href="{{ route('admin.financial.scholarships.index') }}" class="btn btn-sm btn-light text-secondary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </a>
    </x-admin.financial.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-award fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Formulir Konfigurasi Program</h4>
                    <span class="text-muted small">Lengkapi parameter potongan dan ketentuan beasiswa kampus.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <form wire:submit.prevent="save">
                @include('components.admin.financial.scholarships._form')
            </form>
        </div>
    </div>
</div>
