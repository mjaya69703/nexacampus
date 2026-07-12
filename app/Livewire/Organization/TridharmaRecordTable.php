<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\TridharmaRecord;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class TridharmaRecordTable extends BasePowerGridTable
{
    public string $tableName = 'tridharmaRecordTable';

    protected ?string $bulkActionModel = TridharmaRecord::class;
    protected ?string $bulkActionPermissionPrefix = 'tridharma-record';
    protected string $bulkActionItemLabel = 'record Tridharma';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return TridharmaRecord::query()
            ->with('owner')
            ->withCount(['milestones', 'outputs', 'attachments'])
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'owner' => ['first_name', 'last_name', 'email'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('owner_name', fn (TridharmaRecord $model) => $model->owner?->name ?? '-')
            ->add('type_label', fn (TridharmaRecord $model) => str($model->type)->replace('_', ' ')->title())
            ->add('title')
            ->add('funding_label', fn (TridharmaRecord $model) => 'Rp '.number_format((float) $model->funding_amount, 0, ',', '.'))
            ->add('status_label', fn (TridharmaRecord $model) => '<span class="badge '.$this->statusClass($model->status).'">'.str($model->status)->replace('_', ' ')->title().'</span>')
            ->add('verified_label', fn (TridharmaRecord $model) => $model->is_verified ? '<span class="badge bg-success">Terverifikasi</span>' : '<span class="badge bg-warning">Belum Verifikasi</span>')
            ->add('progress_label', function (TridharmaRecord $model) {
                if ($model->milestones_count === 0) {
                    return '-';
                }

                return $model->milestones()->where('status', 'completed')->count().' / '.$model->milestones_count;
            })
            ->add('created_at_formatted', fn (TridharmaRecord $model) => $model->created_at->format('d/m/Y H:i'));
    }

    public function columns(): array
    {
        return [
            Column::make('Owner', 'owner_name')->searchable(),
            Column::make('Tipe', 'type_label', 'type')->sortable()->searchable(),
            Column::make('Judul', 'title')->sortable()->searchable(),
            Column::make('Dana', 'funding_label', 'funding_amount')->sortable(),
            Column::make('Milestone', 'progress_label'),
            Column::make('Output', 'outputs_count')->sortable(),
            Column::make('Lampiran', 'attachments_count')->sortable(),
            Column::make('Status', 'status_label', 'status')->sortable(),
            Column::make('Verifikasi', 'verified_label', 'is_verified')->sortable(),
            Column::make('Dibuat', 'created_at_formatted', 'created_at')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('owner_name', 'owner_name')
                ->placeholder('Cari nama / NIDN dosen...')
                ->operators(['contains'])
                ->builder(function (Builder $query, array $values) {
                    $value = $values['value'] ?? null;
                    if (! empty($value)) {
                        $query->whereHas('owner', function (Builder $sub) use ($value) {
                            $sub->where(function (Builder $q) use ($value) {
                                $q->where('first_name', 'like', '%' . $value . '%')
                                    ->orWhere('last_name', 'like', '%' . $value . '%')
                                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ['%' . $value . '%'])
                                    ->orWhere('username', 'like', '%' . $value . '%')
                                    ->orWhere('email', 'like', '%' . $value . '%');
                            });
                        });
                    }
                }),
            Filter::inputText('title', 'title')->placeholder('Cari judul penelitian / pengabdian...')->operators(['contains']),
            Filter::select('type', 'type')
                ->dataSource(collect([
                    ['id' => 'research', 'name' => 'Research'],
                    ['id' => 'community_service', 'name' => 'Community Service'],
                    ['id' => 'publication', 'name' => 'Publication'],
                    ['id' => 'intellectual_property', 'name' => 'Intellectual Property'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('status', 'status')
                ->dataSource(collect([
                    ['id' => 'draft', 'name' => 'Draft'],
                    ['id' => 'submitted', 'name' => 'Submitted'],
                    ['id' => 'in_approval', 'name' => 'In Approval'],
                    ['id' => 'approved', 'name' => 'Approved'],
                    ['id' => 'active', 'name' => 'Active'],
                    ['id' => 'completed', 'name' => 'Completed'],
                    ['id' => 'rejected', 'name' => 'Rejected'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::boolean('is_verified', 'is_verified')->label('Terverifikasi', 'Belum Verifikasi'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.organization.tridharma-records.show', ['id' => $rowId]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.organization.tridharma-records.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $record = TridharmaRecord::find($id);

        if (! $record) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus rekam Tridharma?",
                text: "Rekam Tridharma \''.$record->title.'\' akan dihapus dari sistem.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya, hapus!",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("deleteItem", {id: '.$id.'})
                }
            });
        ');
    }

    #[On('deleteItem')]
    public function deleteItem($id = null): void
    {
        if (! ActivePermission::check('tridharma-record.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus record Tridharma.');
            return;
        }

        $record = TridharmaRecord::find($id);

        if (! $record) {
            session()->flash('error', 'Record Tridharma tidak ditemukan.');
            return;
        }

        $record->delete();
        session()->flash('success', 'Record Tridharma berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-tridharmaRecordTable');
    }

    public function actions(TridharmaRecord $row): array
    {
        $actions = [
            Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-info')
                ->dispatch('show', ['rowId' => $row->id]),
        ];

        if (ActivePermission::check('tridharma-record.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('tridharma-record.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }

    private function statusClass(string $status): string
    {
        return match ($status) {
            'approved', 'active', 'completed' => 'bg-success',
            'rejected' => 'bg-danger',
            'in_approval', 'submitted' => 'bg-warning',
            'archived' => 'bg-secondary',
            default => 'bg-muted',
        };
    }
}
