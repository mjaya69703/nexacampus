<?php

use App\Models\Organization\LecturerPerformanceRubric;
use Livewire\Component;

new class extends Component
{
    public array $form = [
        'code' => '',
        'name' => '',
        'edom_weight' => 40,
        'teaching_weight' => 30,
        'attendance_weight' => 20,
        'workload_weight' => 10,
        'minimum_responses' => 3,
        'target_workload_sks' => 12,
        'is_active' => true,
        'notes' => '',
    ];

    public function save(): void
    {
        $data = $this->validate([
            'form.code' => ['required', 'string', 'max:50', 'unique:lecturer_performance_rubrics,code'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.edom_weight' => ['required', 'numeric', 'min:0'],
            'form.teaching_weight' => ['required', 'numeric', 'min:0'],
            'form.attendance_weight' => ['required', 'numeric', 'min:0'],
            'form.workload_weight' => ['required', 'numeric', 'min:0'],
            'form.minimum_responses' => ['required', 'integer', 'min:1'],
            'form.target_workload_sks' => ['required', 'numeric', 'min:1'],
            'form.is_active' => ['boolean'],
            'form.notes' => ['nullable', 'string'],
        ])['form'];

        if ($data['is_active']) {
            LecturerPerformanceRubric::query()->update(['is_active' => false]);
        }

        $data['code'] = strtoupper($data['code']);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        LecturerPerformanceRubric::create($data);
        session()->flash('success', 'Rubrik performa berhasil dibuat.');
        $this->redirectRoute('admin.organization.lecturer-performance-rubrics.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Tambah Rubrik Performa']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Tambah Rubrik Performa Dosen"
        description="Buat standar parameter penilaian baru dengan mengatur proporsi bobot EDOM, kepatuhan mengajar, absensi, dan realisasi beban kerja (BKD)."
        icon="plus-circle"
    >
        <a href="{{ route('admin.organization.lecturer-performance-rubrics.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.organization.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <form wire:submit.prevent="save" class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-scale-balanced fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Bobot & Aturan Penilaian</h4>
                            <div class="text-muted small">Pastikan total kombinasi persentase bobot penilaian bernilai logis dan sesuai kebijakan akademik.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.organization.lecturer-performance-rubrics._form')
                </div>
                <div class="card-footer bg-light bg-opacity-50 border-top p-4 d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.organization.lecturer-performance-rubrics.index') }}" class="btn btn-light rounded-pill px-4 fw-medium d-inline-flex align-items-center gap-2">
                        <i class="fa fa-times"></i> <span>Batal</span>
                    </a>
                    <button type="submit" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium d-inline-flex align-items-center gap-2">
                        <i class="fa fa-save"></i> <span>Simpan Rubrik</span>
                    </button>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-circle-info"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Pedoman Bobot</h5>
                            <div class="text-muted small">Panduan kalkulasi rubrik.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Total Bobot Ideal 100%</strong>: Secara umum, penjumlahan bobot EDOM, Mengajar, Absensi, dan BKD disarankan bernilai tepat 100%.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Rubrik Aktif</strong>: Jika rubrik baru ini ditandai sebagai aktif, maka rubrik aktif sebelumnya akan otomatis dinonaktifkan oleh sistem.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Total Bobot Saat Ini</div>
                    <div class="fw-bold fs-3 text-primary mb-2">{{ ((float)$form['edom_weight'] + (float)$form['teaching_weight'] + (float)$form['attendance_weight'] + (float)$form['workload_weight']) }}%</div>
                    <p class="text-muted small mb-0">Total bobot dihitung secara realtime dari 4 komponen penilaian yang Anda masukkan di samping.</p>
                </div>
            </div>
        </div>
    </div>
</div>
