<?php

use App\Models\Financial\FinancialHold;
use App\Support\ActivePermission;
use App\Support\Financial\FinancialClearanceService;
use Livewire\Component;

new class extends Component
{
    public FinancialHold $hold;

    public ?string $releaseNotes = null;

    public ?string $waivedUntil = null;

    public function mount($id): void
    {
        $this->hold = FinancialHold::with([
            'studentProfile.user',
            'studentProfile.studyProgram',
            'invoice',
            'policy',
            'releasedBy',
        ])->findOrFail($id);

        $this->waivedUntil = now()->addDays(7)->toDateString();
    }

    public function release(FinancialClearanceService $clearanceService): void
    {
        abort_unless(ActivePermission::check('financial-hold.update'), 403);

        $clearanceService->release($this->hold, auth()->id(), $this->releaseNotes);
        session()->flash('success', 'Financial hold berhasil direlease.');
        $this->reloadHold();
    }

    public function waive(FinancialClearanceService $clearanceService): void
    {
        abort_unless(ActivePermission::check('financial-hold.update'), 403);

        $this->validate([
            'waivedUntil' => ['required', 'date', 'after_or_equal:today'],
            'releaseNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $clearanceService->waive($this->hold, $this->waivedUntil, auth()->id(), $this->releaseNotes);
        session()->flash('success', 'Dispensasi financial hold berhasil diberikan.');
        $this->reloadHold();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Financial Hold Detail',
        ]);
    }

    public function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    private function reloadHold(): void
    {
        $this->hold->refresh()->load([
            'studentProfile.user',
            'studentProfile.studyProgram',
            'invoice',
            'policy',
            'releasedBy',
        ]);
    }
};
?>

<div class="row">
    <div class="col-lg-8">
        <x-alert />

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="card-title mb-0">{{ str($hold->hold_type)->replace('_', ' ')->title() }} Hold</h3>
                    <small class="text-muted">{{ $hold->studentProfile?->user?->name ?? '-' }} / {{ $hold->studentProfile?->nim ?? '-' }}</small>
                </div>
                <a href="{{ route('admin.financial.financial-holds.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted">Mahasiswa</small>
                        <div class="h6 mb-0">{{ $hold->studentProfile?->user?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Study Program</small>
                        <div class="h6 mb-0">{{ $hold->studentProfile?->studyProgram?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Invoice</small>
                        <div class="h6 mb-0">{{ $hold->invoice?->invoice_number ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Invoice Type</small>
                        <div class="h6 mb-0">{{ str($hold->invoice?->invoice_type ?? '-')->replace('_', ' ')->title() }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Outstanding</small>
                        <div class="h5 mb-0">{{ $this->money($hold->invoice?->outstanding_amount) }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Status</small>
                        <div class="h6 mb-0">{{ $hold->isBlocking() ? 'Blocking' : str($hold->status)->title() }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Started At</small>
                        <div class="h6 mb-0">{{ $hold->starts_at?->format('d F Y H:i') ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Blocked At</small>
                        <div class="h6 mb-0">{{ $hold->blocked_at?->format('d F Y H:i') ?? '-' }}</div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Reason</small>
                        <div class="alert alert-light border mb-0">{{ $hold->reason ?: '-' }}</div>
                    </div>
                </div>
            </div>
        </div>

        @if($hold->status !== 'active')
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Resolution</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <small class="text-muted">Resolved By</small>
                            <div class="h6 mb-0">{{ $hold->releasedBy?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">Resolved At</small>
                            <div class="h6 mb-0">{{ $hold->released_at?->format('d F Y H:i') ?? '-' }}</div>
                        </div>
                        @if($hold->status === 'waived')
                            <div class="col-md-6">
                                <small class="text-muted">Waived Until</small>
                                <div class="h6 mb-0">{{ $hold->waived_until?->format('d F Y') ?? '-' }}</div>
                            </div>
                        @endif
                        <div class="col-12">
                            <small class="text-muted">Notes</small>
                            <div>{{ $hold->release_notes ?: '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Finance Action</h5>
            </div>
            <div class="card-body">
                @if($hold->status === 'active')
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea wire:model="releaseNotes" class="form-control" rows="4" placeholder="Reason for release/dispensation"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Waive Until</label>
                        <input type="date" wire:model="waivedUntil" class="form-control">
                        @error('waivedUntil') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-info" wire:click="waive">
                            <i class="fas fa-clock me-1"></i> Give Temporary Dispensation
                        </button>
                        <button type="button" class="btn btn-success" wire:click="release">
                            <i class="fas fa-check me-1"></i> Release Hold
                        </button>
                    </div>
                @else
                    <div class="text-muted">Hold ini sudah {{ str($hold->status)->title() }}.</div>
                @endif
            </div>
        </div>
    </div>
</div>
