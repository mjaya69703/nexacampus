<?php

use App\Models\StudentService\ServiceLetterType;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = [
        'name' => '',
        'code' => '',
        'description' => '',
        'fulfillment_mode' => 'auto_generate',
        'template_key' => '',
        'requires_financial_clearance' => false,
        'clearance_hold_type' => '',
        'required_fields' => 'purpose',
        'requires_attachment' => false,
        'allowed_extensions' => 'pdf,jpg,jpeg,png',
        'max_file_size_kb' => 2048,
        'signer_name' => '',
        'signer_position' => '',
        'is_active' => 1,
    ];

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];

        ServiceLetterType::create([
            ...$validated,
            'code' => str($validated['code'])->upper()->replace(' ', '_')->toString(),
            'required_fields' => $this->requiredFieldsArray($validated['required_fields']),
            'requires_financial_clearance' => (bool) $validated['requires_financial_clearance'],
            'requires_attachment' => (bool) $validated['requires_attachment'],
            'is_active' => (bool) $validated['is_active'],
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Letter type berhasil dibuat.');
        $this->redirectRoute('admin.student-services.letter-types.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Buat Jenis Surat',
        ]);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:80', Rule::unique('service_letter_types', 'code')],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.fulfillment_mode' => ['required', Rule::in(['auto_generate', 'manual_upload', 'hybrid'])],
            'form.template_key' => ['nullable', 'string', 'max:120'],
            'form.requires_financial_clearance' => ['boolean'],
            'form.clearance_hold_type' => ['nullable', Rule::in(['registration', 'study_plan', 'exam_card', 'transcript', 'graduation'])],
            'form.required_fields' => ['nullable', 'string', 'max:500'],
            'form.requires_attachment' => ['boolean'],
            'form.allowed_extensions' => ['nullable', 'string', 'max:255'],
            'form.max_file_size_kb' => ['nullable', 'integer', 'min:1', 'max:20480'],
            'form.signer_name' => ['nullable', 'string', 'max:255'],
            'form.signer_position' => ['nullable', 'string', 'max:255'],
            'form.is_active' => ['required', 'boolean'],
        ];
    }

    private function requiredFieldsArray(?string $fields): array
    {
        return collect(explode(',', (string) $fields))->map(fn ($field) => trim($field))->filter()->values()->all();
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <x-admin.student-services.header
        title="Buat Jenis Surat / Layanan Baru"
        description="Konfigurasi mode pemenuhan (auto/manual), clearance keuangan, serta spesifikasi pejabat penanda tangan surat."
        icon="envelope-open-text"
    >
        <a href="{{ route('admin.student-services.letter-types.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.student-services.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-envelope-open-text fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Layanan Surat</h4>
                            <div class="text-muted small">Lengkapi spesifikasi pembuatan dan penerbitan surat.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.student-services.letter-types._form')
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
                            <h5 class="fw-bold mb-1">Panduan Mode Surat</h5>
                            <div class="text-muted small">Mekanisme pemenuhan permohonan.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Mode <strong>Auto Generate</strong> akan menghasilkan file PDF secara otomatis menggunakan template sistem sesuai <code>Key Template</code> yang diisikan.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Mode <strong>Manual Upload</strong> mewajibkan operator atau staf kemahasiswaan mengunggah file hasil penandatanganan manual.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Fitur verifikasi tunggakan akan secara otomatis mengecek status tagihan mahasiswa di sistem keuangan kampus.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Awal</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Aktif</div>
                    <p class="text-muted small mb-0">Layanan ini langsung muncul di portal kemahasiswaan dan siap diajukan oleh mahasiswa.</p>
                </div>
            </div>
        </div>
    </div>
</div>
