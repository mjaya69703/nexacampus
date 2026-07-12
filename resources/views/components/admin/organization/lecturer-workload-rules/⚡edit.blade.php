<?php

use App\Models\Organization\LecturerWorkloadRule;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public LecturerWorkloadRule $rule;
    public array $form = [];

    public function mount($id): void
    {
        $this->rule = LecturerWorkloadRule::findOrFail($id);
        $this->form = [
            'category' => $this->rule->category,
            'source_code' => $this->rule->source_code,
            'name' => $this->rule->name,
            'sks_value' => (string) $this->rule->sks_value,
            'maximum_sks' => (string) $this->rule->maximum_sks,
            'is_active' => $this->rule->is_active,
            'description' => $this->rule->description,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.category' => ['required', 'in:teaching,structural,tridharma'],
            'form.source_code' => ['required', 'string', 'max:80', Rule::unique('lecturer_workload_rules', 'source_code')->where('category', $this->form['category'])->ignore($this->rule->id)],
            'form.name' => ['required', 'string', 'max:255'],
            'form.sks_value' => ['required', 'numeric', 'min:0'],
            'form.maximum_sks' => ['nullable', 'numeric', 'gte:form.sks_value'],
            'form.is_active' => ['boolean'],
            'form.description' => ['nullable', 'string'],
        ])['form'];

        $validated['updated_by'] = auth()->id();
        $validated['source_code'] = strtoupper($validated['source_code']);
        $validated['maximum_sks'] = $validated['maximum_sks'] === '' ? null : $validated['maximum_sks'];

        $this->rule->update($validated);
        session()->flash('success', 'Aturan SKS berhasil diperbarui.');
        $this->redirectRoute('admin.organization.lecturer-workload-rules.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Edit Aturan SKS']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Edit Aturan Konversi SKS"
        description="Perbarui nilai konversi atau batasan kuota untuk aturan: {{ $rule->name }} ({{ $rule->source_code }})"
        icon="edit"
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
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Perubahan Aturan Konversi</h4>
                            <div class="text-muted small">Sesuaikan poin konversi SKS, batasan kuota maksimal per item, atau status keaktifan.</div>
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
                            <h5 class="fw-bold mb-1">Pedoman Perubahan</h5>
                            <div class="text-muted small">Dampak pada laporan BKD.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Perubahan <strong>Nilai Konversi SKS</strong> hanya akan mempengaruhi kalkulasi pengajuan BKD baru atau yang belum dikunci pada periode aktif.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Jika regulasi pengakuan ini tidak lagi berlaku di semester sekarang, silakan nonaktifkan switch <strong>Status Aturan Aktif</strong>.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Keterangan Sumber</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Kode: {{ $rule->source_code }}</div>
                    <p class="text-muted small mb-0">Pastikan kode sumber tidak diubah sembarangan jika sudah diintegrasikan dengan modul pengajaran atau presensi internal.</p>
                </div>
            </div>
        </div>
    </div>
</div>
