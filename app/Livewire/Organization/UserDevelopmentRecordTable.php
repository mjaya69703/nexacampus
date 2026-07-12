<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\UserDevelopmentRecord;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class UserDevelopmentRecordTable extends BasePowerGridTable
{
    public string $tableName = 'userDevelopmentRecordTable';

    protected ?string $bulkActionModel = UserDevelopmentRecord::class;
    protected ?string $bulkActionPermissionPrefix = 'user-development-record';
    protected string $bulkActionItemLabel = 'sertifikasi/pelatihan';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return UserDevelopmentRecord::query()
            ->with(['user', 'verifier'])
            ->withCount('attachments')
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'user' => ['first_name', 'last_name', 'email'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('user_name', fn (UserDevelopmentRecord $model) => $model->user?->name ?? '-')
            ->add('type_label', fn (UserDevelopmentRecord $model) => str($model->type)->title())
            ->add('title')
            ->add('organizer')
            ->add('start_date_formatted', fn (UserDevelopmentRecord $model) => $model->start_date ? $model->start_date->format('d M Y') : '-')
            ->add('attachments_count')
            ->add('is_verified')
            ->add('is_verified_label', function (UserDevelopmentRecord $model) {
                if ($model->is_verified) {
                    return '<span class="badge bg-success">Terverifikasi</span>';
                }
                return '<span class="badge bg-warning">Menunggu Verifikasi</span>';
            })
            ->add('created_at_formatted', fn (UserDevelopmentRecord $model) => $model->created_at->format('d/m/Y H:i'));
    }

    public function columns(): array
    {
        return [
            Column::make('Nama User', 'user_name')
                ->searchable(),
            Column::make('Tipe', 'type_label', 'type')
                ->sortable()
                ->searchable(),
            Column::make('Judul', 'title')
                ->sortable()
                ->searchable(),
            Column::make('Penyelenggara', 'organizer')
                ->sortable()
                ->searchable(),
            Column::make('Tanggal Berlaku', 'start_date_formatted', 'start_date')
                ->sortable(),
            Column::make('Lampiran', 'attachments_count')
                ->sortable(),
            Column::make('Status', 'is_verified_label', 'is_verified')
                ->sortable(),
            Column::make('Dibuat Pada', 'created_at_formatted', 'created_at')
                ->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('user_name', 'user_name')
                ->placeholder('Cari nama / email pegawai...')
                ->operators(['contains'])
                ->builder(function (Builder $query, array $values) {
                    $value = $values['value'] ?? null;
                    if (! empty($value)) {
                        $query->whereHas('user', function (Builder $sub) use ($value) {
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
            Filter::inputText('title', 'title')->placeholder('Cari judul pelatihan / sertifikasi...')->operators(['contains']),
            Filter::inputText('organizer', 'organizer')->placeholder('Cari penyelenggara / instansi...')->operators(['contains']),
            Filter::select('type', 'type')
                ->dataSource(collect([
                    ['id' => 'certification', 'name' => 'Certification'],
                    ['id' => 'training', 'name' => 'Training'],
                    ['id' => 'workshop', 'name' => 'Workshop'],
                    ['id' => 'seminar', 'name' => 'Seminar'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::datepicker('start_date', 'start_date'),
            Filter::boolean('is_verified', 'is_verified')->label('Terverifikasi', 'Menunggu'),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.organization.user-development-records.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $record = UserDevelopmentRecord::with('user')->find($id);

        if (! $record) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus riwayat?",
                text: "Riwayat '.$record->title.' akan dipindahkan ke tempat sampah.",
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

    #[On('deleteItem')]
    public function deleteItem($id = null): void
    {
        if (! ActivePermission::check('user-development-record.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus riwayat ini.');
            return;
        }

        $record = UserDevelopmentRecord::find($id);

        if (! $record) {
            session()->flash('error', 'Riwayat tidak ditemukan.');
            return;
        }

        $record->delete();

        session()->flash('success', 'Riwayat pengembangan berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-userDevelopmentRecordTable');
    }

    #[On('verify')]
    public function verify($id): void
    {
        if (! ActivePermission::check('user-development-record.update')) {
            session()->flash('error', 'Anda tidak memiliki izin memverifikasi riwayat ini.');
            return;
        }

        $record = UserDevelopmentRecord::find($id);

        if (! $record) {
            session()->flash('error', 'Riwayat tidak ditemukan.');
            return;
        }

        $record->update([
            'is_verified' => true,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        session()->flash('success', 'Riwayat berhasil diverifikasi.');
        $this->dispatch('pg:eventRefresh-userDevelopmentRecordTable');
    }

    #[On('unverify')]
    public function unverify($id): void
    {
        if (! ActivePermission::check('user-development-record.update')) {
            session()->flash('error', 'Anda tidak memiliki izin mengubah verifikasi riwayat ini.');
            return;
        }

        $record = UserDevelopmentRecord::find($id);

        if (! $record) {
            session()->flash('error', 'Riwayat tidak ditemukan.');
            return;
        }

        $record->update([
            'is_verified' => false,
            'verified_by' => null,
            'verified_at' => null,
        ]);

        session()->flash('success', 'Status riwayat dikembalikan ke menunggu verifikasi.');
        $this->dispatch('pg:eventRefresh-userDevelopmentRecordTable');
    }

    public function actions(UserDevelopmentRecord $row): array
    {
        $actions = [];

        if (ActivePermission::check('user-development-record.update')) {
            if (! $row->is_verified) {
                $actions[] = Button::add('verify')
                    ->slot('<i class="fa fa-check"></i>')
                    ->class('btn btn-success')
                    ->dispatch('verify', ['id' => $row->id]);
            } else {
                $actions[] = Button::add('unverify')
                    ->slot('<i class="fa fa-rotate-left"></i>')
                    ->class('btn btn-warning')
                    ->dispatch('unverify', ['id' => $row->id]);
            }

            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('user-development-record.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
