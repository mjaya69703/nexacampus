<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="exportToPdf(false)" wire:loading.attr="disabled" wire:target="exportToPdf">
        <i class="fas fa-file-pdf me-1"></i> PDF
    </button>

    @if (($this->selectedCount() ?? 0) > 0)
        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="exportToPdf(true)" wire:loading.attr="disabled" wire:target="exportToPdf">
            <i class="fas fa-file-pdf me-1"></i> Selected PDF
        </button>
    @endif
</div>
