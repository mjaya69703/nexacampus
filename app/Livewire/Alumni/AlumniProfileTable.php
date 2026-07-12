<?php

namespace App\Livewire\Alumni;

use App\Enums\EmploymentStatus;
use App\Livewire\BasePowerGridTable;
use App\Models\Academic\StudyProgram;
use App\Models\Alumni\AlumniProfile;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class AlumniProfileTable extends BasePowerGridTable
{
    public string $tableName = 'alumniProfileTable';

    protected ?string $bulkActionModel = AlumniProfile::class;

    protected ?string $bulkActionPermissionPrefix = 'alumni-profile';

    protected string $bulkActionItemLabel = 'profil alumni';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return AlumniProfile::query()
            ->with(['studyProgram', 'faculty'])
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'studyProgram' => ['name', 'code'],
            'faculty' => ['name'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('nim')
            ->add('full_name')
            ->add('study_program_name', fn (AlumniProfile $m) => $m->studyProgram?->name ?? '-')
            ->add('graduation_year')
            ->add('gpa')
            ->add('employment_status_label', fn (AlumniProfile $m) => EmploymentStatus::tryFrom($m->employment_status)?->label() ?? $m->employment_status)
            ->add('current_city')
            ->add('is_active')
            ->add('created_at_label', fn (AlumniProfile $m) => $m->created_at?->format('d M Y'));
    }

    public function columns(): array
    {
        return [
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Nama', 'full_name')->sortable()->searchable(),
            Column::make('Program Studi', 'study_program_name')->sortable()->searchable(),
            Column::make('Tahun Lulus', 'graduation_year')->sortable(),
            Column::make('IPK', 'gpa')->sortable(),
            Column::make('Status Kerja', 'employment_status_label')->sortable()->searchable(),
            Column::make('Kota', 'current_city')->sortable()->searchable(),
            Column::make('Aktif', 'is_active')
                ->toggleable(
                    ActivePermission::check('alumni-profile.update'),
                    'Aktif',
                    'Nonaktif'
                )
                ->sortable(),
            Column::make('Dibuat', 'created_at_label')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('nim')->placeholder('Cari NIM alumni...'),
            Filter::inputText('full_name')->placeholder('Cari nama lengkap alumni...'),
            Filter::select('study_program_name', 'study_program_id')
                ->dataSource(
                    StudyProgram::query()
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->map(fn (StudyProgram $sp) => [
                            'id' => $sp->id,
                            'name' => $sp->name,
                        ])
                )
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('study_program_id', $value)),
            Filter::select('graduation_year', 'graduation_year')
                ->dataSource(
                    AlumniProfile::query()
                        ->select('graduation_year')
                        ->whereNotNull('graduation_year')
                        ->distinct()
                        ->orderByDesc('graduation_year')
                        ->pluck('graduation_year')
                        ->filter()
                        ->map(fn ($year) => [
                            'id' => (string) $year,
                            'name' => (string) $year,
                        ])
                )
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('graduation_year', $value)),
            Filter::select('employment_status_label', 'employment_status')
                ->dataSource(collect(EmploymentStatus::options())
                    ->map(fn ($label, $value) => ['id' => $value, 'name' => $label])
                    ->values()
                    ->toArray()
                )
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('employment_status', $value)),
            Filter::inputText('current_city')->placeholder('Cari kota domisili...'),
            Filter::boolean('is_active', 'Aktif', 'Nonaktif'),
            Filter::datepicker('created_at_label', 'created_at'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('alumni-profile.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status alumni!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $profile = AlumniProfile::find($id);

        if ($profile) {
            $profile->update([
                'is_active' => (bool) $value,
                'updated_by' => auth()->id(),
            ]);
        }

        $this->dispatch('pg:eventRefresh-'.$this->tableName);
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.alumni.profiles.show', ['id' => $rowId]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.alumni.profiles.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $profile = AlumniProfile::find($id);

        if ($profile) {
            $this->js('
                Swal.fire({
                    title: "Hapus profil alumni?",
                    text: "'.$profile->full_name.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('alumni-profile.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus profil alumni!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id profil alumni tidak ditemukan!');

            return;
        }

        $profile = AlumniProfile::find($id);

        if ($profile) {
            $name = $profile->full_name;
            $profile->delete();

            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->js('
                Swal.fire({
                    title: "Profil alumni dihapus",
                    text: "'.$name.' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(AlumniProfile $row): array
    {
        $actions = [];

        if (ActivePermission::check('alumni-profile.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i> Detail')
                ->class('btn btn-outline-info rounded-pill px-2.5 py-1 text-info fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('alumni-profile.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i> Edit')
                ->class('btn btn-outline-primary rounded-pill px-2.5 py-1 text-primary fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('alumni-profile.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> Hapus')
                ->class('btn btn-outline-danger rounded-pill px-2.5 py-1 text-danger fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
