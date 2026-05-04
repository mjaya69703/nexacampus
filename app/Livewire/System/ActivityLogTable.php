<?php

namespace App\Livewire\System;

use App\Livewire\BasePowerGridTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use Spatie\Activitylog\Models\Activity;

final class ActivityLogTable extends BasePowerGridTable
{
    public string $tableName = 'activity-log-table';

    public bool $showFilters = true;

    protected ?string $bulkActionModel = Activity::class;

    protected string $bulkActionItemLabel = 'log aktivitas';

    public ?array $selectedActivity = null;

    public bool $showDetailModal = false;

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function template(): ?string
    {
        return 'components.table.activity-log';
    }

    public function setUp(): array
    {
        return $this->powerGridSetUp(
            showSearchInput: true,
            showToggleColumns: true,
            withoutLoading: true,
        );
    }

    public function datasource(): Builder
    {
        return Activity::query()
            ->with('causer')
            ->latest();
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('log_name')
            ->add('description')
            ->add('event')
            ->add('subject_type_label', fn (Activity $model) => $model->subject_type ? class_basename($model->subject_type) : '-')
            ->add('subject_id')
            ->add('causer_name', function (Activity $model) {
                if (! $model->causer) {
                    return 'System';
                }

                $fullName = trim(($model->causer->first_name ?? '').' '.($model->causer->last_name ?? ''));

                return $fullName !== '' ? $fullName : ($model->causer->name ?? 'User #'.$model->causer_id);
            })
            ->add('causer_id')
            ->add('properties_preview', function (Activity $model) {
                $properties = $model->properties?->toArray() ?? [];

                if (isset($properties['active_role'])) {
                    return 'Role: '.$properties['active_role'];
                }

                if (isset($properties['attributes']['name'])) {
                    return 'Target: '.$properties['attributes']['name'];
                }

                if (isset($properties['old']['name'])) {
                    return 'Old: '.$properties['old']['name'];
                }

                if (isset($properties['ip'])) {
                    return 'IP: '.$properties['ip'];
                }

                return ! empty($properties)
                    ? str(json_encode($properties, JSON_UNESCAPED_UNICODE))->limit(80)->value()
                    : '-';
            })
            ->add('created_at_formatted', fn (Activity $model) => Carbon::parse($model->created_at)->format('d/m/Y H:i:s'));
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')
                ->sortable(),

            Column::make('Log', 'log_name')
                ->sortable()
                ->searchable(),

            Column::make('Description', 'description')
                ->sortable()
                ->searchable(),

            Column::make('Event', 'event')
                ->sortable()
                ->searchable(),

            Column::make('Subject', 'subject_type_label')
                ->sortable()
                ->searchable(),

            Column::make('Subject ID', 'subject_id')
                ->sortable(),

            Column::make('Causer', 'causer_name')
                ->sortable()
                ->searchable(),

            Column::make('Preview', 'properties_preview')
                ->searchable(),

            Column::make('Created At', 'created_at_formatted', 'created_at')
                ->sortable(),

            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('log_name'),
            Filter::inputText('description'),
            Filter::inputText('event'),
            Filter::datepicker('created_at'),
        ];
    }

    public function actions(Activity $row): array
    {
        return [
            Button::add('detail')
                ->slot('<i class="fas fa-eye me-1"></i> Detail')
                ->class('btn btn-primary')
                ->route('admin.system.activity-logs.show', ['id' => $row->id]),
        ];
    }

    public function canBulkDelete(): bool
    {
        return true;
    }
}
