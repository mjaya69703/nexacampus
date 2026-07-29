<?php

namespace App\Livewire\Publication;

use App\Livewire\BasePowerGridTable;
use App\Livewire\Concerns\ExportsPowerGridWithPhpSpreadsheet;
use App\Models\Publication\Gallery;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class GalleryTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'galleryTable';

    protected ?string $bulkActionModel = Gallery::class;

    protected ?string $bulkActionPermissionPrefix = 'gallery';

    protected string $bulkActionItemLabel = 'gambar';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return Gallery::query()
            ->with(['creator', 'category'])
            ->orderByDesc('id');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('title', fn (Gallery $model) => \Illuminate\Support\Str::limit($model->title, 50))
            ->add('image_thumbnail', function (Gallery $model) {
                $url = \Storage::disk('public')->url($model->image_path);

                return '<img src="'.e($url).'" alt="'.e($model->image_alt ?? $model->title).'" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">';
            })
            ->add('category_name', fn (Gallery $model) => $model->category?->name ?? '-')
            ->add('is_published', fn (Gallery $model) => $model->is_published)
            ->add('created_at_formatted', fn (Gallery $model) => $model->created_at?->format('d M Y H:i') ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')
                ->sortable(),

            Column::make('Judul', 'title')
                ->sortable()
                ->searchable(),

            Column::make('Gambar', 'image_thumbnail'),

            Column::make('Kategori', 'category_name')
                ->sortable(),

            Column::make('Status', 'is_published')
                ->toggleable(
                    ActivePermission::check('gallery.update'),
                    'Published',
                    'Draft'
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
            Filter::inputText('title')
                ->placeholder('Cari judul...'),

            Filter::boolean('is_published', 'is_published')
                ->label('Published', 'Draft'),

            Filter::select('category_id', 'category_id')
                ->dataSource(
                    \App\Models\Publication\PublicationCategory::query()
                        ->orderBy('name')
                        ->get()
                        ->map(fn ($cat) => [
                            'id' => $cat->id,
                            'name' => $cat->name,
                        ])
                )
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_published') {
            return;
        }

        if (! ActivePermission::check('gallery.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status publikasi!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $gallery = Gallery::find($id);

        if (! $gallery) {
            session()->flash('error', 'Data galeri tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $gallery->update([
            'is_published' => (bool) $value,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-'.$this->tableName);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.publication.galleries.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $gallery = Gallery::find($id);

        if ($gallery) {
            $title = addslashes(\Illuminate\Support\Str::limit($gallery->title, 40));
            $this->js('
                Swal.fire({
                    title: "Hapus Gambar?",
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
        if (! ActivePermission::check('gallery.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus gambar!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'ID galeri tidak ditemukan!');

            return;
        }

        $gallery = Gallery::find($id);

        if ($gallery) {
            // Delete image file
            if ($gallery->image_path && \Storage::disk('public')->exists($gallery->image_path)) {
                \Storage::disk('public')->delete($gallery->image_path);
            }

            $gallery->update(['deleted_by' => auth()->id()]);
            $gallery->delete();

            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->js('
                Swal.fire({
                    title: "Dihapus",
                    text: "Gambar berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(Gallery $row): array
    {
        $actions = [];

        if (ActivePermission::check('gallery.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-pencil"></i> <span>Edit</span>')
                ->class('btn btn-sm btn-primary d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('gallery.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> <span>Hapus</span>')
                ->class('btn btn-sm btn-danger d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
