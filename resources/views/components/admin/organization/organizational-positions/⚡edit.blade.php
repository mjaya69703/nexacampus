<?php

use App\Models\Organization\OrganizationalPosition;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public OrganizationalPosition $position;
    public array $form = [];

    public function mount(int $id): void
    {
        $this->position = OrganizationalPosition::findOrFail($id);
        $this->form = [
            'name' => $this->position->name,
            'code' => $this->position->code,
            'category' => $this->position->category,
            'scope_type' => $this->position->scope_type,
            'description' => $this->position->description,
            'is_active' => (bool) $this->position->is_active,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        $this->position->update([
            'name' => $validated['form']['name'],
            'code' => strtoupper($validated['form']['code']),
            'category' => $validated['form']['category'],
            'scope_type' => $validated['form']['scope_type'],
            'description' => $validated['form']['description'] ?: null,
            'is_active' => (bool) $validated['form']['is_active'],
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Jabatan berhasil diperbarui.');
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
            'pages' => 'Edit Jabatan',
        ]);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:50', Rule::unique('organizational_positions', 'code')->ignore($this->position->id)],
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
        title="Edit Jabatan Organisasi"
        description="Perbarui informasi master jabatan untuk: {{ $position->name }} ({{ $position->code }})"
        icon="edit"
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
                            <i class="fa fa-edit fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Perubahan Data Jabatan</h4>
                            <div class="text-muted small">Sesuaikan nama, kategori, tipe lingkup kewenangan (scope), atau status keaktifan.</div>
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
                            <h5 class="fw-bold mb-1">Informasi Jabatan</h5>
                            <div class="text-muted small">Dampak perubahan parameter master jabatan.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Perubahan tipe scope akan membatasi atau memperluas pilihan entitas saat penugasan baru dibuat.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Kode jabatan bersifat unik dan digunakan oleh sistem untuk validasi hak akses khusus.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Keaktifan</div>
                    <div class="fw-bold fs-5 {{ $position->is_active ? 'text-success' : 'text-danger' }} mb-2">{{ $position->is_active ? 'Aktif' : 'Nonaktif' }}</div>
                    <p class="text-muted small mb-0">Jika dinonaktifkan, jabatan ini tidak dapat dipilih lagi saat membuat penugasan pegawai baru.</p>
                </div>
            </div>
        </div>
    </div>
</div>
