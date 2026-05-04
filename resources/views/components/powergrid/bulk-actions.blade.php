@if ($this->bulkActionEnabled ?? false)
    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
        <span class="badge bg-blue-lt text-blue">
            {{ $this->selectedCount() }} dipilih
        </span>

        @if ($this->canBulkDelete())
            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="confirmBulkDelete">
                Bulk Delete
            </button>
        @endif

        @if ($this->canCustomBulkAction())
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="runCustomBulkAction">
                {{ $this->customBulkActionLabel() }}
            </button>
        @endif

        @if (($softDeletes ?? '') !== '' && $this->canBulkRestore())
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="bulkRestoreSelected">
                Restore Selected
            </button>
        @endif

        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="clearBulkSelection">
            Clear Selection
        </button>

        @if (($softDeletes ?? '') === 'onlyTrashed')
            <span class="text-secondary small">Mode: trash only</span>
        @elseif (($softDeletes ?? '') === 'withTrashed')
            <span class="text-secondary small">Mode: termasuk trashed</span>
        @endif
    </div>
@endif
