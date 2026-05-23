<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentProfile;
use App\Support\Financial\InvoiceGenerationService;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public array $academicYears = [];

    public string $studentSearch = '';

    public array $selectedStudentProfileIds = [];

    public array $form = [
        'invoice_kind' => 'tuition',
        'generation_mode' => 'single',
        'student_profile_id' => '',
        'academic_year_id' => '',
        'semester' => 1,
        'due_date' => '',
        'invoice_type' => 'tuition',
        'notes' => '',
        'issue_immediately' => true,
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
        $this->form['issue_immediately'] = $value === 'tuition';

        if ($value === 'tuition') {
            $this->form['invoice_type'] = 'tuition';
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

    public function save(InvoiceGenerationService $generationService): void
    {
        $validated = $this->validate($this->rules())['form'];
        $academicYear = $validated['academic_year_id'] ? AcademicYear::find($validated['academic_year_id']) : null;
        $students = $this->studentsForGeneration($validated);
        $created = 0;
        $errors = [];

        if ($students->isEmpty()) {
            session()->flash('error', 'Tidak ada student profile aktif yang cocok dengan target invoice. Invoice hanya bisa dibuat untuk mahasiswa yang sudah punya student profile.');

            return;
        }

        foreach ($students as $student) {
            try {
                if ($validated['invoice_kind'] === 'tuition') {
                    $generationService->generateForStudent(
                        $student,
                        $academicYear,
                        (int) $validated['semester'],
                        $validated['due_date'] ?: null,
                        auth()->id(),
                        (bool) $validated['issue_immediately'],
                    );
                } else {
                    $generationService->createCustomInvoice(
                        $student,
                        collect($this->items)->filter(fn (array $item) => filled($item['description']) && (float) $item['amount'] > 0)->values()->all(),
                        $validated['invoice_type'],
                        $validated['due_date'],
                        $academicYear,
                        filled($validated['semester']) ? (int) $validated['semester'] : null,
                        $validated['notes'] ?: null,
                        auth()->id(),
                        (bool) $validated['issue_immediately'],
                    );
                }

                $created++;
            } catch (\Throwable $exception) {
                $errors[] = ($student->nim ?? $student->id).': '.$exception->getMessage();
            }
        }

        if ($created > 0) {
            session()->flash('success', $created.' invoice berhasil dibuat.');
        }

        if (! empty($errors)) {
            session()->flash('error', implode(' ', array_slice($errors, 0, 5)));
        }

        $this->redirectRoute('admin.financial.student-invoices.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Create Student Invoice',
        ]);
    }

    public function eligibleBulkStudentsCount(): int
    {
        return $this->studentsForGeneration([
            'generation_mode' => 'active_students',
            'academic_year_id' => $this->form['academic_year_id'] ?: null,
            'semester' => $this->form['semester'] ?: null,
        ])->count();
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
        $isCustomInvoice = ($this->form['invoice_kind'] ?? 'tuition') === 'custom';
        $invoiceType = $isCustomInvoice ? ($this->form['invoice_type'] ?? 'custom') : 'tuition';
        $academicYearRule = in_array($invoiceType, ['tuition', 'registration', 'exam'], true)
            ? 'required|exists:academic_years,id'
            : 'nullable|exists:academic_years,id';

        $rules = [
            'form.invoice_kind' => 'required|in:tuition,custom',
            'form.generation_mode' => 'required|in:single,selected_students,active_students',
            'form.student_profile_id' => 'required_if:form.generation_mode,single|nullable|exists:student_profiles,id',
            'selectedStudentProfileIds' => 'required_if:form.generation_mode,selected_students|array',
            'selectedStudentProfileIds.*' => 'exists:student_profiles,id',
            'form.academic_year_id' => $academicYearRule,
            'form.semester' => 'required_if:form.invoice_kind,tuition|nullable|integer|min:1|max:14',
            'form.due_date' => 'required_if:form.invoice_kind,custom|nullable|date',
            'form.invoice_type' => 'required|in:tuition,custom,admission,registration,leave,transfer,graduation,exam,library_fine,certificate,other',
            'form.notes' => 'nullable|string|max:1000',
            'form.issue_immediately' => 'required|boolean',
        ];

        if ($isCustomInvoice) {
            $rules += [
                'items' => 'required|array|min:1',
                'items.*.item_type' => 'required|in:fee,discount,adjustment,penalty',
                'items.*.description' => 'required|string|max:255',
                'items.*.amount' => 'required|numeric|min:0.01',
            ];
        }

        return $rules;
    }

    private function studentsForGeneration(array $validated): Collection
    {
        if ($validated['generation_mode'] === 'single') {
            return StudentProfile::query()
                ->with(['user', 'studyProgram'])
                ->whereKey($validated['student_profile_id'])
                ->get();
        }

        if ($validated['generation_mode'] === 'selected_students') {
            return StudentProfile::query()
                ->with(['user', 'studyProgram'])
                ->where('is_active', true)
                ->whereKey($this->selectedStudentProfileIds)
                ->get();
        }

        return StudentProfile::query()
            ->with(['user', 'studyProgram'])
            ->where('is_active', true)
            ->when($validated['semester'] ?? null, fn ($query, $semester) => $query->where('current_semester', (int) $semester))
            ->when($validated['academic_year_id'] ?? null, function ($query, $academicYearId) use ($validated) {
                $query->whereHas('registrations', function ($query) use ($academicYearId, $validated) {
                    $query->where('academic_year_id', $academicYearId)
                        ->when($validated['semester'] ?? null, fn ($query, $semester) => $query->where('semester_no', $semester))
                        ->where('registration_status', 'Approved')
                        ->where('is_active', true);
                });
            })
            ->get();
    }
};
?>

