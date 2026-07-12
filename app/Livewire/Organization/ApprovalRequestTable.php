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
            Filter::inputText('subject', 'subject')
                ->placeholder('Cari subject...')
                ->operators(['contains']),
            Filter::inputText('reference', 'reference')
                ->placeholder('Cari referensi...')
                ->operators(['contains']),
            Filter::inputText('template_name', 'template_name')
                ->placeholder('Cari template...')
                ->operators(['contains'])
                ->builder(function (Builder $query, array $values) {
                    $value = $values['value'] ?? null;
                    if (! empty($value)) {
                        $query->whereHas('template', function (Builder $sub) use ($value) {
                            $sub->where('name', 'like', '%' . $value . '%');
                        });
                    }
                }),
            Filter::inputText('requester_name', 'requester_name')
                ->placeholder('Cari requester...')
                ->operators(['contains'])
                ->builder(function (Builder $query, array $values) {
                    $value = $values['value'] ?? null;
                    if (! empty($value)) {
                        $query->whereHas('requester', function (Builder $sub) use ($value) {
                            $sub->where(function (Builder $q) use ($value) {
                                $q->where('first_name', 'like', '%' . $value . '%')
                                    ->orWhere('last_name', 'like', '%' . $value . '%')
                                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ['%' . $value . '%'])
                                    ->orWhere('username', 'like', '%' . $value . '%')
                                    ->orWhere('email', 'like', '%' . $value . '%');
                            });
                        });
                    }
                }),
            Filter::select('status', 'status')
                ->dataSource(collect([
                    ['id' => 'in_progress', 'name' => 'In Progress'],
                    ['id' => 'approved', 'name' => 'Approved'],
                    ['id' => 'rejected', 'name' => 'Rejected'],
                    ['id' => 'cancelled', 'name' => 'Cancelled'],
                    ['id' => 'draft', 'name' => 'Draft'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::datepicker('submitted_at', 'submitted_at'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.organization.approval-requests.show', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $request = ApprovalRequest::find($id);

        if (! $request) {
            return;
        }

        if (! in_array($request->status, ['draft', 'cancelled', 'rejected'])) {
            $this->js('Swal.fire("Gagal", "Pengajuan dengan status \''.$request->status.'\' tidak dapat dihapus.", "error");');
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus Pengajuan Approval?",
                text: "Pengajuan \''.$request->subject.'\' akan dihapus permanen dari riwayat.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya, hapus!",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("deleteItem", {id: '.$id.'})
                }
            });
        ');
    }

    #[On('deleteItem')]
    public function deleteItem($id = null): void
    {
        if (! ActivePermission::check('approval-request.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus pengajuan approval.');
            return;
        }

        $request = ApprovalRequest::find($id);

        if (! $request) {
            session()->flash('error', 'Pengajuan tidak ditemukan.');
            return;
        }

        if (! in_array($request->status, ['draft', 'cancelled', 'rejected'])) {
            session()->flash('error', 'Hanya pengajuan draf, dibatalkan, atau ditolak yang dapat dihapus.');
            return;
        }

        $request->update(['deleted_by' => auth()->id()]);
        $request->delete();
        session()->flash('success', 'Pengajuan approval berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-approvalRequestTable');
    }

    public function actions(ApprovalRequest $row): array
    {
        $actions = [];

        if (ActivePermission::check('approval-request.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-primary')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('approval-request.delete') && in_array($row->status, ['draft', 'cancelled', 'rejected'])) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
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
