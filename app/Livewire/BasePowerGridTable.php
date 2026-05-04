<?php

namespace App\Livewire;

use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;

abstract class BasePowerGridTable extends PowerGridComponent
{
    protected ?string $bulkActionModel = null;

    protected ?string $bulkActionPermissionPrefix = null;

    protected string $bulkActionItemLabel = 'data';

    protected bool $bulkActionEnabled = true;

    protected ?string $customBulkActionLabel = null;

    protected function powerGridSetUp(
        bool $showSearchInput = true,
        bool $showToggleColumns = false,
        bool $withoutLoading = false,
    ): array {
        if ($this->bulkActionEnabled) {
            $this->showCheckBox();
        }

        $header = PowerGrid::header();

        if ($showSearchInput) {
            $header->showSearchInput();
        }

        if ($showToggleColumns) {
            $header->showToggleColumns();
        }

        if ($withoutLoading) {
            $header->withoutLoading();
        }

        if ($this->supportsSoftDeletes()) {
            $header->showSoftDeletes();
        }

        if ($this->bulkActionEnabled) {
            $header->includeViewOnBottom('components.powergrid.bulk-actions');
        }

        return [
            $header,
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    public function selectedCount(): int
    {
        return count($this->checkboxValues ?? []);
    }

    public function canBulkDelete(): bool
    {
        return $this->bulkActionEnabled
            && filled($this->bulkActionModel)
            && $this->hasDeletePermission();
    }

    public function canBulkRestore(): bool
    {
        return $this->bulkActionEnabled
            && $this->supportsSoftDeletes()
            && $this->hasRestorePermission();
    }

    public function clearBulkSelection(): void
    {
        $this->checkboxValues = [];
        $this->dispatch('pgBulkActions::clear', $this->tableName);
    }

    public function canCustomBulkAction(): bool
    {
        return $this->bulkActionEnabled && filled($this->customBulkActionLabel);
    }

    public function customBulkActionLabel(): ?string
    {
        return $this->customBulkActionLabel;
    }

    public function runCustomBulkAction(): void {}

    public function confirmBulkDelete(): void
    {
        if (! $this->canBulkDelete()) {
            session()->flash('error', 'Anda tidak memiliki izin untuk bulk delete.');

            return;
        }

        if ($this->selectedCount() === 0) {
            session()->flash('error', 'Pilih minimal satu data terlebih dahulu.');

            return;
        }

        $itemLabel = $this->bulkActionItemLabel;
        $selectedCount = $this->selectedCount();

        $this->js(<<<JS
            Swal.fire({
                title: "Hapus data terpilih?",
                text: "{$selectedCount} {$itemLabel} akan dipindahkan ke tempat sampah atau dihapus.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya hapus",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    \$wire.bulkDeleteSelected()
                }
            });
        JS);
    }

    public function bulkDeleteSelected(): void
    {
        if (! $this->canBulkDelete()) {
            session()->flash('error', 'Anda tidak memiliki izin untuk bulk delete.');

            return;
        }

        $ids = collect($this->checkboxValues)->filter()->values();

        if ($ids->isEmpty()) {
            session()->flash('error', 'Pilih minimal satu data terlebih dahulu.');

            return;
        }

        $successCount = 0;
        $errors = [];

        foreach ($this->bulkActionQuery()->whereKey($ids)->get() as $model) {
            $error = $this->beforeBulkDelete($model);

            if ($error) {
                $errors[] = $error;

                continue;
            }

            $model->delete();
            $this->afterBulkDelete($model);
            $successCount++;
        }

        $this->afterBulkActionCompleted($successCount, $errors, 'dihapus');
    }

    public function bulkRestoreSelected(): void
    {
        if (! $this->canBulkRestore()) {
            session()->flash('error', 'Anda tidak memiliki izin untuk restore data.');

            return;
        }

        $ids = collect($this->checkboxValues)->filter()->values();

        if ($ids->isEmpty()) {
            session()->flash('error', 'Pilih minimal satu data terlebih dahulu.');

            return;
        }

        $successCount = 0;

        foreach ($this->bulkActionQuery()->onlyTrashed()->whereKey($ids)->get() as $model) {
            $model->restore();
            $this->afterBulkRestore($model);
            $successCount++;
        }

        $this->afterBulkActionCompleted($successCount, [], 'dipulihkan');
    }

    protected function bulkActionQuery()
    {
        $modelClass = $this->bulkActionModel;
        $query = $modelClass::query();

        if ($this->supportsSoftDeletes()) {
            $query->withTrashed();
        }

        return $query;
    }

    protected function supportsSoftDeletes(): bool
    {
        if (! filled($this->bulkActionModel)) {
            return false;
        }

        return in_array(SoftDeletes::class, class_uses_recursive($this->bulkActionModel), true);
    }

    protected function hasDeletePermission(): bool
    {
        return filled($this->bulkActionPermissionPrefix)
            ? ActivePermission::check($this->bulkActionPermissionPrefix.'.delete')
            : false;
    }

    protected function hasRestorePermission(): bool
    {
        if (! filled($this->bulkActionPermissionPrefix)) {
            return false;
        }

        return ActivePermission::check($this->bulkActionPermissionPrefix.'.update')
            || ActivePermission::check($this->bulkActionPermissionPrefix.'.delete');
    }

    protected function beforeBulkDelete(Model $model): ?string
    {
        return null;
    }

    protected function afterBulkDelete(Model $model): void {}

    protected function afterBulkRestore(Model $model): void {}

    protected function afterBulkActionCompleted(int $successCount, array $errors, string $verb): void
    {
        $this->dispatch('pg:eventRefresh-'.$this->tableName);
        $this->clearBulkSelection();

        if ($successCount > 0) {
            session()->flash('success', $successCount.' '.$this->bulkActionItemLabel.' berhasil '.$verb.'.');
        }

        if (! empty($errors)) {
            session()->flash('error', implode(' ', $errors));
        }
    }
}