<div class="row">
    <div class="col-lg-8">
        <x-alert />
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="card-title mb-0">Create Student Invoice</h3>
                    <small class="text-muted">Generate tuition invoice or create custom manual invoice.</small>
                </div>
                <a href="{{ route('admin.financial.student-invoices.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    @if (StudentProfile::where('is_active', true)->doesntExist())
                        <div class="alert alert-warning">
                            <div class="d-flex gap-2">
                                <i class="fas fa-triangle-exclamation mt-1"></i>
                                <div>
                                    <strong>Belum ada student profile aktif.</strong>
                                    Invoice financial tidak dibuat dari user biasa, tapi dari <code>student_profiles</code>. Convert/admission-kan mahasiswa dulu atau aktifkan student profile sebelum membuat invoice.
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Invoice Kind</label>
                            <select class="form-control" wire:model.live="form.invoice_kind">
                                <option value="tuition">Tuition Template</option>
                                <option value="custom">Custom / Manual</option>
                            </select>
                            @error('form.invoice_kind') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mode</label>
                            <select class="form-control" wire:model.live="form.generation_mode">
                                <option value="single">Single Student</option>
                                <option value="selected_students">Selected Students</option>
                                <option value="active_students">Approved Active Students</option>
                            </select>
                            @error('form.generation_mode') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        @if (($form['generation_mode'] ?? 'single') === 'single')
                            <div class="col-12 mb-3">
                                <label class="form-label">Student <span class="text-danger">*</span></label>
                                <input type="text" class="form-control mb-2" wire:model.live.debounce.300ms="studentSearch" placeholder="Cari NIM, nama, atau email...">
                                <div class="border rounded p-2" style="max-height: 260px; overflow-y: auto;">
                                    @foreach ($this->studentOptions() as $student)
                                        <label class="form-check mb-2" wire:key="invoice-single-student-{{ $student['id'] }}">
                                            <input class="form-check-input" type="radio" wire:model="form.student_profile_id" value="{{ $student['id'] }}">
                                            <span class="form-check-label">{{ $student['label'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <small class="text-muted">Search hanya menampilkan 25 kandidat teratas.</small>
                                @error('form.student_profile_id') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        @elseif (($form['generation_mode'] ?? 'single') === 'selected_students')
                            <div class="col-12 mb-3">
                                <label class="form-label">Students <span class="text-danger">*</span></label>
                                <input type="text" class="form-control mb-2" wire:model.live.debounce.300ms="studentSearch" placeholder="Cari NIM, nama, atau email...">
                                <div class="border rounded p-2" style="max-height: 260px; overflow-y: auto;">
                                    @foreach ($this->studentOptions() as $student)
                                        <label class="form-check mb-2" wire:key="invoice-selected-student-{{ $student['id'] }}">
                                            <input class="form-check-input" type="checkbox" wire:model="selectedStudentProfileIds" value="{{ $student['id'] }}">
                                            <span class="form-check-label">{{ $student['label'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <small class="text-muted">{{ count($selectedStudentProfileIds) }} mahasiswa dipilih. Search hanya menampilkan 25 kandidat teratas.</small>
                                @error('selectedStudentProfileIds') <span class="text-danger d-block">{{ $message }}</span> @enderror
                                @error('selectedStudentProfileIds.*') <span class="text-danger d-block">{{ $message }}</span> @enderror
                            </div>
                        @else
                            @php($eligibleBulkStudentsCount = $this->eligibleBulkStudentsCount())
                            <div class="col-12 mb-3">
                                <div class="alert {{ $eligibleBulkStudentsCount > 0 ? 'alert-info' : 'alert-warning' }} mb-0">
                                    <div class="d-flex gap-2">
                                        <i class="fas {{ $eligibleBulkStudentsCount > 0 ? 'fa-circle-info' : 'fa-triangle-exclamation' }} mt-1"></i>
                                        <div>
                                            <strong>{{ $eligibleBulkStudentsCount }} mahasiswa eligible.</strong>
                                            @if ($eligibleBulkStudentsCount > 0)
                                                Bulk invoice akan dibuat hanya untuk student profile aktif yang cocok dengan academic year, semester, dan registrasi approved aktif.
                                            @else
                                                Tidak ada student profile aktif yang cocok dengan filter ini. Invoice tidak akan dibuat sampai ada mahasiswa eligible.
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Invoice Type</label>
                            <select class="form-control" wire:model="form.invoice_type" @if(($form['invoice_kind'] ?? 'tuition') === 'tuition') disabled @endif>
                                <option value="tuition">Tuition</option>
                                <option value="custom">Custom</option>
                                <option value="admission">Admission</option>
                                <option value="registration">Registration</option>
                                <option value="leave">Leave</option>
                                <option value="transfer">Transfer</option>
                                <option value="graduation">Graduation</option>
                                <option value="exam">Exam</option>
                                <option value="library_fine">Library Fine</option>
                                <option value="certificate">Certificate</option>
                                <option value="other">Other</option>
                            </select>
                            @error('form.invoice_type') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Academic Year @if(in_array(($form['invoice_type'] ?? 'custom'), ['tuition', 'registration', 'exam'])) <span class="text-danger">*</span> @endif</label>
                            <select class="form-control" wire:model="form.academic_year_id">
                                <option value="">Select Academic Year</option>
                                @foreach ($academicYears as $academicYear)
                                    <option value="{{ $academicYear['id'] }}">{{ $academicYear['label'] }}</option>
                                @endforeach
                            </select>
                            @error('form.academic_year_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Semester @if(($form['invoice_kind'] ?? 'tuition') === 'tuition') <span class="text-danger">*</span> @endif</label>
                            <input type="number" min="1" max="14" class="form-control" wire:model="form.semester">
                            @error('form.semester') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Due Date @if(($form['invoice_kind'] ?? 'tuition') === 'custom') <span class="text-danger">*</span> @endif</label>
                            <input type="date" class="form-control" wire:model="form.due_date">
                            @error('form.due_date') <span class="text-danger">{{ $message }}</span> @enderror
                            @if(($form['invoice_kind'] ?? 'tuition') === 'tuition')
                                <small class="text-muted">Kosongkan untuk memakai deadline tuition fee.</small>
                            @endif
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" rows="2" wire:model="form.notes"></textarea>
                            @error('form.notes') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    @if (($form['invoice_kind'] ?? 'tuition') === 'custom')
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Invoice Items</h5>
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="addItem">
                                    <i class="fas fa-plus me-1"></i> Add Item
                                </button>
                            </div>

                            @foreach ($items as $index => $item)
                                <div class="row align-items-end mb-2" wire:key="invoice-item-{{ $index }}">
                                    <div class="col-md-3">
                                        <label class="form-label">Type</label>
                                        <select class="form-control" wire:model="items.{{ $index }}.item_type">
                                            <option value="fee">Fee</option>
                                            <option value="discount">Discount</option>
                                            <option value="adjustment">Adjustment</option>
                                            <option value="penalty">Penalty</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Description</label>
                                        <input type="text" class="form-control" wire:model="items.{{ $index }}.description" placeholder="Bayar A">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Amount</label>
                                        <input type="number" min="0" step="1000" class="form-control" wire:model="items.{{ $index }}.amount">
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-outline-danger w-100" wire:click="removeItem({{ $index }})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" wire:model="form.issue_immediately">
                            <span class="form-check-label">Issue immediately and show to student</span>
                        </label>
                        <small class="text-muted d-block">Custom invoice biasanya dibuat draft dulu agar bisa direview.</small>
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-file-invoice-dollar me-1"></i> Save Invoice
                        </button>
                        <a href="{{ route('admin.financial.student-invoices.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Rule</h5>
            </div>
            <div class="card-body">
                <ul class="mb-0 ps-3">
                    <li>Tuition invoice mengambil item dari tuition fee aktif.</li>
                    <li>Custom invoice memakai item manual dan default draft.</li>
                    <li>Invoice hanya dibuat untuk mahasiswa yang sudah punya student profile aktif.</li>
                    <li>Mode bulk memfilter student profile aktif dengan registrasi approved aktif pada tahun akademik/semester yang dipilih.</li>
                    <li>Draft invoice belum tampil di halaman student.</li>
                    <li>Invoice yang sudah punya payment nanti tidak diedit langsung, tapi via adjustment.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
