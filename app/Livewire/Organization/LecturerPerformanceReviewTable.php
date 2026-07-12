<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\LecturerPerformanceReview;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class LecturerPerformanceReviewTable extends BasePowerGridTable
{
    public string $tableName = 'lecturerPerformanceReviewTable';

    protected bool $bulkActionEnabled = false;

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return LecturerPerformanceReview::query()->with(['owner', 'edomPeriod', 'workloadPeriod', 'lecturerProfile.studyProgram'])->latest('calculated_at');
    }

    public function relationSearch(): array
    {
        return ['owner' => ['first_name', 'last_name', 'email', 'username'], 'edomPeriod' => ['name', 'code']];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('lecturer_name', fn (LecturerPerformanceReview $model) => $model->owner?->name ?? '-')
            ->add('period_name', fn (LecturerPerformanceReview $model) => $model->edomPeriod?->name ?? '-')
            ->add('program_name', fn (LecturerPerformanceReview $model) => $model->lecturerProfile?->studyProgram?->name ?? '-')
            ->add('edom_score')
            ->add('edom_response_count')
            ->add('final_score')
            ->add('status_badge', fn (LecturerPerformanceReview $model) => '<span class="badge '.($model->status === 'published' ? 'bg-success' : 'bg-info').'">'.str($model->status)->title().'</span>');
    }

    public function columns(): array
    {
        return [
            Column::make('Dosen', 'lecturer_name')->searchable(),
            Column::make('Periode', 'period_name')->searchable(),
            Column::make('Program Studi', 'program_name')->searchable(),
            Column::make('EDOM', 'edom_score')->sortable(),
            Column::make('Respon', 'edom_response_count')->sortable(),
            Column::make('Nilai Akhir', 'final_score')->sortable(),
            Column::make('Status', 'status_badge', 'status')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('lecturer_name', 'lecturer_name')
                ->placeholder('Cari nama dosen/username...')
                ->operators(['contains'])
                ->builder(function (Builder $query, array $values) {
                    $value = $values['value'] ?? null;
                    if (! empty($value)) {
                        $query->whereHas('owner', function (Builder $sub) use ($value) {
                            $sub->where(function (Builder $q) use ($value) {
                                $q->where('first_name', 'like', '%' . $value . '%')
                                    ->orWhere('last_name', 'like', '%' . $value . '%')
                                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ['%' . $value . '%'])
                                    ->orWhere('username', 'like', '%' . $value . '%');
                            });
                        });
                    }
                }),
            Filter::inputText('period_name', 'period_name')
                ->placeholder('Cari periode EDOM...')
                ->operators(['contains'])
                ->builder(function (Builder $query, array $values) {
                    $value = $values['value'] ?? null;
                    if (! empty($value)) {
                        $query->whereHas('edomPeriod', function (Builder $sub) use ($value) {
                            $sub->where('name', 'like', '%' . $value . '%')
                                ->orWhere('code', 'like', '%' . $value . '%');
                        });
                    }
                }),
            Filter::inputText('program_name', 'program_name')
                ->placeholder('Cari program studi...')
                ->operators(['contains'])
                ->builder(function (Builder $query, array $values) {
                    $value = $values['value'] ?? null;
                    if (! empty($value)) {
                        $query->whereHas('lecturerProfile.studyProgram', function (Builder $sub) use ($value) {
                            $sub->where('name', 'like', '%' . $value . '%');
                        });
                    }
                }),
            Filter::select('status', 'status')
                ->dataSource(collect([['id' => 'draft', 'name' => 'Draft'], ['id' => 'calculated', 'name' => 'Calculated'], ['id' => 'published', 'name' => 'Published']]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.organization.lecturer-performance-reviews.show', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $review = LecturerPerformanceReview::find($id);

        if (! $review) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus Hasil Evaluasi?",
                text: "Hasil evaluasi performa milik \''.($review->owner?->name ?? 'Dosen').'\' akan dihapus permanen.",
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
        if (! ActivePermission::check('lecturer-performance-review.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus hasil evaluasi.');
            return;
        }

        $review = LecturerPerformanceReview::find($id);

        if (! $review) {
            session()->flash('error', 'Data evaluasi tidak ditemukan.');
            return;
        }

        $review->delete();
        session()->flash('success', 'Data evaluasi berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-lecturerPerformanceReviewTable');
    }

    public function actions(LecturerPerformanceReview $row): array
    {
        $actions = [];

        if (ActivePermission::check('lecturer-performance-review.view')) {
            $actions[] = Button::add('show')->slot('<i class="fa fa-eye"></i>')->class('btn btn-primary')->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('lecturer-performance-review.delete') && $row->status !== 'published') {
            $actions[] = Button::add('delete')->slot('<i class="fa fa-trash"></i>')->class('btn btn-danger')->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
