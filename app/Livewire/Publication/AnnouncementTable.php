<?php

namespace App\Livewire\Publication;

use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementTargetType;
use App\Livewire\BasePowerGridTable;
use App\Models\Publication\Announcement;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class AnnouncementTable extends BasePowerGridTable
{
    public string $tableName = 'announcementTable';

    protected ?string $bulkActionModel = Announcement::class;

    protected ?string $bulkActionPermissionPrefix = 'announcement';

    protected string $bulkActionItemLabel = 'pengumuman';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        $query = Announcement::query()
            ->with(['creator'])
            ->withCount('reads')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');

        // Lecturer: only see their own announcements
        $user = auth()->user();
        if ($user && $user->hasRole('lecturer') && ! $user->hasRole('superuser')) {
            $query->where('created_by', $user->id);
        }

        return $query;
    }

    public function relationSearch(): array
    {
        return [
            'creator' => ['name', 'email'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('title')
            ->add('target_type_label', fn (Announcement $model) => $model->target_type->label())
            ->add('priority_badge', function (Announcement $model) {
                $priority = $model->priority;

                return '<span class="badge '.$priority->badgeClass().'">
                    <i class="'.$priority->icon().' me-1"></i>'.$priority->label().'
                </span>';
            })
            ->add('is_pinned', fn (Announcement $model) => $model->is_pinned)
            ->add('is_published', fn (Announcement $model) => $model->is_published)
            ->add('published_at_formatted', fn (Announcement $model) => $model->published_at
                ? $model->published_at->format('d M Y H:i')
                : '-')
            ->add('creator_name', fn (Announcement $model) => $model->creator?->name ?? '-')
            ->add('reads_count')
            ->add('created_at_formatted', fn (Announcement $model) => $model->created_at->format('d M Y H:i'))
            ->add('has_attachment', fn (Announcement $model) => $model->attachment_path ? true : false);
    }

    public function columns(): array
    {
        return [
            Column::make('Judul', 'title')
                ->sortable()
                ->searchable(),

            Column::make('Target', 'target_type_label'),

            Column::make('Prioritas', 'priority_badge'),

            Column::make('Pin', 'is_pinned')
                ->toggleable(
                    ActivePermission::check('announcement.update'),
                    'Ya',
                    'Tidak'
                )
                ->sortable(),

            Column::make('Status', 'is_published')
                ->toggleable(
                    ActivePermission::check('announcement.update'),
                    'Published',
                    'Draft'
                )
                ->sortable(),

            Column::make('Dipublikasi', 'published_at_formatted')
                ->sortable(),

            Column::make('Dibuat oleh', 'creator_name')
                ->searchable(),

            Column::make('Dibaca', 'reads_count')
                ->sortable(),

            Column::make('Lampiran', 'has_attachment')
                ->toggleable(false, 'Ya', 'Tidak')
                ->sortable(),

            Column::make('Dibuat', 'created_at_formatted')
                ->sortable(),

            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('title')
                ->placeholder('Cari judul pengumuman...'),

            Filter::inputText('creator_name')
                ->placeholder('Cari pembuat...'),

            Filter::select('priority', 'priority')
                ->dataSource(
                    collect(AnnouncementPriority::cases())
                        ->map(fn ($case) => [
                            'id' => $case->value,
                            'name' => $case->label(),
                        ])
                )
                ->optionValue('id')
                ->optionLabel('name'),

            Filter::boolean('is_published', 'is_published')
                ->label('Published', 'Draft'),

            Filter::boolean('is_pinned', 'is_pinned')
                ->label('Ya', 'Tidak'),

            Filter::datepicker('created_at'),

            Filter::datepicker('published_at'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if (! in_array($field, ['is_pinned', 'is_published'])) {
            return;
        }

        if (! ActivePermission::check('announcement.update')) {
            $fieldLabel = $field === 'is_pinned' ? 'pin' : 'status publikasi';
            session()->flash('error', "Anda tidak memiliki izin untuk mengubah {$fieldLabel} pengumuman!");
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $announcement = Announcement::find($id);

        if (! $announcement) {
            session()->flash('error', 'Pengumuman tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-'.$this->tableName);

            return;
        }

        $updateData = [
            'updated_by' => auth()->id(),
        ];

        if ($field === 'is_pinned') {
            $updateData['is_pinned'] = (bool) $value;
        } elseif ($field === 'is_published') {
            $updateData['is_published'] = (bool) $value;
            if ((bool) $value && ! $announcement->published_at) {
                $updateData['published_at'] = now();
            }
        }

        $announcement->update($updateData);
        $this->dispatch('pg:eventRefresh-'.$this->tableName);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.publication.announcements.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $announcement = Announcement::find($id);

        if ($announcement) {
            $this->js('
                Swal.fire({
                    title: "Hapus pengumuman?",
                    text: "'.addslashes($announcement->title).' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('announcement.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus pengumuman!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'ID pengumuman tidak ditemukan!');

            return;
        }

        $announcement = Announcement::find($id);

        if ($announcement) {
            // Delete attachment if exists
            if ($announcement->attachment_path && \Storage::disk('public')->exists($announcement->attachment_path)) {
                \Storage::disk('public')->delete($announcement->attachment_path);
            }

            $announcement->update(['deleted_by' => auth()->id()]);
            $announcement->delete();

            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->js('
                Swal.fire({
                    title: "Dihapus",
                    text: "Pengumuman berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(Announcement $row): array
    {
        $actions = [];

        if (ActivePermission::check('announcement.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-pencil"></i> <span>Edit</span>')
                ->class('btn btn-sm btn-primary d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('announcement.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> <span>Hapus</span>')
                ->class('btn btn-sm btn-danger d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
