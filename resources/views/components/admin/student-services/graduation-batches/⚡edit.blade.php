<?php

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\StudyProgram;
use App\Models\StudentService\GraduationBatch;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public GraduationBatch $batch;

    public array $form = [];

    public $academicPeriods;

    public $studyPrograms;

    public function mount($id): void
    {
        $this->batch = GraduationBatch::findOrFail($id);
        $this->academicPeriods = AcademicPeriod::query()
            ->with('academicYear')
            ->where('type', 'Yudisium')
            ->orderByDesc('start_at')
            ->get();
        $this->studyPrograms = StudyProgram::query()->orderBy('name')->get(['id', 'name']);
        $this->form = [
            'academic_period_id' => $this->batch->academic_period_id,
            'study_program_id' => $this->batch->study_program_id,
            'name' => $this->batch->name,
            'code' => $this->batch->code,
            'yudisium_date' => $this->batch->yudisium_date?->format('Y-m-d'),
            'sk_number' => $this->batch->sk_number,
            'sk_date' => $this->batch->sk_date?->format('Y-m-d'),
            'status' => $this->batch->status,
            'notes' => $this->batch->notes,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];
        $validated['study_program_id'] = $validated['study_program_id'] ?: null;
        $validated['yudisium_date'] = $validated['yudisium_date'] ?: null;
        $validated['sk_number'] = $validated['sk_number'] ?: null;
        $validated['sk_date'] = $validated['sk_date'] ?: null;
        $validated['notes'] = $validated['notes'] ?: null;

        $this->batch->update($validated);
        session()->flash('success', 'Graduation batch berhasil diperbarui.');
        $this->redirectRoute('admin.student-services.graduation-batches.show', ['id' => $this->batch->id]);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Edit Batch Yudisium',
        ]);
    }

    private function rules(): array
    {
        return [
            'form.academic_period_id' => ['required', 'integer', 'exists:academic_periods,id'],
            'form.study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:100', Rule::unique('graduation_batches', 'code')->ignore($this->batch->id)],
            'form.yudisium_date' => ['nullable', 'date'],
            'form.sk_number' => ['nullable', 'string', 'max:255'],
            'form.sk_date' => ['nullable', 'date'],
            'form.status' => ['required', 'in:draft,open,review,finalized,cancelled'],
            'form.notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <x-admin.student-services.header
        title="Edit Batch Yudisium: {{ $batch->name }}"
        description="Perbarui spesifikasi, jadwal pelaksanaan yudisium resmi, atau status gelombang kelulusan ini."
        icon="layer-group"
    >
        <a href="{{ route('admin.student-services.graduation-batches.show', ['id' => $batch->id]) }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke detail</span>
        </a>
    </x-admin.student-services.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-layer-group fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Perubahan Batch</h4>
                            <div class="text-muted small">Perbarui parameter dan status pelaksanaan gelombang yudisium.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.student-services.graduation-batches._form')
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
                            <h5 class="fw-bold mb-1">Panduan Status Batch</h5>
                            <div class="text-muted small">Alur perubahan status gelombang.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Status <strong>Open</strong> mengizinkan mahasiswa baru mengirimkan berkas pengajuan yudisium.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Ubah ke status <strong>Review</strong> apabila kuota sudah terpenuhi dan operator fokus memverifikasi berkas yang masuk.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Tanggal Yudisium Resmi yang diperbarui di sini akan digunakan oleh sistem saat Anda menekan tombol finalisasi kelulusan.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Batch Saat Ini</div>
                    <div class="fw-bold fs-5 text-dark mb-2">{{ str($batch->status)->title() }}</div>
                    <p class="text-muted small mb-0">Total pengajuan terdaftar: {{ $batch->applications()->count() }} mahasiswa.</p>
                </div>
            </div>
        </div>
    </div>
</div>
