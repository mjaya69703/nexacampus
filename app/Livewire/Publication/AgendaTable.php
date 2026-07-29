<?php

namespace App\Livewire\Publication;

use App\Livewire\BasePowerGridTable;
use App\Models\Publication\Agenda;
use App\Models\Publication\PublicationCategory;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class AgendaTable extends BasePowerGridTable
{
    public string $tableName = 'agendaTable';

    protected ?string $bulkActionModel = Agenda::class;

    protected ?string $bulkActionPermissionPrefix = 'agenda';

    protected string $bulkActionItemLabel = 'Agenda';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return Agenda::query()
            ->with(['creator', 'category'])
            ->orderByDesc('event_date')
            ->orderByDesc('event_time');
    }

    public function relationSearch(): array
    {
        return [
            'category' => ['name'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('title')
            ->add('slug')
            ->add('category_name', fn (Agenda $model) => $model->category?->name ?? '-')
            ->add('event_date_formatted', fn (Agenda $model) => $model->event_date?->format('d M Y') ?? '-')
            ->add('event_time_formatted', fn (Agenda $model) => $model->event_time ? substr($model->event_time, 0, 5) : '-')
            ->add('location')
            ->add('is_published', fn (Agenda $model) => $model->is_published)
            ->add('created_at_formatted', fn (Agenda $model) => $model->created_at?->format('d M Y H:i') ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')
                ->sortable(),

            Column::make('Judul', 'title')
                ->sortable()
                ->searchable(),

            Column::make('Kategori', 'category_name')
                ->sortable()
                ->searchable(),

            Column::make('Tanggal', 'event_date_formatted')
                ->sortable(),

            Column::make('Jam', 'event_time_formatted')
                ->sortable(),

            Column::make('Lokasi', 'location')
                ->sortable()
                ->searchable(),

            Column::make('Status', 'is_published')
                ->toggleable(
                    ActivePermission::check('agenda.update'),
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
                ->placeholder('Cari judul agenda...'),

            Filter::datepicker('event_date', 'event_date'),

            Filter::boolean('is_published', 'is_published')
                ->label('Published', 'Draft'),

            Filter::select('category_id', 'category_id')
                ->dataSource(
                    PublicationCategory::orderBy('name')
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

        if (! ActivePermission::check('agenda.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status Agenda!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $agenda = Agenda::find($id);

        if (! $agenda) {
            session()->flash('error', 'Data Agenda tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $agenda->update([
            'is_published' => (bool) $value,
            'published_at' => (bool) $value ? now() : null,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-'.$this->tableName);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.publication.agendas.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $agenda = Agenda::find($id);

        if ($agenda) {
            $title = addslashes(\Illuminate\Support\Str::limit($agenda->title, 40));
            $this->js('
                Swal.fire({
                    title: "Hapus Agenda?",
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
        if (! ActivePermission::check('agenda.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus Agenda!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'ID Agenda tidak ditemukan!');

            return;
        }

        $agenda = Agenda::find($id);

        if ($agenda) {
            $agenda->update(['deleted_by' => auth()->id()]);
            $agenda->delete();

            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->js('
                Swal.fire({
                    title: "Dihapus",
                    text: "Agenda berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(Agenda $row): array
    {
        $actions = [];

        if (ActivePermission::check('agenda.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-pencil"></i> <span>Edit</span>')
                ->class('btn btn-sm btn-primary d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('agenda.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> <span>Hapus</span>')
                ->class('btn btn-sm btn-danger d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
