<?php

namespace App\Livewire\Publication;

use App\Livewire\BasePowerGridTable;
use App\Livewire\Concerns\ExportsPowerGridWithPhpSpreadsheet;
use App\Models\Publication\News;
use App\Models\Publication\PublicationCategory;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class NewsTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'newsTable';

    protected ?string $bulkActionModel = News::class;

    protected ?string $bulkActionPermissionPrefix = 'news';

    protected string $bulkActionItemLabel = 'berita';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return News::query()
            ->with(['creator', 'category'])
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'creator' => ['name'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('title', fn (News $model) => \Illuminate\Support\Str::limit(strip_tags($model->title), 70))
            ->add('slug')
            ->add('category_name', fn (News $model) => $model->category?->name ?? '-')
            ->add('is_published', fn (News $model) => $model->is_published)
            ->add('published_at_formatted', fn (News $model) => $model->published_at
                ? $model->published_at->format('d M Y H:i')
                : '-')
            ->add('created_at_formatted', fn (News $model) => $model->created_at->format('d M Y H:i'));
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')
                ->sortable(),

            Column::make('Judul', 'title')
                ->sortable()
                ->searchable(),

            Column::make('Slug', 'slug'),

            Column::make('Kategori', 'category_name'),

            Column::make('Status', 'is_published')
                ->toggleable(
                    ActivePermission::check('news.update'),
                    'Published',
                    'Draft'
                )
                ->sortable(),

            Column::make('Dipublikasi', 'published_at_formatted')
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
                ->placeholder('Cari judul berita...'),

            Filter::boolean('is_published', 'is_published')
                ->label('Published', 'Draft'),

            Filter::select('category_id', 'category_id')
                ->dataSource(
                    PublicationCategory::query()
                        ->orderBy('name')
                        ->get()
                        ->map(fn ($category) => [
                            'id' => $category->id,
                            'name' => $category->name,
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

        if (! ActivePermission::check('news.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status publikasi berita!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $news = News::find($id);

        if (! $news) {
            session()->flash('error', 'Data berita tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $updateData = [
            'updated_by' => auth()->id(),
        ];

        if ((bool) $value && ! $news->published_at) {
            $updateData['published_at'] = now();
        }

        $updateData['is_published'] = (bool) $value;
        $news->update($updateData);

        $this->dispatch('pg:eventRefresh-'.$this->tableName);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.publication.news.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $news = News::find($id);

        if ($news) {
            $title = addslashes(\Illuminate\Support\Str::limit($news->title, 40));
            $this->js('
                Swal.fire({
                    title: "Hapus Berita?",
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
        if (! ActivePermission::check('news.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus berita!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'ID berita tidak ditemukan!');

            return;
        }

        $news = News::find($id);

        if ($news) {
            $news->update(['deleted_by' => auth()->id()]);
            $news->delete();

            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->js('
                Swal.fire({
                    title: "Dihapus",
                    text: "Berita berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(News $row): array
    {
        $actions = [];

        if (ActivePermission::check('news.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-pencil"></i> <span>Edit</span>')
                ->class('btn btn-sm btn-primary d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('news.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> <span>Hapus</span>')
                ->class('btn btn-sm btn-danger d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
