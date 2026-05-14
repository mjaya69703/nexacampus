<?php

use App\Models\Academic\AcademicYear;
use App\Models\Financial\StudentInvoice;
use App\Support\Financial\InvoiceStatusService;
use Livewire\Component;

new class extends Component
{
    public StudentInvoice $invoice;
    public array $academicYears = [];
    public array $form = [];
    public array $items = [];

    public function mount($id): void
    {
        $this->invoice = StudentInvoice::with(['items', 'studentProfile.user', 'studentProfile.studyProgram'])->findOrFail($id);

        abort_unless($this->invoice->isEditable(), 403);

        $this->academicYears = AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'code'])
            ->map(fn (AcademicYear $year) => ['id' => $year->id, 'label' => $year->name.' ('.$year->code.')'])
            ->toArray();

        $this->form = [
            'academic_year_id' => $this->invoice->academic_year_id ?: '',
            'semester' => $this->invoice->semester,
            'invoice_type' => $this->invoice->invoice_type,
            'due_date' => $this->invoice->due_date?->format('Y-m-d'),
            'notes' => $this->invoice->notes,
        ];

        $this->items = $this->invoice->items()
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($item) => [
                'item_type' => in_array($item->item_type, ['discount', 'adjustment', 'penalty'], true) ? $item->item_type : 'fee',
                'description' => $item->description,
                'amount' => abs((float) $item->amount),
            ])
            ->toArray();

        if (empty($this->items)) {
            $this->items[] = ['item_type' => 'fee', 'description' => '', 'amount' => 0];
        }
    }

    public function addItem(): void
    {
        $this->items[] = ['item_type' => 'fee', 'description' => '', 'amount' => 0];
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
        abort_unless($this->invoice->fresh()->isEditable(), 403);

        $validated = $this->validate([
            'form.academic_year_id' => 'nullable|exists:academic_years,id',
            'form.semester' => 'nullable|integer|min:1|max:14',
            'form.invoice_type' => 'required|in:tuition,custom,admission,registration,graduation,exam,library_fine,certificate,other',
            'form.due_date' => 'required|date',
            'form.notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.item_type' => 'required|in:fee,discount,adjustment,penalty',
            'items.*.description' => 'required|string|max:255',
            'items.*.amount' => 'required|numeric|min:0.01',
        ]);

        $this->invoice->update([
            'academic_year_id' => $validated['form']['academic_year_id'] ?: null,
            'semester' => $validated['form']['semester'] ?: null,
            'invoice_type' => $validated['form']['invoice_type'],
            'due_date' => $validated['form']['due_date'],
            'notes' => $validated['form']['notes'] ?: null,
            'updated_by' => auth()->id(),
        ]);

        $this->invoice->items()->delete();

        foreach (array_values($validated['items']) as $index => $item) {
            $amount = (float) $item['amount'];

            if ($item['item_type'] === 'discount') {
                $amount = -abs($amount);
            }

            $this->invoice->items()->create([
                'item_type' => $item['item_type'],
                'description' => $item['description'],
                'amount' => $amount,
                'sort_order' => $index + 1,
            ]);
        }

        app(InvoiceStatusService::class)->refresh($this->invoice);

        session()->flash('success', 'Invoice berhasil diperbarui.');
        $this->redirectRoute('admin.financial.student-invoices.show', ['id' => $this->invoice->id]);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Edit Student Invoice',
        ]);
    }
};
?>

<div class="row">
    <div class="col-lg-8">
        <x-alert />
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="card-title mb-0">Edit Invoice</h3>
                    <small class="text-muted">{{ $invoice->invoice_number }} · {{ $invoice->studentProfile?->nim }} - {{ $invoice->studentProfile?->user?->name }}</small>
                </div>
                <a href="{{ route('admin.financial.student-invoices.show', ['id' => $invoice->id]) }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Invoice Type</label>
                            <select class="form-control" wire:model="form.invoice_type">
                                <option value="tuition">Tuition</option>
                                <option value="custom">Custom</option>
                                <option value="admission">Admission</option>
                                <option value="registration">Registration</option>
                                <option value="graduation">Graduation</option>
                                <option value="exam">Exam</option>
                                <option value="library_fine">Library Fine</option>
                                <option value="certificate">Certificate</option>
                                <option value="other">Other</option>
                            </select>
                            @error('form.invoice_type') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Academic Year</label>
                            <select class="form-control" wire:model="form.academic_year_id">
                                <option value="">No Academic Year</option>
                                @foreach ($academicYears as $academicYear)
                                    <option value="{{ $academicYear['id'] }}">{{ $academicYear['label'] }}</option>
                                @endforeach
                            </select>
                            @error('form.academic_year_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Semester</label>
                            <input type="number" min="1" max="14" class="form-control" wire:model="form.semester">
                            @error('form.semester') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Due Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" wire:model="form.due_date">
                            @error('form.due_date') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" rows="2" wire:model="form.notes"></textarea>
                            @error('form.notes') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Invoice Items</h5>
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="addItem">
                                <i class="fas fa-plus me-1"></i> Add Item
                            </button>
                        </div>

                        @foreach ($items as $index => $item)
                            <div class="row align-items-end mb-2" wire:key="edit-invoice-item-{{ $index }}">
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
                                    <input type="text" class="form-control" wire:model="items.{{ $index }}.description">
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

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save
                        </button>
                        <a href="{{ route('admin.financial.student-invoices.show', ['id' => $invoice->id]) }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="alert alert-info">
            Invoice hanya bisa diedit selama belum ada payment. Setelah payment masuk, perubahan nominal akan masuk lewat adjustment flow.
        </div>
    </div>
</div>
