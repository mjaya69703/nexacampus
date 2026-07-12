<?php

use App\Models\Organization\OrganizationalPosition;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = [];

    public function mount(): void
    {
        $this->form = [
            'name' => '',
            'code' => '',
            'category' => 'staff',
            'scope_type' => 'none',
            'description' => '',
            'is_active' => true,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        OrganizationalPosition::create([
            'name' => $validated['form']['name'],
            'code' => strtoupper($validated['form']['code']),
            'category' => $validated['form']['category'],
            'scope_type' => $validated['form']['scope_type'],
            'description' => $validated['form']['description'] ?: null,
            'is_active' => (bool) $validated['form']['is_active'],
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Jabatan berhasil dibuat.');
        $this->redirectRoute('admin.organization.organizational-positions.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.organizational-positions.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Tambah Jabatan',
        ]);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:50', Rule::unique('organizational_positions', 'code')],
            'form.category' => ['required', 'string', 'in:executive,faculty,study_program,work_unit,staff,academic'],
            'form.scope_type' => ['required', 'string', 'in:none,faculty,study_program,work_unit'],
            'form.description' => ['nullable', 'string'],
            'form.is_active' => ['boolean'],
        ];
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Tambah Jabatan Baru"
        description="Definisikan nama jabatan struktural atau fungsional baru beserta lingkup wewenang scope data yang melekat."
        icon="plus-circle"
    >
        <a href="{{ route('admin.organization.organizational-positions.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.organization.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-user-tie fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Master Jabatan Organisasi</h4>
                            <div class="text-muted small">Tentukan nama, kode unik, kategori, serta tipe scope kewenangan.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.organization.organizational-positions._form')
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
                            <h5 class="fw-bold mb-1">Panduan Scope</h5>
                            <div class="text-muted small">Kewenangan scope membatasi data yang dapat dikelola pejabat.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Tanpa Scope:</strong> Akses global untuk seluruh entitas kampus (Rektorat, Biro, dsb.).</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Scope Fakultas / Prodi:</strong> Pejabat hanya melihat data dosen dan mahasiswa pada lingkup fakultas/prodi yang ditugaskan.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Scope Unit Kerja:</strong> Teratas untuk pimpinan unit operasional atau lembaga/biro.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Awal</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Aktif</div>
                    <p class="text-muted small mb-0">Jabatan yang baru disimpan langsung siap digunakan pada form penugasan pegawai.</p>
                </div>
            </div>
        </div>
    </div>
</div>
