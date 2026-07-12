<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\EdomQuestion;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class EdomQuestionTable extends BasePowerGridTable
{
    public string $tableName = 'edomQuestionTable';

    protected ?string $bulkActionModel = EdomQuestion::class;

    protected ?string $bulkActionPermissionPrefix = 'edom-question';

    protected string $bulkActionItemLabel = 'pertanyaan EDOM';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return EdomQuestion::query()->orderBy('category')->orderBy('sort_order');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('category')
            ->add('question_text')
            ->add('answer_type')
            ->add('sort_order')
            ->add('active_badge', fn (EdomQuestion $model) => $model->is_active ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>');
    }

    public function columns(): array
    {
        return [
            Column::make('Kategori', 'category')->sortable()->searchable(),
            Column::make('Pertanyaan', 'question_text')->searchable(),
            Column::make('Tipe', 'answer_type')->sortable(),
            Column::make('Urutan', 'sort_order')->sortable(),
            Column::make('Status', 'active_badge', 'is_active')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('category', 'category')
                ->placeholder('Cari kategori...')
                ->operators(['contains']),
            Filter::inputText('question_text', 'question_text')
                ->placeholder('Cari teks pertanyaan...')
                ->operators(['contains']),
            Filter::select('answer_type', 'answer_type')
                ->dataSource(collect([['id' => 'scale', 'name' => 'Skala'], ['id' => 'text', 'name' => 'Teks']]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::boolean('is_active', 'is_active')->label('Aktif', 'Nonaktif'),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.organization.edom-questions.edit', ['id' => $rowId]);
    }

    #[On('toggleActive')]
    public function toggleActive($id): void
    {
        if (! ActivePermission::check('edom-question.update')) {
            session()->flash('error', 'Anda tidak memiliki izin mengubah status pertanyaan.');
            return;
        }

        $question = EdomQuestion::find($id);
        if ($question) {
            $question->update(['is_active' => ! $question->is_active, 'updated_by' => auth()->id()]);
            session()->flash('success', 'Status pertanyaan berhasil diperbarui.');
            $this->dispatch('pg:eventRefresh-edomQuestionTable');
        }
    }

    #[On('delete')]
    public function delete($id): void
    {
        $question = EdomQuestion::find($id);

        if (! $question) {
            return;
        }

        if ($question->answers()->exists()) {
            $this->js('Swal.fire("Gagal", "Pertanyaan ini tidak dapat dihapus karena sudah memiliki jawaban mahasiswa.", "error");');
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus Pertanyaan?",
                text: "Pertanyaan ini akan dihapus dari sistem.",
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
        if (! ActivePermission::check('edom-question.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus pertanyaan EDOM.');
            return;
        }

        $question = EdomQuestion::find($id);

        if (! $question) {
            session()->flash('error', 'Pertanyaan tidak ditemukan.');
            return;
        }

        if ($question->answers()->exists()) {
            session()->flash('error', 'Pertanyaan yang memiliki jawaban tidak dapat dihapus.');
            return;
        }

        $question->delete();
        session()->flash('success', 'Pertanyaan berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-edomQuestionTable');
    }

    public function actions(EdomQuestion $row): array
    {
        $actions = [];

        if (ActivePermission::check('edom-question.update')) {
            $actions[] = Button::add('edit')->slot('<i class="fa fa-edit"></i>')->class('btn btn-primary')->dispatch('edit', ['rowId' => $row->id]);
            $actions[] = Button::add('toggle')
                ->slot($row->is_active ? '<i class="fa fa-toggle-on"></i>' : '<i class="fa fa-toggle-off"></i>')
                ->class($row->is_active ? 'btn btn-success' : 'btn btn-secondary')
                ->dispatch('toggleActive', ['id' => $row->id]);
        }

        if (ActivePermission::check('edom-question.delete') && ! $row->answers()->exists()) {
            $actions[] = Button::add('delete')->slot('<i class="fa fa-trash"></i>')->class('btn btn-danger')->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
