<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\TuitionFee;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $academicYears = [];
    public array $studyPrograms = [];

    public array $form = [
        'academic_year_id' => '',
        'study_program_id' => '',
        'semester' => 1,
        'base_fee' => 0,
        'lab_fee' => 0,
        'library_fee' => 0,
        'activity_fee' => 0,
        'late_penalty_per_day' => 0,
        'payment_deadline' => '',
        'is_active' => 1,
        'notes' => '',
    ];

    public function mount(): void
    {
        $this->loadOptions();
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];

        TuitionFee::create([
            ...$validated,
            'is_active' => (bool) $validated['is_active'],
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Tarif biaya kuliah (SPP) berhasil dibuat.');
        $this->redirectRoute('admin.financial.tuition-fees.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Tambah Tarif Biaya Kuliah',
        ]);
    }

    private function loadOptions(): void
    {
        $this->academicYears = AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'code'])
            ->map(fn (AcademicYear $year) => ['id' => $year->id, 'label' => $year->name.' ('.$year->code.')'])
            ->toArray();

        $this->studyPrograms = StudyProgram::query()->orderBy('name')->get(['id', 'name', 'code'])
            ->map(fn (StudyProgram $program) => ['id' => $program->id, 'label' => $program->name.' ('.$program->code.')'])
            ->toArray();
    }

    private function rules(): array
    {
        return [
            'form.academic_year_id' => 'required|exists:academic_years,id',
            'form.study_program_id' => 'required|exists:study_programs,id',
            'form.semester' => [
                'required',
                'integer',
                'min:1',
                'max:14',
                Rule::unique('tuition_fees', 'semester')
                    ->where('academic_year_id', $this->form['academic_year_id'] ?? null)
                    ->where('study_program_id', $this->form['study_program_id'] ?? null),
            ],
            'form.base_fee' => 'required|numeric|min:0',
            'form.lab_fee' => 'nullable|numeric|min:0',
            'form.library_fee' => 'nullable|numeric|min:0',
            'form.activity_fee' => 'nullable|numeric|min:0',
            'form.late_penalty_per_day' => 'nullable|numeric|min:0',
            'form.payment_deadline' => 'required|date',
            'form.is_active' => 'required|boolean',
            'form.notes' => 'nullable|string|max:1000',
        ];
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Tambah Tarif Biaya Kuliah Baru"
        description="Tentukan besaran tarif SPP pokok serta rincian komponen biaya penunjang per semester dan program studi."
        icon="plus-circle"
    >
        <a href="{{ route('admin.financial.tuition-fees.index') }}" class="btn btn-sm btn-light text-secondary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </a>
    </x-admin.financial.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-money-check-dollar fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Formulir Konfigurasi Tarif SPP</h4>
                    <span class="text-muted small">Lengkapi rincian nominal biaya dasar dan komponen biaya tambahan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <form wire:submit.prevent="save">
                @include('components.admin.financial.tuition-fees._form')
            </form>
        </div>
    </div>
</div>
