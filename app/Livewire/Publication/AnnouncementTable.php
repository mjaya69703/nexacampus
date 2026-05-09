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
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        $query = Announcement::query()
            ->with('creator')
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
            ->add('is_pinned', fn (Announcement $model) => $model->is_pinned
                ? '<span class="badge bg-info"><i class="fas fa-thumbtack me-1"></i>Pinned</span>'
                : '<span class="text-muted">-</span>')
            ->add('is_published', fn (Announcement $model) => $model->is_published
                ? '<span class="badge bg-success">Published</span>'
                : '<span class="badge bg-secondary">Draft</span>')
            ->add('published_at_formatted', fn (Announcement $model) => $model->published_at
                ? $model->published_at->format('d M Y H:i')
                : '-')
            ->add('creator_name', fn (Announcement $model) => $model->creator?->name ?? '-')
            ->add('reads_count', fn (Announcement $model) => $model->reads()->count())
            ->add('created_at_formatted', fn (Announcement $model) => $model->created_at->format('d M Y H:i'));
    }

    public function columns(): array
    {
        return [
            Column::make('Judul', 'title')->sortable()->searchable(),
            Column::make('Target', 'target_type_label'),
            Column::make('Prioritas', 'priority_badge'),
            Column::make('Pin', 'is_pinned'),
            Column::make('Status', 'is_published'),
            Column::make('Dipublikasi', 'published_at_formatted'),
            Column::make('Dibuat oleh', 'creator_name')->searchable(),
            Column::make('Dibaca', 'reads_count'),
            Column::make('Dibuat', 'created_at_formatted')->sortable(),
            Column::action('Action'),
        ];
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

            $this->dispatch('pg:eventRefresh-announcementTable');
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
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('announcement.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
