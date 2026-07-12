<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\LecturerWorkloadRule;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class LecturerWorkloadRuleTable extends BasePowerGridTable
{
    public string $tableName = 'lecturerWorkloadRuleTable';

    protected ?string $bulkActionModel = LecturerWorkloadRule::class;

    protected ?string $bulkActionPermissionPrefix = 'lecturer-workload-rule';

    protected string $bulkActionItemLabel = 'aturan SKS';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return LecturerWorkloadRule::query()->orderBy('category')->orderBy('source_code');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('category')
            ->add('source_code')
            ->add('name')
            ->add('sks_value')
            ->add('maximum_sks', fn (LecturerWorkloadRule $model) => $model->maximum_sks ?: '-')
            ->add('active_badge', fn (LecturerWorkloadRule $model) => $model->is_active ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>');
    }

    public function columns(): array
    {
        return [
            Column::make('Kategori', 'category')->sortable()->searchable(),
            Column::make('Kode Sumber', 'source_code')->sortable()->searchable(),
            Column::make('Nama', 'name')->searchable(),
            Column::make('SKS', 'sks_value')->sortable(),
            Column::make('Maksimum', 'maximum_sks'),
            Column::make('Status', 'active_badge', 'is_active')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('source_code', 'source_code')->placeholder('Cari kode sumber/modul...')->operators(['contains']),
            Filter::inputText('name', 'name')->placeholder('Cari nama aturan beban kerja...')->operators(['contains']),
            Filter::select('category', 'category')
                ->dataSource(collect([
                    ['id' => 'teaching', 'name' => 'Mengajar'],
                    ['id' => 'structural', 'name' => 'Jabatan'],
                    ['id' => 'tridharma', 'name' => 'Tridharma'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::boolean('is_active', 'is_active')->label('Aktif', 'Nonaktif'),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.organization.lecturer-workload-rules.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $rule = LecturerWorkloadRule::find($id);

        if (! $rule) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus Aturan BKD?",
                text: "Aturan BKD \''.$rule->name.'\' akan dihapus/dinonaktifkan.",
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
        if (! ActivePermission::check('lecturer-workload-rule.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus aturan BKD.');
            return;
        }

        $rule = LecturerWorkloadRule::find($id);

        if (! $rule) {
            session()->flash('error', 'Aturan BKD tidak ditemukan.');
            return;
        }

        $rule->delete();
        session()->flash('success', 'Aturan BKD berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-lecturerWorkloadRuleTable');
    }

    public function actions(LecturerWorkloadRule $row): array
    {
        $actions = [];

        if (ActivePermission::check('lecturer-workload-rule.update')) {
            $actions[] = Button::add('edit')->slot('<i class="fa fa-edit"></i>')->class('btn btn-primary')->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('lecturer-workload-rule.delete')) {
            $actions[] = Button::add('delete')->slot('<i class="fa fa-trash"></i>')->class('btn btn-danger')->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
