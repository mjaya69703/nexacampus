<?php

namespace App\Livewire\Campus;

use App\Livewire\BasePowerGridTable;
use App\Models\Campus\FacilityMaintenanceTicket;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class FacilityMaintenanceTicketsTable extends BasePowerGridTable
{
    public string $tableName = 'facilityMaintenanceTicketsTable';

    protected ?string $bulkActionModel = FacilityMaintenanceTicket::class;
    protected ?string $bulkActionPermissionPrefix = 'facility-maintenance';
    protected string $bulkActionItemLabel = 'laporan kerusakan';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return FacilityMaintenanceTicket::query()
            ->with(['room', 'campusAsset', 'reporter', 'assignee'])
            ->orderByDesc('created_at');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('ticket_number')
            ->add('title')
            ->add('location', fn ($row) => $row->room?->name ?? '-')
            ->add('asset_name', fn ($row) => $row->campusAsset?->name ?? '-')
            ->add('reporter_name', fn ($row) => $row->reporter?->name ?? '-')
            ->add('priority_badge', function ($row) {
                return match ($row->priority) {
                    'urgent' => '<span class="badge bg-danger text-white">Sangat Mendesak</span>',
                    'high' => '<span class="badge bg-warning text-white">Tinggi</span>',
                    'medium' => '<span class="badge bg-info text-white">Sedang</span>',
                    default => '<span class="badge bg-secondary text-white">Rendah</span>',
                };
            })
            ->add('status_badge', function ($row) {
                return match ($row->status) {
                    'open' => '<span class="badge bg-primary text-white">Baru / Terbuka</span>',
                    'in_progress' => '<span class="badge bg-warning text-white">Dalam Pengerjaan</span>',
                    'resolved' => '<span class="badge bg-success text-white">Selesai Ditangani</span>',
                    'closed' => '<span class="badge bg-secondary text-white">Ditutup</span>',
                    'rejected' => '<span class="badge bg-danger text-white">Ditolak</span>',
                    default => '<span class="badge bg-secondary text-white">-</span>',
                };
            });
    }

    public function columns(): array
    {
        return [
            Column::make('No. Tiket', 'ticket_number')->sortable()->searchable(),
            Column::make('Judul Kerusakan', 'title')->sortable()->searchable(),
            Column::make('Lokasi Ruangan', 'location'),
            Column::make('Pelapor', 'reporter_name'),
            Column::make('Prioritas', 'priority_badge'),
            Column::make('Status', 'status_badge'),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('ticket_number')->placeholder('Cari tiket...'),
            Filter::inputText('title')->placeholder('Cari judul...'),
            Filter::select('priority', 'priority')
                ->dataSource([
                    ['id' => 'low', 'name' => 'Rendah'],
                    ['id' => 'medium', 'name' => 'Sedang'],
                    ['id' => 'high', 'name' => 'Tinggi'],
                    ['id' => 'urgent', 'name' => 'Sangat Mendesak'],
                ])
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('priority', $value)),
            Filter::select('status', 'status')
                ->dataSource([
                    ['id' => 'open', 'name' => 'Terbuka'],
                    ['id' => 'in_progress', 'name' => 'Dalam Pengerjaan'],
                    ['id' => 'resolved', 'name' => 'Selesai'],
                    ['id' => 'closed', 'name' => 'Ditutup'],
                ])
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('status', $value)),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.campus.maintenance.show', ['id' => $rowId]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.campus.maintenance.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $item = FacilityMaintenanceTicket::find($id);
        if ($item) {
            $this->js('
                Swal.fire({
                    title: "Hapus tiket?",
                    text: "'.addslashes($item->ticket_number).' - Data tidak dapat dikembalikan!",
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
        if (! ActivePermission::check('facility-maintenance.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin!');
            return;
        }

        $item = FacilityMaintenanceTicket::find($id);
        if ($item) {
            $num = $item->ticket_number;
            $item->delete();
            $this->dispatch('pg:eventRefresh-facilityMaintenanceTicketsTable');
            $this->js('
                Swal.fire({
                    title: "Tiket Dihapus",
                    text: "'.addslashes($num).' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(FacilityMaintenanceTicket $row): array
    {
        $actions = [];
        if (ActivePermission::check('facility-maintenance.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-info')
                ->dispatch('show', ['rowId' => $row->id]);
        }
        if (ActivePermission::check('facility-maintenance.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }
        if (ActivePermission::check('facility-maintenance.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }
        return $actions;
    }
}
