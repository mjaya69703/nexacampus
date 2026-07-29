<?php

namespace App\Livewire\Publication;

use App\Livewire\BasePowerGridTable;
use App\Livewire\Concerns\ExportsPowerGridWithPhpSpreadsheet;
use App\Models\Publication\PublicationCategory;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class PublicationCategoryTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'publicationCategoryTable';

    protected ?string $bulkActionModel = PublicationCategory::class;

    protected ?string $bulkActionPermissionPrefix = 'publication-category';

    protected string $bulkActionItemLabel = 'Kategori';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return PublicationCategory::query()
            ->with(['creator'])
            ->orderBy('sort_order')
            ->orderByDesc('id');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('slug')
            ->add('desc', fn (PublicationCategory $model) => $model->desc
                ? Str::limit(strip_tags($model->desc), 70)
                : '<span class="text-muted fst-italic">—</span>')
            ->add('sort_order')
            ->add('is_active', fn (PublicationCategory $model) => $model->is_active)
            ->add('created_at_formatted', fn (PublicationCategory $model) => $model->created_at?->format('d M Y H:i') ?? '-')
            ->add('total_related', fn (PublicationCategory $model) => $model->news()->count() + $model->agendas()->count() + $model->galleries()->count());
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')
                ->sortable(),

            Column::make('Nama', 'name')
                ->sortable()
                ->searchable(),

            Column::make('Slug', 'slug')
                ->sortable()
                ->searchable(),

            Column::make('Deskripsi', 'desc'),

            Column::make('Total Konten', 'total_related'),

            Column::make('Urutan', 'sort_order')
                ->sortable(),

            Column::make('Status', 'is_active')
                ->toggleable(
                    ActivePermission::check('publication-category.update'),
                    'Aktif',
                    'Nonaktif'
                )
                ->sortable(),

            Column::make('Dibuat', 'created_at_formatted')
                ->sortable(),

            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('name')
                ->placeholder('Cari nama kategori...'),

            Filter::inputText('slug')
                ->placeholder('Cari slug...'),

            Filter::boolean('is_active', 'is_active')
                ->label('Aktif', 'Nonaktif'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('publication-category.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status kategori!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $category = PublicationCategory::find($id);

        if (! $category) {
            session()->flash('error', 'Data kategori tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $category->update([
            'is_active' => (bool) $value,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-'.$this->tableName);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.publication.categories.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $category = PublicationCategory::find($id);

        if ($category) {
            $title = addslashes($category->name);
            $this->js('
                Swal.fire({
                    title: "Hapus Kategori?",
                    text: "'.$title.' - Data tidak bisa dikembalikan!",
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
    }

    #[On('deleteItem')]
    public function deleteItem($id = null): void
    {
        if (! ActivePermission::check('publication-category.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus kategori!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'ID kategori tidak ditemukan!');

            return;
        }

        $category = PublicationCategory::find($id);

        if ($category) {
            $category->update(['deleted_by' => auth()->id()]);
            $category->delete();

            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->js('
                Swal.fire({
                    title: "Dihapus",
                    text: "Kategori berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(PublicationCategory $row): array
    {
        $actions = [];

        if (ActivePermission::check('publication-category.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-pencil"></i> <span>Edit</span>')
                ->class('btn btn-sm btn-primary d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('publication-category.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> <span>Hapus</span>')
                ->class('btn btn-sm btn-danger d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
