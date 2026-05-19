<?php

use App\Models\Financial\StudentInvoice;
use App\Support\Financial\InvoicePublishingService;
use App\Support\Financial\InvoiceStatusService;
use App\Support\Financial\InvoiceAdjustmentService;
use App\Support\Financial\ScholarshipApplicationService;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public StudentInvoice $invoice;

    public array $adjustmentForm = [
        'adjustment_type' => 'discount',
        'amount' => '',
        'reason' => '',
    ];

    public ?int $scholarshipAssignmentId = null;

    public function mount($id): void
    {
        $this->invoice = StudentInvoice::with([
            'studentProfile.user',
            'studentProfile.studyProgram',
            'academicYear',
            'items',
            'adjustments.createdBy',
            'adjustments.source',
            'payments.verifiedBy',
            'installments',
            'installmentRequests.reviewedBy',
            'issuedBy',
            'cancelledBy',
        ])->findOrFail($id);

        app(InvoiceStatusService::class)->refresh($this->invoice);
        $this->reloadInvoice();
    }

    public function applyAdjustment(InvoiceAdjustmentService $adjustmentService): void
    {
        abort_unless(\App\Support\ActivePermission::check('student-invoice.update'), 403);

        $validated = $this->validate([
            'adjustmentForm.adjustment_type' => ['required', Rule::in(array_keys(InvoiceAdjustmentService::TYPES))],
            'adjustmentForm.amount' => ['required', 'numeric', 'min:1'],
            'adjustmentForm.reason' => ['nullable', 'string', 'max:1000'],
        ])['adjustmentForm'];

        try {
            $adjustmentService->apply(
                invoice: $this->invoice,
                type: $validated['adjustment_type'],
                amount: (float) $validated['amount'],
                reason: $validated['reason'] ?: null,
                createdBy: auth()->id()
            );

            $this->adjustmentForm = [
                'adjustment_type' => 'discount',
                'amount' => '',
                'reason' => '',
            ];
            session()->flash('success', 'Adjustment berhasil diterapkan.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reloadInvoice();
    }

    public function applyScholarship(ScholarshipApplicationService $scholarshipService): void
    {
        abort_unless(\App\Support\ActivePermission::check('student-invoice.update'), 403);

        $validated = $this->validate([
            'scholarshipAssignmentId' => ['required', 'exists:student_scholarships,id'],
        ]);

        try {
            $assignment = \App\Models\Financial\StudentScholarship::with('scholarship')->findOrFail($validated['scholarshipAssignmentId']);
            $scholarshipService->applyAssignmentToInvoice($assignment, $this->invoice, auth()->id());
            $this->scholarshipAssignmentId = null;
            session()->flash('success', 'Scholarship berhasil diterapkan ke invoice.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reloadInvoice();
    }

    public function issue(InvoicePublishingService $publishingService): void
    {
        try {
            $this->invoice = $publishingService->issue($this->invoice, auth()->id());
            session()->flash('success', 'Invoice berhasil diterbitkan.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reloadInvoice();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Invoice Detail',
        ]);
    }

    public function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'paid' => 'bg-success',
            'partially_paid' => 'bg-info',
            'overdue' => 'bg-danger',
            'cancelled' => 'bg-secondary',
            'issued' => 'bg-primary',
            'draft' => 'bg-light text-dark',
            'verified', 'approved' => 'bg-success',
            'pending', 'submitted' => 'bg-warning text-dark',
            'rejected' => 'bg-danger',
            default => 'bg-warning text-dark',
        };
    }

    private function reloadInvoice(): void
    {
        $this->invoice->refresh()->load([
            'studentProfile.user',
            'studentProfile.studyProgram',
            'academicYear',
            'items',
            'adjustments.createdBy',
            'adjustments.source',
            'payments.verifiedBy',
            'installments',
            'installmentRequests.reviewedBy',
            'issuedBy',
            'cancelledBy',
        ]);
    }

    public function adjustmentTypes(): array
    {
        return InvoiceAdjustmentService::TYPES;
    }

    public function availableScholarshipAssignments(): array
    {
        return \App\Models\Financial\StudentScholarship::query()
            ->with('scholarship')
            ->where('student_profile_id', $this->invoice->student_profile_id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('academic_year_id')
                    ->orWhere('academic_year_id', $this->invoice->academic_year_id);
            })
            ->where(function ($query) {
                $query->whereNull('semester')
                    ->orWhere('semester', $this->invoice->semester);
            })
            ->get()
            ->map(fn (\App\Models\Financial\StudentScholarship $assignment) => [
                'id' => $assignment->id,
                'label' => $assignment->scholarship?->name.' - '.($assignment->academicYear?->name ?? 'All Years').' / '.($assignment->semester ? 'Semester '.$assignment->semester : 'All Semesters'),
            ])
            ->toArray();
    }
};
?>

