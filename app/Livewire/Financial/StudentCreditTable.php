<?php

namespace App\Livewire\Financial;

use App\Livewire\BasePowerGridTable;
use App\Models\Financial\StudentCreditBalance;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class StudentCreditTable extends BasePowerGridTable
{
    public string $tableName = 'studentCreditTable';

    protected bool $bulkActionEnabled = false;

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return StudentCreditBalance::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram'])
            ->where('balance', '!=', 0)
            ->orderByDesc('balance');
    }

    public function relationSearch(): array
    {
        return [
            'studentProfile' => ['nim'],
            'studentProfile.user' => ['first_name', 'last_name', 'email'],
            'studentProfile.studyProgram' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('student_name', fn (StudentCreditBalance $model) => $model->studentProfile?->user?->name ?? '-')
            ->add('nim', fn (StudentCreditBalance $model) => $model->studentProfile?->nim ?? '-')
            ->add('study_program', fn (StudentCreditBalance $model) => $model->studentProfile?->studyProgram?->name ?? '-')
            ->add('balance_label', fn (StudentCreditBalance $model) => $this->money($model->balance))
            ->add('updated_at_label', fn (StudentCreditBalance $model) => $model->updated_at?->format('d M Y H:i') ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::make('Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Program Studi', 'study_program')->sortable()->searchable(),
            Column::make('Credit Balance', 'balance_label')->sortable(),
            Column::make('Updated At', 'updated_at_label')->sortable(),
        ];
    }

    private function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
