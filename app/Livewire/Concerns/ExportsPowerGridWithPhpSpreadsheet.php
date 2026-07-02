<?php

namespace App\Livewire\Concerns;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PowerComponents\LivewirePowerGrid\Components\Exports\Export;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsPowerGridWithPhpSpreadsheet
{
    use WithExport;

    public function exportToXLS(bool $selected = false): StreamedResponse|bool
    {
        $payload = $this->powerGridExportPayload($selected);

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

            foreach (range('A', $this->powerGridSpreadsheetLastColumn(count($payload['headers']))) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            (new Xlsx($spreadsheet))->save('php://output');
        }, $this->powerGridExportFileName('xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportToCsv(bool $selected = false): StreamedResponse|bool
    {
        $payload = $this->powerGridExportPayload($selected);

        if ($payload === false) {
            return false;
        }

        return response()->streamDownload(function () use ($payload) {
            echo "\xEF\xBB\xBF";

            $handle = fopen('php://output', 'w');
            fputcsv($handle, $payload['headers'], ',', '"', '\\');

            foreach ($payload['rows'] as $row) {
                fputcsv($handle, array_values($row), ',', '"', '\\');
            }

            fclose($handle);
        }, $this->powerGridExportFileName('csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function powerGridExportPayload(bool $selected): array|bool
    {
        if ($selected && count($this->checkboxValues) === 0) {
            return false;
        }

        return (new Export)->prepare(
            collect($this->prepareToExport($selected)),
            $this->powerGridColumnsWithCurrentHiddenState(),
            (bool) data_get($this->setUp, 'exportable.stripTags', true),
        );
    }

    private function powerGridColumnsWithCurrentHiddenState(): array
    {
        $currentHiddenStates = collect($this->columns)
            ->mapWithKeys(fn ($column) => [data_get($column, 'field') => data_get($column, 'hidden')]);

        return array_map(function ($column) use ($currentHiddenStates) {
            $column->hidden = (bool) $currentHiddenStates->get($column->field, $column->hidden);

            return $column;
        }, $this->columns());
    }

    private function powerGridExportFileName(string $extension): string
    {
        return str($this->tableName ?: class_basename(static::class))
            ->kebab()
            ->append('-'.now()->format('Ymd-His').'.'.$extension)
            ->toString();
    }

    private function powerGridSpreadsheetLastColumn(int $columnCount): string
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
