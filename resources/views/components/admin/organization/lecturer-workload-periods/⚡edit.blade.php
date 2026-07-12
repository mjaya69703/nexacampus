<?php

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use App\Models\Organization\LecturerWorkloadPeriod;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public LecturerWorkloadPeriod $period;
    public array $form = [];

    public function mount(int $id): void
    {
        $this->period = LecturerWorkloadPeriod::findOrFail($id);
        $this->form = [
            'academic_year_id' => (string) $this->period->academic_year_id,
            'academic_period_id' => (string) $this->period->academic_period_id,
            'name' => $this->period->name,
            'code' => $this->period->code,
            'starts_at' => $this->period->starts_at?->toDateString(),
            'ends_at' => $this->period->ends_at?->toDateString(),
            'status' => $this->period->status,
            'minimum_sks' => (string) $this->period->minimum_sks,
            'maximum_sks' => (string) $this->period->maximum_sks,
            'notes' => $this->period->notes,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'form.academic_period_id' => ['nullable', 'integer', 'exists:academic_periods,id'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:80', Rule::unique('lecturer_workload_periods', 'code')->ignore($this->period->id)],
            'form.starts_at' => ['nullable', 'date'],
            'form.ends_at' => ['nullable', 'date', 'after_or_equal:form.starts_at'],
            'form.status' => ['required', 'in:draft,open,review,closed'],
            'form.minimum_sks' => ['required', 'numeric', 'min:0'],
            'form.maximum_sks' => ['required', 'numeric', 'gte:form.minimum_sks'],
            'form.notes' => ['nullable', 'string'],
        ])['form'];

        $validated['updated_by'] = auth()->id();
        $validated['academic_year_id'] = $validated['academic_year_id'] ?: null;
        $validated['academic_period_id'] = $validated['academic_period_id'] ?: null;

        $this->period->update($validated);
        session()->flash('success', 'Periode BKD berhasil diperbarui.');
        $this->redirectRoute('admin.organization.lecturer-workload-periods.index');
    }

    public function render()
    {
        return $this->view([
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
            'academicPeriods' => AcademicPeriod::orderByDesc('start_at')->get(),
        ])->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Edit Periode BKD']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Edit Periode BKD"
        description="Sesuaikan jadwal, status siklus, atau ketentuan SKS untuk periode BKD: {{ $period->name }} ({{ $period->code }})"
        icon="edit"
    >
        <a href="{{ route('admin.organization.lecturer-workload-periods.show', $period->id) }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke detail</span>
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
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Perubahan Periode BKD</h4>
                            <div class="text-muted small">Perbarui status pembukaan, masa review, atau tautan tahun/periode akademik.</div>
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
                            <h5 class="fw-bold mb-1">Pedoman Perubahan</h5>
                            <div class="text-muted small">Informasi update periode.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Mengubah status menjadi <strong>Masa Review (Review)</strong> akan melarang penambahan item BKD baru oleh dosen, namun memperbolehkan asesor melakukan verifikasi dan penilaian.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Mengubah status menjadi <strong>Ditutup (Closed)</strong> akan mengunci seluruh pengajuan pada periode ini secara permanen.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Rentang SKS</div>
                    <div class="fw-bold fs-5 text-dark mb-2">{{ $period->minimum_sks }} - {{ $period->maximum_sks }} SKS</div>
                    <p class="text-muted small mb-0">Ketentuan minimum dan maksimum SKS ini digunakan sebagai referensi kelulusan pelaporan BKD pada halaman pengajuan dan ringkasan asesor.</p>
                </div>
            </div>
        </div>
    </div>
</div>
