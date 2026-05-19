<div class="d-flex flex-wrap align-items-center gap-1 me-1">
    <button type="button" class="btn btn-outline-danger" wire:click="exportToPdf(false)" wire:loading.attr="disabled" wire:target="exportToPdf">
        <i class="fas fa-file-pdf me-1"></i> PDF
    </button>

    @if (($this->selectedCount() ?? 0) > 0)
        <button type="button" class="btn btn-outline-danger" wire:click="exportToPdf(true)" wire:loading.attr="disabled" wire:target="exportToPdf">
            <i class="fas fa-file-pdf me-1"></i> Selected PDF
        </button>
    @endif
</div>
