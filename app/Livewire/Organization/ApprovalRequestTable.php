<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\ApprovalRequest;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class ApprovalRequestTable extends BasePowerGridTable
{
    public string $tableName = 'approvalRequestTable';

    protected ?string $bulkActionModel = ApprovalRequest::class;

    protected ?string $bulkActionPermissionPrefix = 'approval-request';

    protected string $bulkActionItemLabel = 'request approval';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return ApprovalRequest::query()
            ->with(['template', 'requester'])
            ->orderByRaw("FIELD(status, 'in_progress', 'submitted', 'draft', 'approved', 'rejected', 'cancelled')")
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'template' => ['name', 'code', 'module'],
            'requester' => ['first_name', 'last_name', 'email', 'username', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('subject')
            ->add('reference', fn (ApprovalRequest $model) => $model->reference ?: '-')
            ->add('template_name', fn (ApprovalRequest $model) => $model->template?->name ?? '-')
            ->add('requester_name', fn (ApprovalRequest $model) => $model->requester?->name ?? '-')
            ->add('status_badge', fn (ApprovalRequest $model) => $this->statusBadge($model->status))
            ->add('current_step_order', fn (ApprovalRequest $model) => $model->current_step_order ?: '-')
            ->add('submitted_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Subject', 'subject')->sortable()->searchable(),
            Column::make('Referensi', 'reference')->sortable()->searchable(),
            Column::make('Template', 'template_name')->searchable(),
            Column::make('Requester', 'requester_name')->searchable(),
            Column::make('Status', 'status_badge', 'status')->sortable(),
            Column::make('Step', 'current_step_order')->sortable(),
            Column::make('Submit', 'submitted_at')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status', 'status')
                ->dataSource(collect([
                    ['id' => 'in_progress', 'name' => 'In Progress'],
                    ['id' => 'approved', 'name' => 'Approved'],
                    ['id' => 'rejected', 'name' => 'Rejected'],
                    ['id' => 'cancelled', 'name' => 'Cancelled'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.organization.approval-requests.show', ['id' => $rowId]);
    }

    public function actions(ApprovalRequest $row): array
    {
        if (! ActivePermission::check('approval-request.view')) {
            return [];
        }

        return [
            Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-primary')
                ->dispatch('show', ['rowId' => $row->id]),
        ];
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'approved' => 'bg-success',
            'rejected' => 'bg-danger',
            'cancelled' => 'bg-secondary',
            'draft' => 'bg-muted',
            default => 'bg-warning text-dark',
        };

        return '<span class="badge '.$class.'">'.str($status)->replace('_', ' ')->title().'</span>';
    }
}
