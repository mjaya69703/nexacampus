<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\ApprovalTemplate;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class ApprovalTemplateTable extends BasePowerGridTable
{
    public string $tableName = 'approvalTemplateTable';

    protected ?string $bulkActionModel = ApprovalTemplate::class;

    protected ?string $bulkActionPermissionPrefix = 'approval-template';

    protected string $bulkActionItemLabel = 'template approval';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return ApprovalTemplate::query()
            ->withCount('steps')
            ->orderBy('module')
            ->orderBy('name');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('name')
            ->add('code')
            ->add('module')
            ->add('steps_count')
            ->add('is_active')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Nama', 'name')->sortable()->searchable(),
            Column::make('Kode', 'code')->sortable()->searchable(),
            Column::make('Module', 'module')->sortable()->searchable(),
            Column::make('Step', 'steps_count')->sortable(),
            Column::make('Aktif', 'is_active')
                ->toggleable(ActivePermission::check('approval-template.update'), 'Aktif', 'Nonaktif')
                ->sortable(),
            Column::make('Dibuat', 'created_at')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active' || ! ActivePermission::check('approval-template.update')) {
            return;
        }

        ApprovalTemplate::whereKey($id)->update([
            'is_active' => (bool) $value,
            'updated_by' => auth()->id(),
        ]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.organization.approval-templates.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $template = ApprovalTemplate::find($id);

        if (! $template) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus template approval?",
                text: "'.$template->name.' akan dipindahkan ke tempat sampah.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya hapus",
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
        if (! ActivePermission::check('approval-template.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus template approval.');

            return;
        }

        $template = ApprovalTemplate::find($id);

        if (! $template) {
            return;
        }

        $template->update(['deleted_by' => auth()->id()]);
        $template->delete();
        session()->flash('success', 'Template approval berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-approvalTemplateTable');
    }

    public function actions(ApprovalTemplate $row): array
    {
        $actions = [];

        if (ActivePermission::check('approval-template.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('approval-template.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
