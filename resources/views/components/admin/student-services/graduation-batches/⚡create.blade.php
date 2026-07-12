<?php

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\StudyProgram;
use App\Models\StudentService\GraduationBatch;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = [
        'academic_period_id' => '',
        'study_program_id' => '',
        'name' => '',
        'code' => '',
        'yudisium_date' => '',
        'sk_number' => '',
        'sk_date' => '',
        'status' => 'draft',
        'notes' => '',
    ];

    public $academicPeriods;

    public $studyPrograms;

    public function mount(): void
    {
        $this->academicPeriods = AcademicPeriod::query()
            ->with('academicYear')
            ->where('type', 'Yudisium')
            ->orderByDesc('start_at')
            ->get();
        $this->studyPrograms = StudyProgram::query()->orderBy('name')->get(['id', 'name']);
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];
        $validated['study_program_id'] = $validated['study_program_id'] ?: null;
        $validated['yudisium_date'] = $validated['yudisium_date'] ?: null;
        $validated['sk_number'] = $validated['sk_number'] ?: null;
        $validated['sk_date'] = $validated['sk_date'] ?: null;
        $validated['notes'] = $validated['notes'] ?: null;

        GraduationBatch::create($validated);
        session()->flash('success', 'Graduation batch berhasil dibuat.');
        $this->redirectRoute('admin.student-services.graduation-batches.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Buat Batch Yudisium',
        ]);
    }

    private function rules(): array
    {
        return [
            'form.academic_period_id' => ['required', 'integer', 'exists:academic_periods,id'],
            'form.study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:100', Rule::unique('graduation_batches', 'code')],
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
        title="Buat Batch Yudisium Baru"
        description="Tentukan periode akademik, jadwal pelaksanaan yudisium resmi, dan lingkup program studi untuk gelombang kelulusan."
        icon="layer-group"
    >
        <a href="{{ route('admin.student-services.graduation-batches.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
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
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Batch Yudisium</h4>
                            <div class="text-muted small">Lengkapi data pelaksanaan gelombang kelulusan mahasiswa.</div>
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
                            <h5 class="fw-bold mb-1">Panduan Gelombang</h5>
                            <div class="text-muted small">Pengelolaan pendaftaran dan finalisasi yudisium.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pastikan memilih Periode Akademik khusus bertipe <strong>Yudisium</strong> yang aktif.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Gunakan status <strong>Open</strong> ketika gelombang siap menerima pendaftaran mahasiswa secara mandiri.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Tanggal Yudisium Resmi wajib diset jika ingin melakukan finalisasi serentak pada tahap berikutnya.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Awal</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Draft</div>
                    <p class="text-muted small mb-0">Secara default batch baru dibuat dalam status Draft agar Anda bisa memeriksa kembali parameter sebelum dibuka untuk mahasiswa.</p>
                </div>
            </div>
        </div>
    </div>
</div>
