<?php

namespace App\Livewire\Alumni;

use App\Enums\JobType;
use App\Livewire\BasePowerGridTable;
use App\Models\Alumni\EmployerPartner;
use App\Models\Alumni\JobPosting;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class JobPostingTable extends BasePowerGridTable
{
    public string $tableName = 'jobPostingTable';

    protected ?string $bulkActionModel = JobPosting::class;

    protected ?string $bulkActionPermissionPrefix = 'job-posting';

    protected string $bulkActionItemLabel = 'lowongan kerja';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return JobPosting::query()
            ->with(['employerPartner'])
            ->orderByDesc('posted_date');
    }

    public function relationSearch(): array
    {
        return [
            'employerPartner' => ['name'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('title')
            ->add('company_name', fn (JobPosting $m) => $m->employerPartner?->name ?? '-')
            ->add('job_type_label', fn (JobPosting $m) => JobType::tryFrom($m->job_type)?->label() ?? $m->job_type)
            ->add('location')
            ->add('salary_range')
            ->add('deadline_label', fn (JobPosting $m) => $m->application_deadline?->format('d M Y') ?? '-')
            ->add('is_active')
            ->add('posted_date_label', fn (JobPosting $m) => $m->posted_date?->format('d M Y') ?? '-')
            ->add('created_at_label', fn (JobPosting $m) => $m->created_at?->format('d M Y'));
    }

    public function columns(): array
    {
        return [
            Column::make('Judul', 'title')->sortable()->searchable(),
            Column::make('Perusahaan', 'company_name')->sortable()->searchable(),
            Column::make('Tipe', 'job_type_label')->sortable()->searchable(),
            Column::make('Lokasi', 'location')->sortable()->searchable(),
            Column::make('Deadline', 'deadline_label')->sortable(),
            Column::make('Aktif', 'is_active')
                ->toggleable(
                    ActivePermission::check('job-posting.update'),
                    'Aktif',
                    'Nonaktif'
                )
                ->sortable(),
            Column::make('Diposting', 'posted_date_label')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('title')->placeholder('Cari judul lowongan...'),
            Filter::select('company_name', 'employer_partner_id')
                ->dataSource(
                    EmployerPartner::query()
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->map(fn (EmployerPartner $ep) => [
                            'id' => $ep->id,
                            'name' => $ep->name,
                        ])
                )
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('employer_partner_id', $value)),
            Filter::select('job_type_label', 'job_type')
                ->dataSource(collect(JobType::options())
                    ->map(fn ($label, $value) => ['id' => $value, 'name' => $label])
                    ->values()
                    ->toArray()
                )
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('job_type', $value)),
            Filter::inputText('location')->placeholder('Cari lokasi / penempatan...'),
            Filter::boolean('is_active', 'Aktif', 'Nonaktif'),
            Filter::datepicker('posted_date_label', 'posted_date'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('job-posting.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status lowongan!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $posting = JobPosting::find($id);

        if ($posting) {
            $posting->update([
                'is_active' => (bool) $value,
                'updated_by' => auth()->id(),
            ]);
        }

        $this->dispatch('pg:eventRefresh-'.$this->tableName);
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.alumni.job-postings.show', ['id' => $rowId]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.alumni.job-postings.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $posting = JobPosting::find($id);

        if ($posting) {
            $this->js('
                Swal.fire({
                    title: "Hapus lowongan kerja?",
                    text: "'.$posting->title.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('job-posting.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus lowongan kerja!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id lowongan kerja tidak ditemukan!');

            return;
        }

        $posting = JobPosting::find($id);

        if ($posting) {
            $title = $posting->title;
            $posting->delete();

            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->js('
                Swal.fire({
                    title: "Lowongan kerja dihapus",
                    text: "'.$title.' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(JobPosting $row): array
    {
        $actions = [];

        if (ActivePermission::check('job-posting.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i> Detail')
                ->class('btn btn-outline-info rounded-pill px-2.5 py-1 text-info fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('job-posting.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i> Edit')
                ->class('btn btn-outline-primary rounded-pill px-2.5 py-1 text-primary fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('job-posting.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> Hapus')
                ->class('btn btn-outline-danger rounded-pill px-2.5 py-1 text-danger fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
