<?php

use App\Models\Financial\StudentInvoice;
use App\Support\Financial\InvoicePublishingService;
use App\Support\Financial\InvoiceStatusService;
use Livewire\Component;

new class extends Component
{
    public StudentInvoice $invoice;

    public function mount($id): void
    {
        $this->invoice = StudentInvoice::with([
            'studentProfile.user',
            'studentProfile.studyProgram',
            'academicYear',
            'items',
            'issuedBy',
            'cancelledBy',
        ])->findOrFail($id);

        app(InvoiceStatusService::class)->refresh($this->invoice);
        $this->invoice->refresh()->load(['studentProfile.user', 'studentProfile.studyProgram', 'academicYear', 'items', 'issuedBy', 'cancelledBy']);
    }

    public function issue(InvoicePublishingService $publishingService): void
    {
        try {
            $this->invoice = $publishingService->issue($this->invoice, auth()->id());
            session()->flash('success', 'Invoice berhasil diterbitkan.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->invoice->refresh()->load(['studentProfile.user', 'studentProfile.studyProgram', 'academicYear', 'items', 'issuedBy', 'cancelledBy']);
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
            default => 'bg-warning text-dark',
        };
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
