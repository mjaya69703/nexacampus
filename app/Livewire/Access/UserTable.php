<?php

namespace App\Livewire\Access;

use App\Livewire\BasePowerGridTable;
use App\Models\Access\Role;
use App\Models\User;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class UserTable extends BasePowerGridTable
{
    public string $tableName = 'userTable';

    protected ?string $bulkActionModel = User::class;

    protected ?string $bulkActionPermissionPrefix = 'user';

    protected string $bulkActionItemLabel = 'user';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return User::query()->with('roles');
    }

    public function relationSearch(): array
    {
        return [
            'roles' => ['name'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('first_name')
            ->add('last_name')
            ->add('photo_preview', function (User $model) {
                return '<img src="'.e($model->photo).'" alt="'.e($model->name).'" class="powergrid-user-photo">';
            })
            ->add('photo', function ($row) {
                return '<img src="'.$row->photo.'" width="40" class="rounded-circle">';
            })
            ->add('username')
            ->add('phone')
            ->add('instagram')
            ->add('facebook')
            ->add('linkedin')
            ->add('identity_number')
            ->add('religion')
            ->add('blood_type')
            ->add('roles', function (User $model) {
                return $model->roles->pluck('name')->join(', ');
            })
            ->add('role_names', function (User $model) {
                return $model->roles->pluck('name')->join(',');
            })
            ->add('gender')
            ->add('citizenship')
            ->add('height')
            ->add('weight')
            ->add('place_of_birth')
            ->add('date_of_birth_formatted', fn (User $model) => Carbon::parse($model->date_of_birth)->format('d/m/Y'))
            ->add('email')
            ->add('is_active')
            ->add('fst_setup')
            ->add('tfa_setup')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('No', 'id')
                ->index(),
            Column::make('Nama Depan', 'first_name')
                ->sortable()
                ->searchable(),

            Column::make('Nama Belakang', 'last_name')
                ->sortable()
                ->searchable(),

            Column::make('Foto', 'photo_preview')
                ->bodyAttribute('text-center'),

            Column::make('Username', 'username')
                ->sortable()
                ->searchable(),

            Column::make('No. HP', 'phone')
                ->sortable()
                ->searchable(),

            Column::make('Peran / Role', 'roles'),

            Column::make('Jenis Kelamin', 'gender')
                ->sortable()
                ->searchable(),

            Column::make('Tempat Lahir', 'place_of_birth')
                ->sortable()
                ->searchable(),

            Column::make('Tanggal Lahir', 'date_of_birth_formatted', 'date_of_birth')
                ->sortable(),

            Column::make('Email', 'email')
                ->sortable()
                ->searchable(),

            Column::make('Status', 'is_active')
                ->toggleable(
                    ActivePermission::check('user.update'),
                    'Aktif',
                    'Nonaktif'
                )
                ->sortable(),

            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('first_name')->placeholder('Cari nama depan...'),
            Filter::inputText('last_name')->placeholder('Cari nama belakang...'),
            Filter::inputText('username')->placeholder('Cari username...'),
            Filter::inputText('email')->placeholder('Cari alamat email...'),
            Filter::inputText('phone')->placeholder('Cari nomor HP...'),
            Filter::select('roles', 'role_id')
                ->dataSource(
                    Role::query()
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->map(fn (Role $role) => [
                            'id' => $role->id,
                            'name' => $role->name,
                        ])
                )
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->whereHas('roles', fn ($q) => $q->where('roles.id', $value))),
            Filter::select('gender', 'gender')
                ->dataSource([
                    ['id' => 'Male', 'name' => 'Laki-laki (Male)'],
                    ['id' => 'Female', 'name' => 'Perempuan (Female)'],
                    ['id' => 'Laki-laki', 'name' => 'Laki-laki'],
                    ['id' => 'Perempuan', 'name' => 'Perempuan'],
                ])
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('gender', $value)),
            Filter::boolean('is_active', 'Aktif', 'Nonaktif'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('user.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status user!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        if ($id == auth()->id()) {
            session()->flash('error', 'Anda tidak dapat menonaktifkan akun yang sedang login!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $user = User::find($id);

        if ($user) {
            $user->update([
                'is_active' => (bool) $value,
            ]);
        }

        $this->dispatch('pg:eventRefresh-'.$this->tableName);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.access.users.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {

        $user = User::find($id);

        if ($user) {
            $this->js('
                Swal.fire({
                    title: "Hapus user?",
                    text: "'.$user->first_name.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('user.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus user!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id user tidak ditemukan!');

            return;
        }

        $user = User::find($id);

        if (! $user) {
            session()->flash('error', 'User tidak ditemukan!');

            return;
        }

        if ($user->id === auth()->id()) {
            session()->flash('error', 'Anda tidak bisa menghapus diri sendiri!');

            return;
        }

        $userName = $user->first_name;

        $user->delete();

        $this->dispatch('pg:eventRefresh-userTable');
        $this->js('
            Swal.fire({
                title: "User dihapus",
                text: "'.$userName.' berhasil dihapus!",
                icon: "success",
                timer: 2000,
                showConfirmButton: false
            });
        ');
    }

    public function actions(User $row): array
    {
        $actions = [];

        if (ActivePermission::check('user.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i> Edit')
                ->id()
                ->class('btn btn-outline-primary rounded-pill px-2.5 py-1 text-primary fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('user.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> Hapus')
                ->class('btn btn-outline-danger rounded-pill px-2.5 py-1 text-danger fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }

    protected function beforeBulkDelete(Model $model): ?string
    {
        if ($model instanceof User && $model->id === auth()->id()) {
            return 'Akun yang sedang login tidak bisa dihapus lewat bulk action.';
        }

        return null;
    }

    /*
    public function actionRules($row): array
    {
       return [
            // Hide button edit for ID 1
            Rule::button('edit')
                ->when(fn($row) => $row->id === 1)
                ->hide(),
        ];
    }
    */
}
