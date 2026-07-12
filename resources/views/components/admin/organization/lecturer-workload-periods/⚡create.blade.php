<?php

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use App\Models\Organization\LecturerWorkloadPeriod;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = [
        'academic_year_id' => '',
        'academic_period_id' => '',
        'name' => '',
        'code' => '',
        'starts_at' => '',
        'ends_at' => '',
        'status' => 'draft',
        'minimum_sks' => '12',
        'maximum_sks' => '16',
        'notes' => '',
    ];

    public function save(): void
    {
        $validated = $this->validate([
            'form.academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'form.academic_period_id' => ['nullable', 'integer', 'exists:academic_periods,id'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:80', Rule::unique('lecturer_workload_periods', 'code')],
            'form.starts_at' => ['nullable', 'date'],
            'form.ends_at' => ['nullable', 'date', 'after_or_equal:form.starts_at'],
            'form.status' => ['required', 'in:draft,open,review,closed'],
            'form.minimum_sks' => ['required', 'numeric', 'min:0'],
            'form.maximum_sks' => ['required', 'numeric', 'gte:form.minimum_sks'],
            'form.notes' => ['nullable', 'string'],
        ])['form'];

        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();
        $validated['academic_year_id'] = $validated['academic_year_id'] ?: null;
        $validated['academic_period_id'] = $validated['academic_period_id'] ?: null;

        LecturerWorkloadPeriod::create($validated);
        session()->flash('success', 'Periode BKD berhasil dibuat.');
        $this->redirectRoute('admin.organization.lecturer-workload-periods.index');
    }

    public function render()
    {
        return $this->view([
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
            'academicPeriods' => AcademicPeriod::orderByDesc('start_at')->get(),
        ])->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Tambah Periode BKD']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Tambah Periode BKD"
        description="Buka siklus periode baru untuk pengajuan dan proses verifikasi laporan Beban Kerja Dosen (BKD)."
        icon="calendar-plus"
    >
        <a href="{{ route('admin.organization.lecturer-workload-periods.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.organization.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-calendar-check fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Pengaturan Periode BKD</h4>
                            <div class="text-muted small">Tentukan nama periode, rentang waktu pengajuan, serta batas minimum/maksimum SKS yang diwajibkan.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.organization.lecturer-workload-periods._form')
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
                            <h5 class="fw-bold mb-1">Pedoman Siklus BKD</h5>
                            <div class="text-muted small">Regulasi pembukaan periode.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Sesuai standar UU Guru & Dosen, batas minimum kewajiban adalah <strong>12 SKS</strong> dan batas maksimum diakui adalah <strong>16 SKS</strong> per semester.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pilih <strong>Status Dibuka (Open)</strong> jika Anda ingin para dosen langsung dapat mengisi klaim aktivitas BKD pada rentang tanggal tersebut.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Tautkan periode dengan <strong>Tahun Akademik</strong> dan <strong>Semester</strong> untuk mengelompokkan laporan secara sistematis.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Manajemen Waktu</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Penutupan Otomatis</div>
                    <p class="text-muted small mb-0">Jika melewati tanggal deadline (Ends At), sistem tetap mengizinkan admin mengubah status menjadi "Masa Review" untuk melarang pengajuan baru dari dosen.</p>
                </div>
            </div>
        </div>
    </div>
</div>
