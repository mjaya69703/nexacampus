<?php

use App\Models\Organization\LecturerWorkloadRule;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = [
        'category' => 'structural',
        'source_code' => '',
        'name' => '',
        'sks_value' => '1',
        'maximum_sks' => '',
        'is_active' => true,
        'description' => '',
    ];

    public function save(): void
    {
        $validated = $this->validate([
            'form.category' => ['required', 'in:teaching,structural,tridharma'],
            'form.source_code' => ['required', 'string', 'max:80', Rule::unique('lecturer_workload_rules', 'source_code')->where('category', $this->form['category'])],
            'form.name' => ['required', 'string', 'max:255'],
            'form.sks_value' => ['required', 'numeric', 'min:0'],
            'form.maximum_sks' => ['nullable', 'numeric', 'gte:form.sks_value'],
            'form.is_active' => ['boolean'],
            'form.description' => ['nullable', 'string'],
        ])['form'];

        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();
        $validated['source_code'] = strtoupper($validated['source_code']);
        $validated['maximum_sks'] = $validated['maximum_sks'] === '' ? null : $validated['maximum_sks'];

        LecturerWorkloadRule::create($validated);
        session()->flash('success', 'Aturan SKS berhasil dibuat.');
        $this->redirectRoute('admin.organization.lecturer-workload-rules.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Tambah Aturan SKS']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Tambah Aturan Konversi SKS"
        description="Daftarkan regulasi perhitungan atau konversi poin SKS baru untuk aktivitas pengajaran, penugasan struktural, dan tridharma."
        icon="cog"
    >
        <a href="{{ route('admin.organization.lecturer-workload-rules.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.organization.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-cogs fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Regulasi Konversi SKS BKD</h4>
                            <div class="text-muted small">Tentukan kategori, kode sumber konversi, dan nilai/bobot SKS yang diakui.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.organization.lecturer-workload-rules._form')
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
                            <h5 class="fw-bold mb-1">Pedoman Konversi SKS</h5>
                            <div class="text-muted small">Panduan pengisian regulasi.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Kode Sumber (Source Code)</strong> harus konsisten dan huruf kapital, karena digunakan oleh sistem untuk pemetaan otomatis dari data absensi atau skema tridharma.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Nilai Konversi SKS</strong> menentukan berapa bobot poin yang otomatis dihitung ketika aktivitas dengan kode ini diklaim oleh dosen.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Gunakan <strong>Batas Maksimum SKS</strong> jika regulasi PO BKD membatasi maksimal pengakuan untuk aktivitas tersebut dalam 1 semester.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Regulasi BKD</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Pemetaan PO BKD</div>
                    <p class="text-muted small mb-0">Aturan yang dinonaktifkan tidak akan muncul pada daftar pilihan saat dosen menyusun laporan kinerja atau pengajuan BKD di periode berjalan.</p>
                </div>
            </div>
        </div>
    </div>
</div>
