<?php

namespace App\Livewire\Alumni;

use App\Livewire\BasePowerGridTable;
use App\Models\Alumni\EmployerPartner;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class EmployerPartnerTable extends BasePowerGridTable
{
    public string $tableName = 'employerPartnerTable';

    protected ?string $bulkActionModel = EmployerPartner::class;

    protected ?string $bulkActionPermissionPrefix = 'employer-partner';

    protected string $bulkActionItemLabel = 'mitra perusahaan';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return EmployerPartner::query()
            ->withCount('jobPostings')
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('industry')
            ->add('city')
            ->add('website')
            ->add('contact_person')
            ->add('contact_email')
            ->add('is_active')
            ->add('job_postings_count')
            ->add('created_at_label', fn (EmployerPartner $m) => $m->created_at?->format('d M Y'));
    }

    public function columns(): array
    {
        return [
            Column::make('Nama', 'name')->sortable()->searchable(),
            Column::make('Industri', 'industry')->sortable()->searchable(),
            Column::make('Kota', 'city')->sortable()->searchable(),
            Column::make('Website', 'website')->searchable(),
            Column::make('Kontak', 'contact_person')->searchable(),
            Column::make('Lowongan', 'job_postings_count')->sortable(),
            Column::make('Aktif', 'is_active')
                ->toggleable(
                    ActivePermission::check('employer-partner.update'),
                    'Aktif',
                    'Nonaktif'
                )
                ->sortable(),
            Column::make('Dibuat', 'created_at_label')->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('is_active', 'is_active')
                ->dataSource([
                    ['id' => '1', 'name' => 'Aktif'],
                    ['id' => '0', 'name' => 'Nonaktif'],
                ])
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('employer-partner.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status mitra!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $partner = EmployerPartner::find($id);

        if ($partner) {
            $partner->update([
                'is_active' => (bool) $value,
                'updated_by' => auth()->id(),
            ]);
        }

        $this->dispatch('pg:eventRefresh-'.$this->tableName);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.alumni.employer-partners.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $partner = EmployerPartner::find($id);

        if ($partner) {
            $this->js('
                Swal.fire({
                    title: "Hapus mitra perusahaan?",
                    text: "'.$partner->name.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('employer-partner.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus mitra perusahaan!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id mitra perusahaan tidak ditemukan!');

            return;
        }

        $partner = EmployerPartner::find($id);

        if ($partner) {
            $name = $partner->name;
            $partner->delete();

            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->js('
                Swal.fire({
                    title: "Mitra perusahaan dihapus",
                    text: "'.$name.' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(EmployerPartner $row): array
    {
        $actions = [];

        if (ActivePermission::check('employer-partner.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('employer-partner.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
