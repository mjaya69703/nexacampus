<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentProfile;
use App\Models\Financial\InvoiceSchedule;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $academicYears = [];

    public string $studentSearch = '';

    public array $selectedStudentProfileIds = [];

    public array $form = [
        'name' => '',
        'invoice_kind' => 'tuition',
        'generation_mode' => 'active_students',
        'student_profile_id' => '',
        'academic_year_id' => '',
        'semester' => 1,
        'invoice_type' => 'tuition',
        'publish_at' => '',
        'due_date' => '',
        'issue_immediately' => true,
        'is_active' => true,
        'notes' => '',
    ];

    public array $items = [
        ['item_type' => 'fee', 'description' => '', 'amount' => null],
    ];

    public function mount(): void
    {
        $this->academicYears = AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'code'])
            ->map(fn (AcademicYear $year) => ['id' => $year->id, 'label' => $year->name.' ('.$year->code.')'])
            ->toArray();
    }

    public function updatedFormInvoiceKind(string $value): void
    {
        if ($value === 'tuition') {
            $this->form['invoice_type'] = 'tuition';
            $this->form['issue_immediately'] = true;
        } elseif (($this->form['invoice_type'] ?? 'tuition') === 'tuition') {
            $this->form['invoice_type'] = 'custom';
        }
    }

    public function addItem(): void
    {
        $this->items[] = ['item_type' => 'fee', 'description' => '', 'amount' => null];
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) <= 1) {
            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];

        InvoiceSchedule::create([
            ...$validated,
            'student_profile_id' => $validated['generation_mode'] === 'single' ? $validated['student_profile_id'] : null,
            'student_profile_ids' => $validated['generation_mode'] === 'selected_students' ? array_values($this->selectedStudentProfileIds) : null,
            'academic_year_id' => filled($validated['academic_year_id']) ? $validated['academic_year_id'] : null,
            'semester' => filled($validated['semester']) ? (int) $validated['semester'] : null,
            'items' => $validated['invoice_kind'] === 'custom' ? $this->cleanItems() : null,
            'is_active' => (bool) $validated['is_active'],
            'issue_immediately' => (bool) $validated['issue_immediately'],
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Jadwal penerbitan tagihan berhasil dibuat.');
        $this->redirectRoute('admin.financial.invoice-schedules.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Buat Jadwal Penerbitan Tagihan',
        ]);
    }

    public function studentOptions(): array
    {
        return StudentProfile::query()
            ->with(['user', 'studyProgram'])
            ->where('is_active', true)
            ->when($this->studentSearch !== '', function ($query) {
                $search = '%'.$this->studentSearch.'%';

                $query->where(function ($query) use ($search) {
                    $query->where('nim', 'like', $search)
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhere('email', 'like', $search));
                });
            })
            ->orderBy('nim')
            ->limit(25)
            ->get()
            ->map(fn (StudentProfile $student) => [
                'id' => $student->id,
                'label' => ($student->nim ?? '-').' - '.$student->user?->name.' ('.$student->studyProgram?->name.')',
            ])
            ->toArray();
    }

    private function rules(): array
    {
        $isTuition = ($this->form['invoice_kind'] ?? 'tuition') === 'tuition';

        $rules = [
            'form.name' => ['required', 'string', 'max:255'],
            'form.invoice_kind' => ['required', Rule::in(['tuition', 'custom'])],
            'form.generation_mode' => ['required', Rule::in(['single', 'selected_students', 'active_students'])],
            'form.student_profile_id' => ['required_if:form.generation_mode,single', 'nullable', 'exists:student_profiles,id'],
            'selectedStudentProfileIds' => ['required_if:form.generation_mode,selected_students', 'array'],
            'selectedStudentProfileIds.*' => ['exists:student_profiles,id'],
            'form.academic_year_id' => [$isTuition ? 'required' : 'nullable', 'exists:academic_years,id'],
            'form.semester' => [$isTuition ? 'required' : 'nullable', 'integer', 'min:1', 'max:14'],
            'form.invoice_type' => ['required', Rule::in(array_keys(config('financial.invoice_types', [])))],
            'form.publish_at' => ['required', 'date'],
            'form.due_date' => ['required', 'date', 'after_or_equal:today'],
            'form.issue_immediately' => ['required', 'boolean'],
            'form.is_active' => ['required', 'boolean'],
            'form.notes' => ['nullable', 'string', 'max:1000'],
        ];

        if (! $isTuition) {
            $rules += [
                'items' => ['required', 'array', 'min:1'],
                'items.*.item_type' => ['required', Rule::in(['fee', 'discount', 'adjustment', 'penalty'])],
                'items.*.description' => ['required', 'string', 'max:255'],
                'items.*.amount' => ['required', 'numeric', 'min:1'],
            ];
        }

        return $rules;
    }

    private function cleanItems(): array
    {
        return collect($this->items)
            ->map(fn (array $item) => [
                'item_type' => $item['item_type'] ?? 'fee',
                'description' => $item['description'] ?? '',
                'amount' => (float) ($item['amount'] ?? 0),
            ])
            ->filter(fn (array $item) => filled($item['description']) && $item['amount'] > 0)
            ->values()
            ->all();
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Buat Jadwal Penerbitan Tagihan Baru"
        description="Konfigurasi parameter pembuatan invoice massal secara otomatis untuk spesifik mahasiswa, batch, atau seluruh angkatan."
        icon="plus-circle"
    >
        <a href="{{ route('admin.financial.invoice-schedules.index') }}" class="btn btn-sm btn-light text-secondary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </a>
    </x-admin.financial.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-clock fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Formulir Konfigurasi Penjadwalan</h4>
                    <span class="text-muted small">Tentukan jenis tagihan, target mahasiswa, dan tanggal publikasi otomatis.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <form wire:submit.prevent="save">
                @include('components.admin.financial.invoice-schedules._form')
            </form>
        </div>
    </div>
</div>
