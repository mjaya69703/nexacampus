<?php

namespace App\Livewire\Publication;

use App\Enums\FaqType;
use App\Livewire\BasePowerGridTable;
use App\Models\Publication\Faq;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class FaqTable extends BasePowerGridTable
{
    public string $tableName = 'faqTable';

    protected ?string $bulkActionModel = Faq::class;

    protected ?string $bulkActionPermissionPrefix = 'faq';

    protected string $bulkActionItemLabel = 'FAQ';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return Faq::query()
            ->with(['creator'])
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderByDesc('id');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('type_badge', function (Faq $model) {
                $type = $model->type;
                if ($type instanceof FaqType) {
                    return '<span class="badge '.$type->badgeClass().'"><i class="'.$type->icon().' me-1"></i>'.$type->label().'</span>';
                }

                return '<span class="badge bg-secondary">'.e($model->type).'</span>';
            })
            ->add('category')
            ->add('question', fn (Faq $model) => \Illuminate\Support\Str::limit(strip_tags($model->question), 70))
            ->add('sort_order')
            ->add('is_active', fn (Faq $model) => $model->is_active)
            ->add('created_at_formatted', fn (Faq $model) => $model->created_at?->format('d M Y H:i') ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')
                ->sortable(),

            Column::make('Tipe', 'type_badge'),

            Column::make('Kategori', 'category')
                ->sortable()
                ->searchable(),

            Column::make('Pertanyaan', 'question')
                ->sortable()
                ->searchable(),

            Column::make('Urutan', 'sort_order')
                ->sortable(),

            Column::make('Status', 'is_active')
                ->toggleable(
                    ActivePermission::check('faq.update'),
                    'Aktif',
                    'Nonaktif'
                )
                ->sortable(),

            Column::make('Dibuat', 'created_at_formatted')
                ->sortable(),

            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('question')
                ->placeholder('Cari pertanyaan...'),

            Filter::inputText('category')
                ->placeholder('Cari kategori...'),

            Filter::select('type', 'type')
                ->dataSource(
                    collect(FaqType::cases())
                        ->map(fn ($case) => [
                            'id' => $case->value,
                            'name' => $case->label(),
                        ])
                )
                ->optionValue('id')
                ->optionLabel('name'),

            Filter::boolean('is_active', 'is_active')
                ->label('Aktif', 'Nonaktif'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('faq.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status FAQ!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $faq = Faq::find($id);

        if (! $faq) {
            session()->flash('error', 'Data FAQ tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $faq->update([
            'is_active' => (bool) $value,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-'.$this->tableName);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.publication.faqs.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $faq = Faq::find($id);

        if ($faq) {
            $title = addslashes(\Illuminate\Support\Str::limit($faq->question, 40));
            $this->js('
                Swal.fire({
                    title: "Hapus FAQ?",
                    text: "'.$title.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('faq.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus FAQ!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'ID FAQ tidak ditemukan!');

            return;
        }

        $faq = Faq::find($id);

        if ($faq) {
            $faq->update(['deleted_by' => auth()->id()]);
            $faq->delete();

            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->js('
                Swal.fire({
                    title: "Dihapus",
                    text: "FAQ berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(Faq $row): array
    {
        $actions = [];

        if (ActivePermission::check('faq.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-pencil"></i> <span>Edit</span>')
                ->class('btn btn-sm btn-primary d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('faq.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> <span>Hapus</span>')
                ->class('btn btn-sm btn-danger d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
