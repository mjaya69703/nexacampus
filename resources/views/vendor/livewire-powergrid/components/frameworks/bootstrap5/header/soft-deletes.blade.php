@if (data_get($setUp, 'header.softDeletes'))
    <div class="btn-group powergrid-trash-filter">
        <button
            class="btn btn-sm dropdown-toggle powergrid-trash-filter__button"
            type="button"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            title="@lang('livewire-powergrid::datatable.soft_deletes.with_trashed')"
        >
            <span class="powergrid-trash-filter__icon">
                <i class="fas fa-trash-can"></i>
            </span>
        </button>
        <ul class="dropdown-menu">
            <li wire:click="$dispatch('pg:softDeletes-{{ $tableName }}', {softDeletes: ''})">
                <a
                    class="dropdown-item"
                    href="#"
                >
                    @lang('livewire-powergrid::datatable.soft_deletes.without_trashed')
                </a>
            </li>
            <li wire:click="$dispatch('pg:softDeletes-{{ $tableName }}', {softDeletes: 'withTrashed'})">
                <a
                    class="dropdown-item"
                    href="#"
                >
                    @lang('livewire-powergrid::datatable.soft_deletes.with_trashed')
                </a>
            </li>
            <li wire:click="$dispatch('pg:softDeletes-{{ $tableName }}', {softDeletes: 'onlyTrashed'})">
                <a
                    class="dropdown-item"
                    href="#"
                >
                    @lang('livewire-powergrid::datatable.soft_deletes.only_trashed')
                </a>
            </li>
        </ul>
    </div>
@endif
