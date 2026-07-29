<?php

namespace App\Livewire\Publication;

use App\Livewire\BasePowerGridTable;
use App\Models\Publication\GalleryAlbum;
use App\Models\Publication\PublicationCategory;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;

final class GalleryAlbumTable extends BasePowerGridTable
{
    public string $tableName = 'galleryAlbumTable';

    protected ?string $bulkActionModel = GalleryAlbum::class;

    protected ?string $bulkActionPermissionPrefix = 'gallery';

    protected string $bulkActionItemLabel = 'album';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return GalleryAlbum::query()
            ->with(['creator', 'category'])
            ->withCount('images')
            ->orderByDesc('id');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('title', fn (GalleryAlbum $model) => Str::limit($model->title, 50))
            ->add('slug')
            ->add('cover_thumbnail', function (GalleryAlbum $model) {
                if ($model->cover_image_path) {
                    $url = \Storage::disk('public')->url($model->cover_image_path);

                    return '<img src="'.e($url).'" alt="Cover" style="width:60px;height:60px;object-fit:cover;border-radius:6px;">';
                }

                return '<div style="width:60px;height:60px;border-radius:6px;background:#e9ecef;display:flex;align-items:center;justify-content:center;font-size:18px;color:#adb5bd;"><i class="fa fa-image"></i></div>';
            })
            ->add('category_name', fn (GalleryAlbum $model) => $model->category?->name ?? '-')
            ->add('images_count')
            ->add('is_published', fn (GalleryAlbum $model) => $model->is_published)
            ->add('created_at_formatted', fn (GalleryAlbum $model) => $model->created_at?->format('d M Y H:i') ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')
                ->sortable(),

            Column::make('Judul', 'title')
                ->sortable()
                ->searchable(),

            Column::make('Sampul', 'cover_thumbnail'),

            Column::make('Kategori', 'category_name')
                ->sortable(),

            Column::make('Jumlah Foto', 'images_count')
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
                ->placeholder('Cari album...'),

            Filter::boolean('is_published', 'is_published')
                ->label('Published', 'Draft'),

            Filter::select('category_id', 'category_id')
                ->dataSource(
                    PublicationCategory::orderBy('name')->get()
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
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status album!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $album = GalleryAlbum::find($id);

        if (! $album) {
            session()->flash('error', 'Data album tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $album->update([
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
        $album = GalleryAlbum::find($id);

        if ($album) {
            $title = addslashes(Str::limit($album->title, 40));
            $this->js('
                Swal.fire({
                    title: "Hapus Album?",
                    text: "'.$title.' - Semua foto dalam album akan ikut terhapus!",
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
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus album!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'ID album tidak ditemukan!');

            return;
        }

        $album = GalleryAlbum::with('images')->find($id);

        if ($album) {
            // Delete all image files from storage
            foreach ($album->images as $image) {
                if ($image->image_path && \Storage::disk('public')->exists($image->image_path)) {
                    \Storage::disk('public')->delete($image->image_path);
                }
            }

            // Delete cover image if exists
            if ($album->cover_image_path && \Storage::disk('public')->exists($album->cover_image_path)) {
                \Storage::disk('public')->delete($album->cover_image_path);
            }

            $album->update(['deleted_by' => auth()->id()]);
            $album->delete();

            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->js('
                Swal.fire({
                    title: "Dihapus",
                    text: "Album beserta foto berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(GalleryAlbum $row): array
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
