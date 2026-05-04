<?php

namespace App\Livewire\Access;

use App\Livewire\BasePowerGridTable;
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

// use App\Livewire\Examples\FiltersInlineTable\FiltersInlineTable;

final class UserTable extends BasePowerGridTable
{
    public string $tableName = 'userTable';

    protected ?string $bulkActionModel = User::class;

    protected ?string $bulkActionPermissionPrefix = 'user';

    protected string $bulkActionItemLabel = 'user';

    // public function boot(): void
    // {
    //     config(['livewire-powergrid.filter' => 'outside']);
    // }

    public function setUp(): array
    {
        return $this->powerGridSetUp();
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
            Column::make('First name', 'first_name')
                ->sortable()
                ->searchable(),

            Column::make('Last name', 'last_name')
                ->sortable()
                ->searchable(),

            Column::make('Photo', 'photo_preview')
                ->bodyAttribute('text-center'),

            Column::make('Username', 'username')
                ->sortable()
                ->searchable(),

            Column::make('Phone', 'phone')
                ->sortable()
                ->searchable(),

            Column::make('Role', 'roles'),

            Column::make('Gender', 'gender')
                ->sortable()
                ->searchable(),

            Column::make('Place of birth', 'place_of_birth')
                ->sortable()
                ->searchable(),

            Column::make('Date of birth', 'date_of_birth_formatted', 'date_of_birth')
                ->sortable(),

            Column::make('Email', 'email')
                ->sortable()
                ->searchable(),

            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
        ];
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
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('user.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
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
