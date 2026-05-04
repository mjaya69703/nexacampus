<div class="col-12 powergrid-search">
    <label class="col-12 col-sm-8 mb-0">
        @if (data_get($setUp, 'header.searchInput'))
            <div class="input-group powergrid-search__group w-100">
                <span class="powergrid-search__icon">
                    <i class="fas fa-magnifying-glass"></i>
                </span>
                <input
                    wire:model.live.debounce.600ms="search"
                    type="text"
                    class="{{ theme_style($theme, 'searchBox.input') }} powergrid-search__input"
                    placeholder="{{ trans('livewire-powergrid::datatable.placeholders.search') }}"
                >
            </div>
        @endif
    </label>
</div>
