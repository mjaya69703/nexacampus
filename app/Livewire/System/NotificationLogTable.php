<?php

namespace App\Livewire\System;

use App\Livewire\BasePowerGridTable;
use App\Livewire\Concerns\ExportsPowerGridWithPhpSpreadsheet;
use App\Models\Settings\NotificationLog;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class NotificationLogTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'notification-log-table';

    protected bool $bulkActionEnabled = false;

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return NotificationLog::query()
            ->with('user')
            ->latest();
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('event_key')
            ->add('channel')
            ->add('provider')
            ->add('status_badge', fn (NotificationLog $model) => $this->statusBadge($model->status))
            ->add('status')
            ->add('recipient_name')
            ->add('recipient_phone')
            ->add('recipient_email')
            ->add('subject')
            ->add('body_preview', fn (NotificationLog $model) => str($model->body ?? '-')->limit(120)->toString())
            ->add('error_preview', fn (NotificationLog $model) => str($model->error_message ?? '-')->limit(120)->toString())
            ->add('sent_at_label', fn (NotificationLog $model) => $model->sent_at?->format('d M Y H:i') ?? '-')
            ->add('created_at_label', fn (NotificationLog $model) => $model->created_at?->format('d M Y H:i') ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::make('Event', 'event_key')->sortable()->searchable(),
            Column::make('Channel', 'channel')->sortable()->searchable(),
            Column::make('Provider', 'provider')->sortable()->searchable(),
            Column::make('Status', 'status_badge', 'status')->sortable(),
            Column::make('Penerima', 'recipient_name')->sortable()->searchable(),
            Column::make('No. WA', 'recipient_phone')->searchable(),
            Column::make('Email', 'recipient_email')->searchable()->hidden(),
            Column::make('Subject', 'subject')->searchable(),
            Column::make('Pesan', 'body_preview')->searchable(),
            Column::make('Error', 'error_preview')->searchable(),
            Column::make('Sent At', 'sent_at_label', 'sent_at')->sortable(),
            Column::make('Created At', 'created_at_label', 'created_at')->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('channel', 'channel')
                ->dataSource(collect(['whatsapp', 'email', 'in_app'])->map(fn (string $channel) => ['id' => $channel, 'name' => $channel]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('status', 'status')
                ->dataSource(collect(['queued', 'sent', 'failed', 'skipped'])->map(fn (string $status) => ['id' => $status, 'name' => $status]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::inputText('event_key'),
            Filter::inputText('recipient_phone'),
            Filter::datepicker('created_at'),
        ];
    }

    private function statusBadge(?string $status): string
    {
        $class = match ($status) {
            'sent' => 'bg-success',
            'failed' => 'bg-danger',
            'skipped' => 'bg-secondary',
            default => 'bg-warning text-dark',
        };

        return '<span class="badge '.$class.'">'.str($status ?: 'queued')->replace('_', ' ')->title().'</span>';
    }
}
