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

        session()->flash('success', 'Invoice schedule berhasil dibuat.');
        $this->redirectRoute('admin.financial.invoice-schedules.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Create Invoice Schedule',
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

<div class="row">
    <div class="col-12">
        <x-alert />
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="card-title mb-0">Create Invoice Schedule</h3>
                    <small class="text-muted">Schedule invoice generation and publishing from the admin dashboard.</small>
                </div>
                <a href="{{ route('admin.financial.invoice-schedules.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    @include('components.admin.financial.invoice-schedules._form')
                </form>
            </div>
        </div>
    </div>
</div>
