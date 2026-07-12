<?php

namespace App\Livewire\Financial;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\StudentCreditBalance;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
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
            ->add('updated_at_label', fn (StudentCreditBalance $model) => $model->updated_at?->format('d M Y, H:i') ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::make('Nama Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Program Studi', 'study_program', 'study_program_id')->sortable()->searchable(),
            Column::make('Saldo Deposit / Kredit', 'balance_label')->sortable(),
            Column::make('Terakhir Diperbarui', 'updated_at_label')->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('student_name', 'studentProfile.user.first_name')
                ->operators(['contains']),
            Filter::inputText('nim', 'studentProfile.nim')
                ->operators(['contains']),
            Filter::select('study_program', 'study_program_id')
                ->dataSource(
                    StudyProgram::query()
                        ->orderBy('name')
                        ->get(['id', 'name', 'code'])
                        ->map(fn ($item) => ['id' => $item->id, 'name' => $item->name . ' (' . $item->code . ')'])
                )
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    private function money(float|string|null $amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }
}
