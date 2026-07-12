<?php

namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\CourseMaterial;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use App\Livewire\Concerns\ExportsPowerGridWithPhpSpreadsheet;

final class CourseMaterialTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    use WithFileUploads;

    public string $tableName = 'courseMaterialTable';

    protected ?string $bulkActionModel = CourseMaterial::class;

    protected ?string $bulkActionPermissionPrefix = 'course-material';

    protected string $bulkActionItemLabel = 'materi perkuliahan';

    // Upload modal properties
    public bool $showUploadModal = false;

    public bool $showEditModal = false;

    public ?int $editingMaterialId = null;

    public int $courseOfferingId;

    // Form fields
    public string $title = '';

    public string $description = '';

    public ?string $category = 'lecture_notes';

    public ?int $meetingNumber = null;

    public ?bool $isPublished = true;

    public $file = null;

    // Validation rules
    protected function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:syllabus,lecture_notes,assignments,references',
            'meeting_number' => 'nullable|integer|min:1|max:20',
            'is_published' => 'boolean',
            'file' => 'required|file|max:51200|mimes:pdf,ppt,pptx,doc,docx,mp4', // max 50MB
        ];
    }

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return CourseMaterial::query()
            ->with(['uploadedBy', 'courseOffering.course'])
            ->where('course_offering_id', $this->courseOfferingId)
            ->orderByDesc('meeting_number')
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'uploadedBy' => ['first_name', 'last_name', 'email'],
            'courseOffering.course' => ['code', 'name'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('title')
            ->add('category')
            ->add('meeting_number')
            ->add('file_name')
            ->add('formatted_file_size')
            ->add('download_count')
            ->add('is_published', fn (CourseMaterial $model) => $model->is_published ? '<span class="badge bg-success">Published</span>' : '<span class="badge bg-secondary">Draft</span>')
            ->add('uploaded_by_name', fn (CourseMaterial $model) => $model->uploadedBy?->name ?? '-')
            ->add('last_accessed_at')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Judul', 'title')->sortable()->searchable(),
            Column::make('Kategori', 'category')->sortable()->searchable(),
            Column::make('Pertemuan', 'meeting_number')->sortable(),
            Column::make('File', 'file_name')->sortable()->searchable(),
            Column::make('Ukuran', 'formatted_file_size'),
            Column::make('Downloads', 'download_count')->sortable(),
            Column::make('Status', 'is_published'),
            Column::make('Diupload oleh', 'uploaded_by_name')->sortable()->searchable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('title', 'title')
                ->placeholder('Cari judul materi...')
                ->operators(['contains']),
            Filter::inputText('uploaded_by_name', 'uploaded_by_name')
                ->placeholder('Cari pengupload...')
                ->builder(function (Builder $query, $value) {
                    $search = is_array($value) ? ($value['value'] ?? '') : (string) $value;
                    if ($search === '') {
                        return $query;
                    }

                    return $query->whereHas('uploadedBy', fn (Builder $user) => $user->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'));
                }),
            Filter::select('category', 'category')
                ->dataSource(collect([
                    ['id' => 'syllabus', 'name' => 'Syllabus/RPS'],
                    ['id' => 'lecture_notes', 'name' => 'Lecture Notes'],
                    ['id' => 'assignments', 'name' => 'Assignments'],
                    ['id' => 'references', 'name' => 'References'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::number('meeting_number', 'meeting_number'),
            Filter::boolean('is_published', 'is_published'),
            Filter::datepicker('created_at', 'created_at'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('lecturer.course-materials.download', ['id' => $rowId]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $material = CourseMaterial::find($rowId);

        if ($material) {
            $this->editingMaterialId = $rowId;
            $this->title = $material->title;
            $this->description = $material->description ?? '';
            $this->category = $material->category;
            $this->meeting_number = $material->meeting_number;
            $this->is_published = $material->is_published;
            $this->showEditModal = true;
        }
    }

    #[On('delete')]
    public function delete($id): void
    {
        $material = CourseMaterial::find($id);

        if ($material) {
            $this->js('
                Swal.fire({
                    title: "Hapus materi?",
                    text: "'.$material->title.' - File akan dihapus dari server!",
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
        if (! ActivePermission::check('course-material.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus materi!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'ID materi tidak ditemukan!');

            return;
        }

        $material = CourseMaterial::find($id);

        if ($material) {
            // Delete file from storage
            if (Storage::disk('public')->exists($material->file_path)) {
                Storage::disk('public')->delete($material->file_path);
            }

            $material->update(['deleted_by' => auth()->id()]);
            $material->delete();

            $this->dispatch('pg:eventRefresh-courseMaterialTable');
            $this->js('
                Swal.fire({
                    title: "Data dihapus",
                    text: "Materi berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function openUploadModal(): void
    {
        $this->resetForm();
        $this->showUploadModal = true;
    }

    public function closeUploadModal(): void
    {
        $this->showUploadModal = false;
        $this->resetForm();
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->title = '';
        $this->description = '';
        $this->category = 'lecture_notes';
        $this->meeting_number = null;
        $this->is_published = true;
        $this->file = null;
        $this->editingMaterialId = null;
    }

    public function uploadMaterial(): void
    {
        if (! ActivePermission::check('course-material.create')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk upload materi!');

            return;
        }

        $this->validate();

        try {
            // Store file
            $path = $this->file->store('course-materials', 'public');
            $originalName = $this->file->getClientOriginalName();
            $fileSize = $this->file->getSize();
            $fileType = $this->file->getClientOriginalExtension();

            // Create material record
            CourseMaterial::create([
                'course_offering_id' => $this->courseOfferingId,
                'uploaded_by' => auth()->id(),
                'title' => $this->title,
                'description' => $this->description,
                'file_path' => $path,
                'file_name' => $originalName,
                'file_type' => $fileType,
                'file_size' => $fileSize,
                'category' => $this->category,
                'meeting_number' => $this->meeting_number,
                'is_published' => $this->is_published,
                'download_count' => 0,
                'created_by' => auth()->id(),
            ]);

            $this->closeUploadModal();
            $this->dispatch('pg:eventRefresh-courseMaterialTable');

            $this->js('
                Swal.fire({
                    title: "Berhasil!",
                    text: "Materi berhasil diupload!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal mengupload materi: '.$e->getMessage());
        }
    }

    public function updateMaterial(): void
    {
        if (! ActivePermission::check('course-material.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk update materi!');

            return;
        }

        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:syllabus,lecture_notes,assignments,references',
            'meeting_number' => 'nullable|integer|min:1|max:20',
            'is_published' => 'boolean',
        ];

        // Only validate file if new file is uploaded
        if ($this->file) {
            $rules['file'] = 'nullable|file|max:51200|mimes:pdf,ppt,pptx,doc,docx,mp4';
        }

        $this->validate($rules);

        try {
            $material = CourseMaterial::find($this->editingMaterialId);

            if (! $material) {
                session()->flash('error', 'Materi tidak ditemukan!');

                return;
            }

            $updateData = [
                'title' => $this->title,
                'description' => $this->description,
                'category' => $this->category,
                'meeting_number' => $this->meeting_number,
                'is_published' => $this->is_published,
                'updated_by' => auth()->id(),
            ];

            // Handle file replacement if new file uploaded
            if ($this->file) {
                // Delete old file
                if (Storage::disk('public')->exists($material->file_path)) {
                    Storage::disk('public')->delete($material->file_path);
                }

                // Store new file
                $path = $this->file->store('course-materials', 'public');
                $updateData['file_path'] = $path;
                $updateData['file_name'] = $this->file->getClientOriginalName();
                $updateData['file_type'] = $this->file->getClientOriginalExtension();
                $updateData['file_size'] = $this->file->getSize();
            }

            $material->update($updateData);

            $this->closeEditModal();
            $this->dispatch('pg:eventRefresh-courseMaterialTable');

            $this->js('
                Swal.fire({
                    title: "Berhasil!",
                    text: "Materi berhasil diupdate!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal mengupdate materi: '.$e->getMessage());
        }
    }

    public function actions(CourseMaterial $row): array
    {
        $actions = [];

        if (ActivePermission::check('course-material.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-download"></i>')
                ->class('btn btn-info')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('course-material.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('course-material.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
