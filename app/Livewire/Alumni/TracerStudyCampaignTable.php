<?php

namespace App\Livewire\Alumni;

use App\Enums\CampaignStatus;
use App\Livewire\BasePowerGridTable;
use App\Models\Alumni\TracerStudyCampaign;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class TracerStudyCampaignTable extends BasePowerGridTable
{
    public string $tableName = 'tracerStudyCampaignTable';

    protected ?string $bulkActionModel = TracerStudyCampaign::class;

    protected ?string $bulkActionPermissionPrefix = 'tracer-study-campaign';

    protected string $bulkActionItemLabel = 'kampanye tracer study';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return TracerStudyCampaign::query()
            ->with(['academicYear'])
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'academicYear' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('title')
            ->add('academic_year_name', fn (TracerStudyCampaign $m) => $m->academicYear?->name ?? '-')
            ->add('period_label', fn (TracerStudyCampaign $m) => ($m->start_date?->format('d M Y') ?? '-').' s/d '.($m->end_date?->format('d M Y') ?? '-'))
            ->add('status_badge', fn (TracerStudyCampaign $m) => $this->statusBadge($m->status))
            ->add('status')
            ->add('total_sent')
            ->add('total_responded')
            ->add('response_rate_label', fn (TracerStudyCampaign $m) => $m->responseRate().'%')
            ->add('created_at_label', fn (TracerStudyCampaign $m) => $m->created_at?->format('d M Y'));
    }

    public function columns(): array
    {
        return [
            Column::make('Judul', 'title')->sortable()->searchable(),
            Column::make('Tahun Akademik', 'academic_year_name')->sortable()->searchable(),
            Column::make('Periode', 'period_label')->sortable(),
            Column::make('Status', 'status_badge'),
            Column::make('Terkirim', 'total_sent')->sortable(),
            Column::make('Direspon', 'total_responded')->sortable(),
            Column::make('Response Rate', 'response_rate_label')->sortable(),
            Column::make('Dibuat', 'created_at_label')->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status', 'status')
                ->dataSource(collect(CampaignStatus::options())
                    ->map(fn ($label, $value) => ['id' => $value, 'name' => $label])
                    ->values()
                    ->toArray()
                )
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.alumni.tracer-study.show', ['id' => $rowId]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.alumni.tracer-study.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $campaign = TracerStudyCampaign::find($id);

        if ($campaign) {
            $this->js('
                Swal.fire({
                    title: "Hapus kampanye tracer study?",
                    text: "'.$campaign->title.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('tracer-study-campaign.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus kampanye tracer study!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id kampanye tracer study tidak ditemukan!');

            return;
        }

        $campaign = TracerStudyCampaign::find($id);

        if ($campaign) {
            $title = $campaign->title;
            $campaign->delete();

            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->js('
                Swal.fire({
                    title: "Kampanye tracer study dihapus",
                    text: "'.$title.' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(TracerStudyCampaign $row): array
    {
        $actions = [];

        if (ActivePermission::check('tracer-study-campaign.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-primary')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('tracer-study-campaign.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-warning')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('tracer-study-campaign.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'active' => 'bg-success',
            'closed' => 'bg-secondary',
            'draft' => 'bg-warning text-dark',
            default => 'bg-secondary',
        };

        $label = CampaignStatus::tryFrom($status)?->label() ?? str($status)->replace('_', ' ')->title();

        return '<span class="badge '.$class.'">'.$label.'</span>';
    }
}
