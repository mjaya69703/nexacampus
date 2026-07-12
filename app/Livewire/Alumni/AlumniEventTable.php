<?php

namespace App\Livewire\Alumni;

use App\Enums\EventType;
use App\Livewire\BasePowerGridTable;
use App\Models\Alumni\AlumniEvent;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class AlumniEventTable extends BasePowerGridTable
{
    public string $tableName = 'alumniEventTable';

    protected ?string $bulkActionModel = AlumniEvent::class;

    protected ?string $bulkActionPermissionPrefix = 'alumni-event';

    protected string $bulkActionItemLabel = 'event alumni';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return AlumniEvent::query()
            ->withCount('participants')
            ->orderByDesc('event_date');
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('title')
            ->add('event_type_label', fn (AlumniEvent $m) => EventType::tryFrom($m->event_type)?->label() ?? $m->event_type)
            ->add('event_date_label', fn (AlumniEvent $m) => $m->event_date?->format('d M Y H:i') ?? '-')
            ->add('location_mode', fn (AlumniEvent $m) => $m->is_online ? 'Online' : 'Offline')
            ->add('is_published')
            ->add('participants_count')
            ->add('max_participants')
            ->add('slots_label', fn (AlumniEvent $m) => $m->max_participants
                ? ($m->max_participants - $m->participants_count).'/'.$m->max_participants
                : '-'
            )
            ->add('created_at_label', fn (AlumniEvent $m) => $m->created_at?->format('d M Y'));
    }

    public function columns(): array
    {
        return [
            Column::make('Judul', 'title')->sortable()->searchable(),
            Column::make('Tipe', 'event_type_label')->sortable()->searchable(),
            Column::make('Tanggal', 'event_date_label')->sortable(),
            Column::make('Mode', 'location_mode')->sortable(),
            Column::make('Published', 'is_published')
                ->toggleable(
                    ActivePermission::check('alumni-event.update'),
                    'Ya',
                    'Tidak'
                )
                ->sortable(),
            Column::make('Peserta', 'slots_label')->sortable(),
            Column::make('Dibuat', 'created_at_label')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('title')->placeholder('Cari judul kegiatan/event...'),
            Filter::select('event_type_label', 'event_type')
                ->dataSource(collect(EventType::options())
                    ->map(fn ($label, $value) => ['id' => $value, 'name' => $label])
                    ->values()
                    ->toArray()
                )
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('event_type', $value)),
            Filter::boolean('is_online', 'Online (Virtual)', 'Offline (Tatap Muka)'),
            Filter::boolean('is_published', 'Published', 'Draft'),
            Filter::datepicker('event_date_label', 'event_date'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_published') {
            return;
        }

        if (! ActivePermission::check('alumni-event.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status publikasi!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $event = AlumniEvent::find($id);

        if ($event) {
            $event->update([
                'is_published' => (bool) $value,
                'updated_by' => auth()->id(),
            ]);
        }

        $this->dispatch('pg:eventRefresh-'.$this->tableName);
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.alumni.events.show', ['id' => $rowId]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.alumni.events.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $event = AlumniEvent::find($id);

        if ($event) {
            $this->js('
                Swal.fire({
                    title: "Hapus event alumni?",
                    text: "'.$event->title.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('alumni-event.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus event alumni!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id event alumni tidak ditemukan!');

            return;
        }

        $event = AlumniEvent::find($id);

        if ($event) {
            $title = $event->title;
            $event->delete();

            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->js('
                Swal.fire({
                    title: "Event alumni dihapus",
                    text: "'.$title.' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(AlumniEvent $row): array
    {
        $actions = [];

        if (ActivePermission::check('alumni-event.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i> Detail')
                ->class('btn btn-outline-info rounded-pill px-2.5 py-1 text-info fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('alumni-event.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i> Edit')
                ->class('btn btn-outline-primary rounded-pill px-2.5 py-1 text-primary fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('alumni-event.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> Hapus')
                ->class('btn btn-outline-danger rounded-pill px-2.5 py-1 text-danger fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
