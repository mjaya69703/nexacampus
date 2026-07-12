<?php

namespace App\Livewire\StudentService;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\StudyProgram;
use App\Models\Organization\WorkUnit;
use App\Models\StudentService\StudentComplaint;
use App\Models\StudentService\StudentComplaintCategory;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class StudentComplaintTable extends BasePowerGridTable
{
    public string $tableName = 'studentComplaintTable';

    protected bool $bulkActionEnabled = false;

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        $query = StudentComplaint::query()
            ->with(['studentProfile.user', 'category', 'assignedWorkUnit', 'assignedUser']);

        if (! in_array(session('active_role'), ['superuser', 'admin'], true)) {
            $user = auth()->user();
            $unitIds = $user?->activeWorkUnits()->pluck('work_units.id')->all() ?? [];
            $query->where(function ($query) use ($unitIds, $user) {
                $query->where('assigned_user_id', $user?->id ?: 0);
                if ($unitIds !== []) {
                    $query->orWhereIn('assigned_work_unit_id', $unitIds);
                }
            });
        }

        return $query->orderByRaw("FIELD(status, 'submitted', 'reopened', 'in_review', 'waiting_student', 'responded', 'resolved', 'closed', 'rejected')")
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'studentProfile' => ['nim'],
            'studentProfile.user' => ['first_name', 'last_name', 'email'],
            'category' => ['name', 'code'],
            'assignedWorkUnit' => ['name', 'code'],
            'assignedUser' => ['first_name', 'last_name', 'email'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('ticket_number')
            ->add('student', fn ($row) => $row->studentProfile?->user?->name ?? '-')
            ->add('category_name', fn ($row) => $row->category?->name ?? '-')
            ->add('assigned_unit', fn ($row) => $row->assignedWorkUnit?->name ?? '-')
            ->add('assigned_user', fn ($row) => $row->assignedUser?->name ?? '-')
            ->add('subject')
            ->add('priority_badge', fn (StudentComplaint $row) => $this->priorityBadge($row->priority))
            ->add('priority')
            ->add('status_badge', fn (StudentComplaint $row) => $this->statusBadge($row->status))
            ->add('status')
            ->add('sla_badge', fn (StudentComplaint $row) => $this->slaBadge($row))
            ->add('due_at')
            ->add('last_message_at_label', fn (StudentComplaint $row) => $row->last_message_at?->format('d M Y H:i') ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::make('Nomor', 'ticket_number')->sortable()->searchable(),
            Column::make('Mahasiswa', 'student')->searchable(),
            Column::make('Kategori', 'category_name')->searchable(),
            Column::make('Unit', 'assigned_unit')->searchable(),
            Column::make('Subject', 'subject')->searchable(),
            Column::make('Prioritas', 'priority_badge', 'priority')->sortable(),
            Column::make('Status', 'status_badge', 'status')->sortable(),
            Column::make('SLA', 'sla_badge', 'due_at')->sortable(),
            Column::make('Last Reply', 'last_message_at_label', 'last_message_at')->sortable()->hidden(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('ticket_number')->placeholder('Cari nomor tiket...'),
            Filter::select('category_name', 'student_complaint_category_id')
                ->dataSource(StudentComplaintCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('student_complaint_category_id', $value)),
            Filter::select('assigned_unit', 'assigned_work_unit_id')
                ->dataSource(WorkUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('assigned_work_unit_id', $value)),
            Filter::select('status_badge', 'status')
                ->dataSource(collect([
                    ['id' => 'submitted', 'name' => 'Masuk'],
                    ['id' => 'in_review', 'name' => 'Direview'],
                    ['id' => 'waiting_student', 'name' => 'Menunggu Mahasiswa'],
                    ['id' => 'responded', 'name' => 'Sudah Dibalas'],
                    ['id' => 'resolved', 'name' => 'Selesai'],
                    ['id' => 'closed', 'name' => 'Ditutup'],
                    ['id' => 'rejected', 'name' => 'Ditolak'],
                    ['id' => 'reopened', 'name' => 'Dibuka Lagi'],
                ]))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('status', $value)),
            Filter::select('priority_badge', 'priority')
                ->dataSource(collect([
                    ['id' => 'low', 'name' => 'Rendah'],
                    ['id' => 'normal', 'name' => 'Normal'],
                    ['id' => 'high', 'name' => 'Tinggi'],
                    ['id' => 'urgent', 'name' => 'Urgent'],
                ]))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('priority', $value)),
            Filter::datepicker('sla_badge', 'due_at'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.student-services.complaints.show', ['id' => $rowId]);
    }

    public function actions(StudentComplaint $row): array
    {
        return ActivePermission::check('student-complaint.view')
            ? [Button::add('show')->slot('<i class="fa fa-eye"></i>')->class('btn btn-primary')->dispatch('show', ['rowId' => $row->id])]
            : [];
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'resolved', 'closed' => 'bg-success',
            'waiting_student' => 'bg-warning text-dark',
            'rejected' => 'bg-danger',
            'responded' => 'bg-info',
            'in_review', 'reopened' => 'bg-primary',
            default => 'bg-secondary',
        };

        return '<span class="badge '.$class.'">'.$this->statusLabel($status).'</span>';
    }

    private function priorityBadge(string $priority): string
    {
        $class = match ($priority) {
            'urgent' => 'bg-danger',
            'high' => 'bg-warning text-dark',
            'low' => 'bg-secondary',
            default => 'bg-info',
        };

        $label = match ($priority) {
            'low' => 'Rendah',
            'high' => 'Tinggi',
            'urgent' => 'Urgent',
            default => 'Normal',
        };

        return '<span class="badge '.$class.'">'.$label.'</span>';
    }

    private function slaBadge(StudentComplaint $row): string
    {
        if (! $row->due_at) {
            return '<span class="text-muted">-</span>';
        }

        $closed = in_array($row->status, ['resolved', 'closed', 'rejected'], true);
        $class = (! $closed && $row->due_at->isPast()) ? 'text-danger fw-bold' : 'text-muted';

        return '<span class="'.$class.'">'.$row->due_at->format('d M Y H:i').'</span>';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Masuk',
            'in_review' => 'Direview',
            'waiting_student' => 'Menunggu Mahasiswa',
            'responded' => 'Sudah Dibalas',
            'resolved' => 'Selesai',
            'closed' => 'Ditutup',
            'rejected' => 'Ditolak',
            'reopened' => 'Dibuka Lagi',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }
}