<div class="row">
    <div class="col-lg-8">
        <x-alert />

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="card-title mb-0">{{ $invoice->invoice_number }}</h3>
                    <small class="text-muted">{{ ucfirst(str_replace('_', ' ', $invoice->invoice_type)) }} invoice</small>
                </div>
                <div class="d-flex gap-2">
                    @if($invoice->isEditable())
                        <a href="{{ route('admin.financial.student-invoices.edit', ['id' => $invoice->id]) }}" class="btn btn-warning">
                            <i class="fas fa-edit me-1"></i> Edit
                        </a>
                    @endif
                    @if($invoice->status === 'draft')
                        <button type="button" class="btn btn-success" wire:click="issue">
                            <i class="fas fa-paper-plane me-1"></i> Issue
                        </button>
                    @endif
                    <a href="{{ route('admin.financial.student-invoices.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted">Mahasiswa</small>
                        <div class="h6 mb-0">{{ $invoice->studentProfile?->user?->name }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">NIM</small>
                        <div class="h6 mb-0">{{ $invoice->studentProfile?->nim }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Program Studi</small>
                        <div class="h6 mb-0">{{ $invoice->studentProfile?->studyProgram?->name }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Academic Year</small>
                        <div class="h6 mb-0">
                            {{ $invoice->academicYear?->name ?? '-' }}
                            @if($invoice->semester)
                                - Semester {{ $invoice->semester }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Invoice Items</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Type</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->items->sortBy('sort_order') as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td>{{ ucfirst($item->item_type) }}</td>
                                <td class="text-end">{{ $this->money($item->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2">Total</th>
                            <th class="text-end">{{ $this->money($invoice->total_amount) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">Adjustments</h5>
                    <small class="text-muted">Post-issue ledger for discount, waiver, scholarship, correction, and soft penalty.</small>
                </div>
                <span class="badge bg-light text-dark">{{ $invoice->adjustments->count() }} records</span>
            </div>
            <div class="card-body">
                @if($invoice->status !== 'draft' && $invoice->status !== 'cancelled')
                    <form wire:submit.prevent="applyAdjustment" class="row g-2 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Type</label>
                            <select class="form-control" wire:model="adjustmentForm.adjustment_type">
                                @foreach($this->adjustmentTypes() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('adjustmentForm.adjustment_type') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Amount</label>
                            <input type="number" min="1" step="1000" class="form-control" wire:model="adjustmentForm.amount">
                            @error('adjustmentForm.amount') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reason</label>
                            <input type="text" class="form-control" wire:model="adjustmentForm.reason" placeholder="Optional note">
                            @error('adjustmentForm.reason') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-1"></i> Apply
                            </button>
                        </div>
                    </form>

                    @if(count($this->availableScholarshipAssignments()) > 0)
                        <form wire:submit.prevent="applyScholarship" class="row g-2 mb-3">
                            <div class="col-md-10">
                                <label class="form-label">Apply Student Scholarship</label>
                                <select class="form-control" wire:model="scholarshipAssignmentId">
                                    <option value="">Select active scholarship assignment</option>
                                    @foreach($this->availableScholarshipAssignments() as $assignment)
                                        <option value="{{ $assignment['id'] }}">{{ $assignment['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('scholarshipAssignmentId') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-graduation-cap me-1"></i> Apply
                                </button>
                            </div>
                        </form>
                    @endif
                @else
                    <div class="alert alert-light border mb-3">Adjustment tersedia setelah invoice diterbitkan dan belum dibatalkan.</div>
                @endif

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Reason</th>
                                <th>Created By</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoice->adjustments->sortByDesc('created_at') as $adjustment)
                                <tr>
                                    <td>{{ str($adjustment->adjustment_type)->replace('_', ' ')->title() }}</td>
                                    <td>{{ $adjustment->reason ?: '-' }}</td>
                                    <td>{{ $adjustment->createdBy?->name ?? '-' }}</td>
                                    <td class="text-end">{{ $this->money($adjustment->amount) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Belum ada adjustment.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if($invoice->installments->isNotEmpty())
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Installment Schedule</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Due Date</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Paid</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->installments->sortBy('installment_no') as $installment)
                                <tr>
                                    <td>{{ $installment->installment_no }}</td>
                                    <td>{{ $installment->due_date?->format('d M Y') }}</td>
                                    <td class="text-end">{{ $this->money((float) $installment->amount + (float) $installment->fee_amount) }}</td>
                                    <td class="text-end">{{ $this->money($installment->paid_amount) }}</td>
                                    <td><span class="badge {{ $this->statusClass($installment->status) }}">{{ str($installment->status)->replace('_', ' ')->title() }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($invoice->payments->isNotEmpty())
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Payment History</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Payment</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                                <th>Paid At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->payments->sortByDesc('created_at') as $payment)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.financial.payments.show', ['id' => $payment->id]) }}">{{ $payment->payment_number }}</a>
                                    </td>
                                    <td class="text-end">{{ $this->money($payment->amount) }}</td>
                                    <td><span class="badge {{ $this->statusClass($payment->status) }}">{{ str($payment->status)->replace('_', ' ')->title() }}</span></td>
                                    <td>{{ $payment->paid_at?->format('d M Y H:i') ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Payment Summary</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted">Status</small>
                    <div><span class="badge {{ $this->statusClass($invoice->status) }} p-2">{{ str_replace('_', ' ', ucfirst($invoice->status)) }}</span></div>
                </div>
                <div class="mb-3">
                    <small class="text-muted">Due Date</small>
                    <div class="h6 mb-0">{{ $invoice->due_date?->format('d F Y') }}</div>
                </div>
                <div class="mb-3">
                    <small class="text-muted">Paid Amount</small>
                    <div class="h6 mb-0">{{ $this->money($invoice->paid_amount) }}</div>
                </div>
                <div class="mb-0">
                    <small class="text-muted">Outstanding</small>
                    <div class="h4 mb-0">{{ $this->money($invoice->outstanding_amount) }}</div>
                </div>
            </div>
        </div>

        @if($invoice->status === 'cancelled')
            <div class="alert alert-secondary">
                Cancelled at {{ $invoice->cancelled_at?->format('d F Y H:i') }} by {{ $invoice->cancelledBy?->name ?? '-' }}.
            </div>
        @endif

        @if($invoice->issued_at)
            <div class="alert alert-info">
                Issued at {{ $invoice->issued_at?->format('d F Y H:i') }} by {{ $invoice->issuedBy?->name ?? '-' }}.
            </div>
        @endif
    </div>
</div>
