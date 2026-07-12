<?php

namespace App\Livewire\Admission;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionPeriod;
use App\Support\ActivePermission;
use App\Support\Admission\AdmissionConversionService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\Exports\Export;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class ApplicationTable extends BasePowerGridTable
{
    use WithExport;

    public string $tableName = 'admissionApplicationTable';

    protected ?string $bulkActionModel = AdmissionApplication::class;

    protected ?string $bulkActionPermissionPrefix = 'admission-application';

    protected string $bulkActionItemLabel = 'aplikasi admission';

    protected ?string $customBulkActionLabel = 'Convert Selected to Students';

    public function setUp(): array
    {
        $setup = $this->powerGridSetUp(showToggleColumns: true);
        $setup[0]->includeViewOnTop('components.powergrid.admission-application-actions');

        return [
            ...$setup,
            PowerGrid::exportable('admission-applications')
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV)
                ->stripTags(true),
        ];
    }

    public function datasource(): Builder
    {
        return AdmissionApplication::query()
            ->with(['period', 'faculty', 'studyProgram'])
            ->withCount(['documents', 'scores', 'examParticipants'])
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'period' => ['name', 'code'],
            'faculty' => ['name', 'code'],
            'studyProgram' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('application_number')
            ->add('full_name')
            ->add('email')
            ->add('phone')
            ->add('period_name', fn (AdmissionApplication $model) => $model->period?->name)
            ->add('faculty_name', fn (AdmissionApplication $model) => $model->faculty?->name)
            ->add('study_program_name', fn (AdmissionApplication $model) => $model->studyProgram?->name)
            ->add('class_type')
            ->add('status')
            ->add('documents_count')
            ->add('scores_count')
            ->add('exam_participants_count')
            ->add('final_score')
            ->add('converted_at', fn (AdmissionApplication $model) => $model->converted_at?->format('d M Y H:i'))
            ->add('submitted_at', fn (AdmissionApplication $model) => $model->submitted_at?->format('d M Y H:i'))
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('No. Pendaftaran', 'application_number')->sortable()->searchable(),
            Column::make('Nama Pendaftar', 'full_name')->sortable()->searchable(),
            Column::make('Email', 'email')->sortable()->searchable()->hidden(),
            Column::make('No. HP / WA', 'phone')->sortable()->searchable(),
            Column::make('Gelombang / Periode', 'period_name')->sortable()->searchable(),
            Column::make('Fakultas', 'faculty_name')->sortable()->searchable()->hidden(),
            Column::make('Program Studi', 'study_program_name')->sortable()->searchable(),
            Column::make('Kelas', 'class_type')->sortable(),
            Column::make('Status', 'status')->sortable(),
            Column::make('Skor Akhir', 'final_score')->sortable(),
            Column::make('Dokumen', 'documents_count')->sortable(),
            Column::make('Nilai Ujian', 'scores_count')->sortable()->hidden(),
            Column::make('Sesi Ujian', 'exam_participants_count')->sortable()->hidden(),
            Column::make('Dikonversi', 'converted_at')->sortable()->hidden(),
            Column::make('Tanggal Daftar', 'submitted_at')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('application_number')->placeholder('Cari no. pendaftaran...'),
            Filter::inputText('full_name')->placeholder('Cari nama pendaftar...'),
            Filter::inputText('phone')->placeholder('Cari no. HP/WhatsApp...'),
            Filter::inputText('email')->placeholder('Cari email pendaftar...'),
            Filter::select('status', 'status')
                ->dataSource(collect([
                    ['id' => 'submitted', 'name' => 'Submitted (Terkirim)'],
                    ['id' => 'under_review', 'name' => 'Under Review (Proses Seleksi)'],
                    ['id' => 'accepted', 'name' => 'Accepted (Diterima)'],
                    ['id' => 'rejected', 'name' => 'Rejected (Ditolak)'],
                    ['id' => 'waitlisted', 'name' => 'Waitlisted (Cadangan)'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('period_name', 'admission_period_id')
                ->dataSource(AdmissionPeriod::query()->orderByDesc('created_at')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('admission_period_id', $value)),
            Filter::select('faculty_name', 'faculty_id')
                ->dataSource(Faculty::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('faculty_id', $value)),
            Filter::select('study_program_name', 'study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('study_program_id', $value)),
            Filter::select('class_type', 'class_type')
                ->dataSource(collect([
                    ['id' => 'regular', 'name' => 'Reguler Pagi'],
                    ['id' => 'evening', 'name' => 'Kelas Malam'],
                    ['id' => 'weekend', 'name' => 'Kelas Akhir Pekan (Weekend)'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::datetimepicker('submitted_at', 'submitted_at'),
        ];
    }

    public function exportToXLS(bool $selected = false): StreamedResponse|bool
    {
        $payload = $this->exportPayload($selected);

        if ($payload === false) {
            return false;
        }

        return response()->streamDownload(function () use ($payload) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($payload['headers'], null, 'A1');

            foreach ($payload['rows'] as $index => $row) {
                $sheet->fromArray(array_values($row), null, 'A'.($index + 2));
            }

            foreach (range('A', $this->spreadsheetLastColumn(count($payload['headers']))) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            (new Xlsx($spreadsheet))->save('php://output');
        }, $this->exportFileName('xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportToCsv(bool $selected = false): StreamedResponse|bool
    {
        $payload = $this->exportPayload($selected);

        if ($payload === false) {
            return false;
        }

        return response()->streamDownload(function () use ($payload) {
            echo "\xEF\xBB\xBF";

            $handle = fopen('php://output', 'w');
            fputcsv($handle, $payload['headers']);

            foreach ($payload['rows'] as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, $this->exportFileName('csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportToPdf(bool $selected = false): Response|bool
    {
        $payload = $this->exportPayload($selected);

        if ($payload === false) {
            session()->flash('error', 'Pilih minimal satu application untuk export selected.');

            return false;
        }

        return response()->streamDownload(function () use ($payload) {
            $options = new Options;
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml(view('exports.admission-applications-pdf', [
                'headers' => $payload['headers'],
                'rows' => $payload['rows'],
                'generatedAt' => now(),
            ])->render());
            $dompdf->setPaper('a4', 'landscape');
            $dompdf->render();

            echo $dompdf->output();
        }, $this->exportFileName('pdf'), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function runCustomBulkAction(): void
    {
        if (! ActivePermission::check('admission-application.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk convert applicant.');

            return;
        }

        $ids = collect($this->checkboxValues)->filter()->values();

        if ($ids->isEmpty()) {
            session()->flash('error', 'Pilih minimal satu applicant terlebih dahulu.');

            return;
        }

        $converted = 0;
        $errors = [];
        $service = app(AdmissionConversionService::class);

        foreach (AdmissionApplication::query()->whereKey($ids)->get() as $application) {
            try {
                $service->convert($application, auth()->id());
                $converted++;
            } catch (Throwable $exception) {
                $errors[] = $application->application_number.': '.$exception->getMessage();
            }
        }

        $this->clearBulkSelection();
        $this->dispatch('pg:eventRefresh-admissionApplicationTable');

        if ($converted > 0) {
            session()->flash('success', $converted.' applicant berhasil dikonversi menjadi student.');
        }

        if ($errors) {
            session()->flash('error', implode(' ', $errors));
        }
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.admission.admission-applications.show', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $application = AdmissionApplication::find($id);

        if (! $application) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus aplikasi admission?",
                text: "'.$application->application_number.' - Data tidak bisa dikembalikan!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya hapus",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("deleteAdmissionApplication", {id: '.$id.'})
                }
            });
        ');
    }

    #[On('deleteAdmissionApplication')]
    public function deleteItem($id = null): void
    {
        if (! ActivePermission::check('admission-application.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus aplikasi admission.');

            return;
        }

        $application = AdmissionApplication::find($id);

        if (! $application) {
            session()->flash('error', 'Aplikasi admission tidak ditemukan.');

            return;
        }

        $application->update(['deleted_by' => auth()->id()]);
        $application->delete();

        session()->flash('success', 'Aplikasi admission berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-admissionApplicationTable');
    }

    public function actions(AdmissionApplication $row): array
    {
        $actions = [];

        if (ActivePermission::check('admission-application.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i> Detail')
                ->class('btn btn-outline-info rounded-pill px-2.5 py-1 text-info fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('admission-application.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> Hapus')
                ->class('btn btn-outline-danger rounded-pill px-2.5 py-1 text-danger fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }

    private function exportPayload(bool $selected): array|bool
    {
        if ($selected && count($this->checkboxValues) === 0) {
            return false;
        }

        $columns = $this->columnsWithCurrentHiddenState();
        $rows = $this->prepareToExport($selected);

        return (new Export)->prepare(
            collect($rows),
            $columns,
            (bool) data_get($this->setUp, 'exportable.stripTags', true),
        );
    }

    private function columnsWithCurrentHiddenState(): array
    {
        $currentHiddenStates = collect($this->columns)
            ->mapWithKeys(fn ($column) => [data_get($column, 'field') => data_get($column, 'hidden')]);

        return array_map(function ($column) use ($currentHiddenStates) {
            $column->hidden = (bool) $currentHiddenStates->get($column->field, $column->hidden);

            return $column;
        }, $this->columns());
    }

    private function exportFileName(string $extension): string
    {
        return 'admission-applications-'.now()->format('Ymd-His').'.'.$extension;
    }

    private function spreadsheetLastColumn(int $columnCount): string
    {
        $column = '';

        while ($columnCount > 0) {
            $columnCount--;
            $column = chr(65 + ($columnCount % 26)).$column;
            $columnCount = intdiv($columnCount, 26);
        }

        return $column ?: 'A';
    }
}
