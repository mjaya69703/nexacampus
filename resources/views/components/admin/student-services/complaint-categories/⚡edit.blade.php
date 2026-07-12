<?php

use App\Models\Organization\WorkUnit;
use App\Models\StudentService\StudentComplaintCategory;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public StudentComplaintCategory $category;
    public array $form = [];

    public function mount(int $id): void
    {
        $this->category = StudentComplaintCategory::findOrFail($id);
        $this->form = [
            'name' => $this->category->name,
            'code' => $this->category->code,
            'default_work_unit_id' => $this->category->default_work_unit_id,
            'description' => $this->category->description,
            'default_sla_hours' => $this->category->default_sla_hours,
            'is_active' => (bool) $this->category->is_active,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:30', Rule::unique('student_complaint_categories', 'code')->ignore($this->category->id)],
            'form.default_work_unit_id' => ['nullable', 'exists:work_units,id'],
            'form.description' => ['nullable', 'string'],
            'form.default_sla_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'form.is_active' => ['boolean'],
        ]);

        $data = $validated['form'];
        $data['code'] = strtoupper($data['code']);
        $data['default_work_unit_id'] = $data['default_work_unit_id'] ?: null;
        $this->category->update($data);

        session()->flash('success', 'Kategori pengaduan berhasil diperbarui.');
        $this->redirectRoute('admin.student-services.complaint-categories.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.student-services.complaint-categories.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Layanan Mahasiswa', 'pages' => 'Edit Kategori Pengaduan']);
    }

    public function getWorkUnitsProperty()
    {
        return WorkUnit::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <x-admin.student-services.header
        title="Edit Kategori Pengaduan: {{ $category->name }}"
        description="Perbarui spesifikasi SLA, kode, dan penanggung jawab kategori pengaduan ini untuk penyesuaian operasional."
        icon="tags"
    >
        <a href="{{ route('admin.student-services.complaint-categories.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.student-services.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-tags fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Perubahan Kategori</h4>
                            <div class="text-muted small">Perbarui data spesifikasi kategori tiket pengaduan.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.student-services.complaint-categories._form')
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
                            <h5 class="fw-bold mb-1">Panduan SLA & Unit</h5>
                            <div class="text-muted small">Pengaturan alur penyelesaian tiket otomatis.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Perubahan SLA hanya berlaku untuk tiket baru yang masuk setelah pembaruan disimpan.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Unit Kerja Default yang diubah akan menjadi tujuan penugasan otomatis untuk tiket baru berikutnya.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Nonaktifkan kategori jika sudah tidak digunakan lagi agar form mahasiswa tetap ringkas.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Kategori</div>
                    <div class="fw-bold fs-5 text-dark mb-2">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</div>
                    <p class="text-muted small mb-0">Diperbarui terakhir pada {{ $category->updated_at ? $category->updated_at->format('d/m/Y H:i') : '-' }}.</p>
                </div>
            </div>
        </div>
    </div>
</div>
