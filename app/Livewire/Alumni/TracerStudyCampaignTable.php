<?php

namespace App\Livewire\Alumni;

use App\Enums\CampaignStatus;
use App\Livewire\BasePowerGridTable;
use App\Models\Academic\AcademicYear;
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
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('title')->placeholder('Cari judul kampanye survei...'),
            Filter::select('academic_year_name', 'academic_year_id')
                ->dataSource(
                    AcademicYear::query()
                        ->orderByDesc('start_date')
                        ->get(['id', 'name'])
                        ->map(fn (AcademicYear $ay) => [
                            'id' => $ay->id,
                            'name' => $ay->name,
                        ])
                )
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('academic_year_id', $value)),
            Filter::select('status', 'status')
                ->dataSource(collect(CampaignStatus::options())
                    ->map(fn ($label, $value) => ['id' => $value, 'name' => $label])
                    ->values()
                    ->toArray()
                )
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::datepicker('created_at_label', 'created_at'),
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
                ->slot('<i class="fa fa-eye"></i> Detail')
                ->class('btn btn-outline-info rounded-pill px-2.5 py-1 text-info fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('tracer-study-campaign.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i> Edit')
                ->class('btn btn-outline-primary rounded-pill px-2.5 py-1 text-primary fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('tracer-study-campaign.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> Hapus')
                ->class('btn btn-outline-danger rounded-pill px-2.5 py-1 text-danger fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'active' => 'bg-success bg-opacity-10 text-success border border-success border-opacity-25',
            'closed' => 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25',
            'draft' => 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25',
            default => 'bg-secondary bg-opacity-10 text-secondary',
        };

        $label = CampaignStatus::tryFrom($status)?->label() ?? str($status)->replace('_', ' ')->title();

        return '<span class="badge rounded-pill px-2.5 py-1 '.$class.'">'.$label.'</span>';
    }
}
