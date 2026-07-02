<?php

namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\CourseMaterial;
use App\Models\Academic\CourseMaterialDownload;
use App\Models\Academic\StudyPlan;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use App\Livewire\Concerns\ExportsPowerGridWithPhpSpreadsheet;

final class StudentCourseMaterialTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'studentCourseMaterialTable';

    public int $courseOfferingId;

    // Accept filters from parent component
    public ?string $searchQuery = null;

    public ?string $selectedCategory = null;

    public ?int $selectedMeetingNumber = null;

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        $query = CourseMaterial::query()
            ->with(['uploadedBy', 'courseOffering.course'])
            ->where('course_offering_id', $this->courseOfferingId)
            ->where('is_published', true)
            ->orderByDesc('meeting_number')
            ->orderByDesc('created_at');

        // Apply category filter
        if ($this->selectedCategory && $this->selectedCategory !== 'all') {
            $query->where('category', $this->selectedCategory);
        }

        // Apply meeting number filter
        if ($this->selectedMeetingNumber) {
            $query->where('meeting_number', $this->selectedMeetingNumber);
        }

        // Apply search
        if ($this->searchQuery) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%'.$this->searchQuery.'%')
                    ->orWhere('description', 'like', '%'.$this->searchQuery.'%')
                    ->orWhere('file_name', 'like', '%'.$this->searchQuery.'%');
            });
        }

        return $query;
    }

    public function relationSearch(): array
    {
        return [
            'uploadedBy' => ['first_name', 'last_name'],
            'courseOffering.course' => ['code', 'name'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('title')
            ->add('description')
            ->add('category', fn (CourseMaterial $model) => $this->getCategoryBadge($model->category))
            ->add('meeting_number', fn (CourseMaterial $model) => $model->meeting_number ? 'Pertemuan '.$model->meeting_number : '-')
            ->add('file_name')
            ->add('formatted_file_size')
            ->add('download_count')
            ->add('uploaded_by_name', fn (CourseMaterial $model) => $model->uploadedBy?->name ?? '-')
            ->add('last_accessed_at', fn (CourseMaterial $model) => $model->last_accessed_at ? $model->last_accessed_at->diffForHumans() : 'Belum pernah')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Judul', 'title')->sortable()->searchable(),
            Column::make('Deskripsi', 'description')->sortable()->searchable(),
            Column::make('Kategori', 'category'),
            Column::make('Pertemuan', 'meeting_number')->sortable(),
            Column::make('File', 'file_name')->sortable()->searchable(),
            Column::make('Ukuran', 'formatted_file_size'),
            Column::make('Downloads', 'download_count')->sortable(),
            Column::make('Diupload oleh', 'uploaded_by_name')->sortable(),
            Column::make('Terakhir diakses', 'last_accessed_at'),
            Column::action('Action'),
        ];
    }

    #[On('download')]
    public function download($rowId): void
    {
        $material = CourseMaterial::find($rowId);

        if (! $material || ! $material->is_published) {
            session()->flash('error', 'Materi tidak ditemukan atau belum dipublikasikan!');

            return;
        }

        // Verify student is enrolled in this course offering
        $user = auth()->user();
        $studentProfileId = $user->studentProfile?->id;
        $isEnrolled = $studentProfileId
            ? StudyPlan::query()
                ->where('student_profile_id', $studentProfileId)
                ->whereHas('details', function ($query) use ($material) {
                    $query->where('course_offering_id', $material->course_offering_id);
                })
                ->exists()
            : false;

        if (! $isEnrolled && ! $user->hasRole('lecturer')) {
            session()->flash('error', 'Anda tidak memiliki akses ke materi ini!');

            return;
        }

        // Track download
        CourseMaterialDownload::updateOrCreate(
            [
                'course_material_id' => $material->id,
                'student_profile_id' => $studentProfileId,
            ],
            [
                'downloaded_at' => now(),
                'ip_address' => request()->ip(),
            ]
        );

        // Increment download counter
        $material->increment('download_count');
        $material->update(['last_accessed_at' => now()]);

        // Download file
        $filePath = storage_path('app/public/'.$material->file_path);

        if (! file_exists($filePath)) {
            session()->flash('error', 'File tidak ditemukan di server!');

            return;
        }

        $this->redirectRoute('lecturer.course-materials.download', ['id' => $material->id], navigate: true);
    }

    private function getCategoryBadge(string $category): string
    {
        $badges = [
            'syllabus' => '<span class="badge bg-primary">Syllabus/RPS</span>',
            'lecture_notes' => '<span class="badge bg-info">Lecture Notes</span>',
            'assignments' => '<span class="badge bg-warning">Assignments</span>',
            'references' => '<span class="badge bg-secondary">References</span>',
        ];

        return $badges[$category] ?? '<span class="badge bg-light">'.$category.'</span>';
    }

    public function actions(CourseMaterial $row): array
    {
        return [
            Button::add('download')
                ->slot('<i class="fa fa-download"></i> Download')
                ->class('btn btn-success')
                ->dispatch('download', ['rowId' => $row->id]),
        ];
    }
}
