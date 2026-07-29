<?php

namespace App\Livewire\Campus;

use App\Livewire\BasePowerGridTable;
use App\Models\Campus\RoomReservation;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class RoomReservationsTable extends BasePowerGridTable
{
    public string $tableName = 'roomReservationsTable';

    protected ?string $bulkActionModel = RoomReservation::class;
    protected ?string $bulkActionPermissionPrefix = 'room-reservation';
    protected string $bulkActionItemLabel = 'peminjaman ruangan';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return RoomReservation::query()
            ->with(['room', 'user'])
            ->orderByDesc('created_at');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('reservation_number')
            ->add('title')
            ->add('room_name', fn ($row) => $row->room?->name ?? '-')
            ->add('user_name', fn ($row) => $row->user?->name ?? '-')
            ->add('reservation_date_formatted', fn ($row) => $row->reservation_date?->format('d M Y'))
            ->add('time_range', fn ($row) => substr($row->start_time, 0, 5) . ' - ' . substr($row->end_time, 0, 5))
            ->add('status_badge', function ($row) {
                return match ($row->status) {
                    'approved' => '<span class="badge bg-success text-white">Disetujui</span>',
                    'rejected' => '<span class="badge bg-danger text-white">Ditolak</span>',
                    'cancelled' => '<span class="badge bg-secondary text-white">Dibatalkan</span>',
                    default => '<span class="badge bg-warning text-white">Menunggu Approval</span>',
                };
            });
    }

    public function columns(): array
    {
        return [
            Column::make('No. Reservasi', 'reservation_number')->sortable()->searchable(),
            Column::make('Judul Kegiatan', 'title')->sortable()->searchable(),
            Column::make('Ruangan', 'room_name'),
            Column::make('Pemohon', 'user_name'),
            Column::make('Tanggal', 'reservation_date_formatted'),
            Column::make('Waktu', 'time_range'),
            Column::make('Status', 'status_badge'),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('reservation_number')->placeholder('Cari no. reservasi...'),
            Filter::inputText('title')->placeholder('Cari judul...'),
            Filter::select('status', 'status')
                ->dataSource([
                    ['id' => 'pending', 'name' => 'Menunggu Approval'],
                    ['id' => 'approved', 'name' => 'Disetujui'],
                    ['id' => 'rejected', 'name' => 'Ditolak'],
                    ['id' => 'cancelled', 'name' => 'Dibatalkan'],
                ])
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('status', $value)),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.campus.reservations.show', ['id' => $rowId]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.campus.reservations.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $item = RoomReservation::find($id);
        if ($item) {
            $this->js('
                Swal.fire({
                    title: "Hapus reservasi?",
                    text: "'.addslashes($item->reservation_number).' - Data tidak dapat dikembalikan!",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ya, Hapus",
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
        if (! ActivePermission::check('room-reservation.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin!');
            return;
        }

        $item = RoomReservation::find($id);
        if ($item) {
            $num = $item->reservation_number;
            $item->delete();
            $this->dispatch('pg:eventRefresh-roomReservationsTable');
            $this->js('
                Swal.fire({
                    title: "Reservasi Dihapus",
                    text: "'.addslashes($num).' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(RoomReservation $row): array
    {
        $actions = [];
        if (ActivePermission::check('room-reservation.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-info')
                ->dispatch('show', ['rowId' => $row->id]);
        }
        if (ActivePermission::check('room-reservation.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }
        if (ActivePermission::check('room-reservation.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }
        return $actions;
    }
}
