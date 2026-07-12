<?php

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use App\Models\Organization\EdomPeriod;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = ['academic_year_id' => '', 'academic_period_id' => '', 'name' => '', 'code' => '', 'starts_at' => '', 'ends_at' => '', 'status' => 'draft', 'minimum_responses' => 3, 'notes' => ''];

    public function save(): void
    {
        $data = $this->validate([
            'form.academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'form.academic_period_id' => ['nullable', 'integer', 'exists:academic_periods,id'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:80', Rule::unique('edom_periods', 'code')],
            'form.starts_at' => ['nullable', 'date'],
            'form.ends_at' => ['nullable', 'date', 'after_or_equal:form.starts_at'],
            'form.status' => ['required', 'in:draft,open,closed'],
            'form.minimum_responses' => ['required', 'integer', 'min:1'],
            'form.notes' => ['nullable', 'string'],
        ])['form'];

        $data['academic_year_id'] = $data['academic_year_id'] ?: null;
        $data['academic_period_id'] = $data['academic_period_id'] ?: null;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        EdomPeriod::create($data);
        session()->flash('success', 'Periode EDOM berhasil dibuat.');
        $this->redirectRoute('admin.organization.edom-periods.index');
    }

    public function render()
    {
        return $this->view(['academicYears' => AcademicYear::orderByDesc('start_date')->get(), 'academicPeriods' => AcademicPeriod::orderByDesc('start_at')->get()])->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Tambah Periode EDOM']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Tambah Periode EDOM"
        description="Buat jadwal periode baru untuk pengisian Evaluasi Dosen Oleh Mahasiswa pada semester atau rentang waktu tertentu."
        icon="calendar-plus"
    >
        <a href="{{ route('admin.organization.edom-periods.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.organization.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <form wire:submit.prevent="save" class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-calendar-days fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Periode EDOM</h4>
                            <div class="text-muted small">Lengkapi parameter waktu, status, dan batas respons kuesioner evaluasi.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.organization.edom-periods._form')
                </div>
                <div class="card-footer bg-light bg-opacity-50 border-top p-4 d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.organization.edom-periods.index') }}" class="btn btn-light rounded-pill px-4 fw-medium d-inline-flex align-items-center gap-2">
                        <i class="fa fa-times"></i> <span>Batal</span>
                    </a>
                    <button type="submit" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium d-inline-flex align-items-center gap-2">
                        <i class="fa fa-save"></i> <span>Simpan Periode</span>
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
                            <h5 class="fw-bold mb-1">Panduan Pengisian</h5>
                            <div class="text-muted small">Ketentuan pembukaan periode EDOM.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Rentang Waktu</strong>: Tentukan tanggal mulai dan selesai yang sesuai dengan kalender akademik perguruan tinggi agar mahasiswa dapat mengisi tepat waktu.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Minimal Respon</strong>: Angka batas minimal suara mahasiswa yang valid agar skor evaluasi dosen tidak bias oleh jumlah sampel yang terlalu sedikit.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Status Periode</strong>: Gunakan status <em>Dibuka</em> hanya jika siap menerima respons mahasiswa dari portal akademik.</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
