<?php

use App\Models\Academic\CourseMaterial;
use App\Models\Academic\CourseOffering;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ?int $courseOfferingId = null;

    public array $offeringInfo = [];

    public array $materials = [];

    public array $versions = [];

    // Upload/Edit form properties
    public string $title = '';

    public string $description = '';

    public string $category = 'lecture_notes';

    public ?int $meetingNumber = null;

    public $files = []; // Changed from single file to array

    public string $videoUrl = '';

    public string $videoTitle = '';

    public bool $isPublished = true;

    public ?int $editingMaterialId = null;

    public bool $showUploadForm = false;

    public bool $showEditForm = false;

    public function mount($id = null): void
    {
        if ($id === null && request()->route('offeringId')) {
            $id = request()->route('offeringId');
        }

        if ($id === null) {
            abort(404, 'Course offering ID is required');
        }

        $this->courseOfferingId = (int) $id;

        $offering = CourseOffering::with([
            'course',
            'academicYear',
            'studyProgram',
            'courseSchedules.room.building',
        ])->find($id);

        if ($offering) {
            $activeSchedule = $offering->courseSchedules->where('is_active', true)->first();

            $this->offeringInfo = [
                'id' => $offering->id,
                'course_code' => $offering->course?->code ?? '-',
                'course_name' => $offering->course?->name ?? '-',
                'label' => $offering->label ?? '-',
                'academic_year' => $offering->academicYear?->name ?? '-',
                'study_program' => $offering->studyProgram?->name ?? '-',
                'semester_no' => $offering->semester_no,
                'room' => $activeSchedule?->room?->name ?? '-',
                'building' => $activeSchedule?->room?->building?->name ?? '-',
                'delivery_mode' => $activeSchedule?->delivery_mode ?? '-',
            ];
        }

        $this->loadMaterials();
    }

    private function loadMaterials(): void
    {
        $materials = CourseMaterial::query()
            ->with(['uploadedBy', 'files'])
            ->where('course_offering_id', $this->courseOfferingId)
            ->orderByDesc('meeting_number')
            ->orderByDesc('created_at')
            ->get();

        $this->materials = $materials
            ->map(function (CourseMaterial $material) {
                return [
                    'id' => $material->id,
                    'title' => $material->title,
                    'description' => $material->description,
                    'category' => $material->category,
                    'meeting_number' => $material->meeting_number,
                    'is_published' => $material->is_published,
                    'download_count' => $material->files->sum('download_count'),
                    'uploaded_at' => $material->created_at->format('d M Y H:i'),
                    'uploaded_by' => $material->uploadedBy?->name ?? '-',
                    'files' => $material->files->map(function ($file) {
                        return [
                            'id' => $file->id,
                            'file_name' => $file->file_name,
                            'file_type' => $file->file_type,
                            'file_size' => $file->file_size,
                            'file_path' => $file->file_path,
                            'download_count' => $file->download_count,
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function recordMaterialVersion(CourseMaterial $material, string $changeType, ?string $summary = null): void
    {
        $material->loadMissing('files');

        $latestVersion = (int) $material->versions()->max('version_number');

        $material->versions()->create([
            'version_number' => $latestVersion + 1,
            'change_type' => $changeType,
            'title' => $material->title,
            'description' => $material->description,
            'category' => $material->category,
            'meeting_number' => $material->meeting_number,
            'is_published' => $material->is_published,
            'files_snapshot' => $material->files
                ->map(fn ($file) => [
                    'file_name' => $file->file_name,
                    'file_type' => $file->file_type,
                    'file_size' => $file->file_size,
                    'download_count' => $file->download_count,
                ])
                ->values()
                ->all(),
            'change_summary' => $summary,
            'created_by' => auth()->id(),
        ]);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Course Materials',
        ]);
    }

    public function openUploadForm(): void
    {
        $this->reset(['title', 'description', 'category', 'meetingNumber', 'files', 'isPublished', 'videoUrl', 'videoTitle']);
        $this->category = 'lecture_notes';
        $this->isPublished = true;
        $this->files = [];
        $this->videoUrl = '';
        $this->videoTitle = '';
        $this->showUploadForm = true;
    }

    public function closeUploadForm(): void
    {
        $this->showUploadForm = false;
        $this->reset(['title', 'description', 'category', 'meetingNumber', 'files', 'isPublished', 'videoUrl', 'videoTitle']);
    }

    public function openEditForm(int $id): void
    {
        $material = CourseMaterial::find($id);

        if (! $material) {
            return;
        }

        $this->editingMaterialId = $material->id;
        $this->title = $material->title;
        $this->description = $material->description;
        $this->category = $material->category;
        $this->meetingNumber = $material->meeting_number;
        $this->isPublished = $material->is_published;
        $this->videoUrl = '';
        $this->videoTitle = '';
        $this->showEditForm = true;
    }

    public function closeEditForm(): void
    {
        $this->showEditForm = false;
        $this->reset(['title', 'description', 'category', 'meetingNumber', 'files', 'isPublished', 'editingMaterialId', 'videoUrl', 'videoTitle']);
    }

    public function uploadMaterial(): void
    {
        $validated = $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:syllabus,lecture_notes,assignments,references',
            'meetingNumber' => 'nullable|integer|min:1|max:20',
            'files.*' => 'nullable|file|max:51200|mimes:pdf,ppt,pptx,doc,docx,mp4,jpg,jpeg,png',
            'videoUrl' => 'nullable|url|max:255',
            'videoTitle' => 'nullable|string|max:255',
        ]);

        $createData = [
            'course_offering_id' => $this->courseOfferingId,
            'uploaded_by' => auth()->id(),
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'meeting_number' => $this->meetingNumber,
            'is_published' => $this->isPublished,
        ];

        $material = CourseMaterial::create($createData);

        // Handle multiple file uploads
        if (! empty($this->files)) {
            foreach ($this->files as $file) {
                if ($file) {
                    try {
                        $originalName = $file->getClientOriginalName();
                        $fileType = $file->getClientOriginalExtension();
                        $fileSize = $file->getSize();
                        $filePath = $file->store('course-materials', 'public');

                        if ($fileSize === false || $fileSize === null) {
                            $fullPath = storage_path('app/public/'.$filePath);
                            $fileSize = file_exists($fullPath) ? filesize($fullPath) : null;
                        }

                        $material->files()->create([
                            'file_path' => $filePath,
                            'file_name' => $originalName,
                            'file_type' => $fileType,
                            'file_size' => $fileSize,
                        ]);
                    } catch (Exception $e) {
                        session()->flash('error', 'Gagal mengupload file: '.$e->getMessage());

                        return;
                    }
                }
            }
        }

        if (! empty($validated['videoUrl'])) {
            $material->files()->create([
                'file_path' => $validated['videoUrl'],
                'file_name' => $validated['videoTitle'] ?: 'Video Link',
                'file_type' => 'link',
                'file_size' => null,
            ]);
        }

        $this->recordMaterialVersion($material->fresh('files'), 'created', 'Materi dibuat.');

        session()->flash('success', 'Materi berhasil diupload!');
        $this->closeUploadForm();
        $this->loadMaterials();
    }

    public function updateMaterial(): void
    {
        if (! $this->editingMaterialId) {
            return;
        }

        $material = CourseMaterial::with('files')->find($this->editingMaterialId);

        if (! $material) {
            session()->flash('error', 'Materi tidak ditemukan.');
            $this->closeEditForm();

            return;
        }

        $validated = $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:syllabus,lecture_notes,assignments,references',
            'meetingNumber' => 'nullable|integer|min:1|max:20',
            'files.*' => 'nullable|file|max:51200|mimes:pdf,ppt,pptx,doc,docx,mp4,jpg,jpeg,png',
            'videoUrl' => 'nullable|url|max:255',
            'videoTitle' => 'nullable|string|max:255',
        ]);

        $updateData = [
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'meeting_number' => $this->meetingNumber,
            'is_published' => $this->isPublished,
        ];

        $material->update($updateData);

        // Handle new file uploads (append to existing files)
        if (! empty($this->files)) {
            foreach ($this->files as $file) {
                if ($file) {
                    try {
                        $originalName = $file->getClientOriginalName();
                        $fileType = $file->getClientOriginalExtension();
                        $fileSize = $file->getSize();
                        $filePath = $file->store('course-materials', 'public');

                        if ($fileSize === false || $fileSize === null) {
                            $fullPath = storage_path('app/public/'.$filePath);
                            $fileSize = file_exists($fullPath) ? filesize($fullPath) : null;
                        }

                        $material->files()->create([
                            'file_path' => $filePath,
                            'file_name' => $originalName,
                            'file_type' => $fileType,
                            'file_size' => $fileSize,
                        ]);
                    } catch (Exception $e) {
                        session()->flash('error', 'Gagal mengupload file: '.$e->getMessage());

                        return;
                    }
                }
            }
        }

        if (! empty($validated['videoUrl'])) {
            $material->files()->create([
                'file_path' => $validated['videoUrl'],
                'file_name' => $validated['videoTitle'] ?: 'Video Link',
                'file_type' => 'link',
                'file_size' => null,
            ]);
        }

        $this->recordMaterialVersion($material->fresh('files'), 'updated', 'Materi diperbarui.');

        session()->flash('success', 'Materi berhasil diupdate!');
        $this->closeEditForm();
        $this->loadMaterials();
    }

    public function deleteMaterial(int $id): void
    {
        $material = CourseMaterial::with('files')->find($id);

        if (! $material) {
            session()->flash('error', 'Materi tidak ditemukan.');

            return;
        }

        // Hapus semua files terkait
        foreach ($material->files as $file) {
            if ($file->file_path) {
                Storage::disk('public')->delete($file->file_path);
            }
        }

        $material->delete();

        session()->flash('success', 'Materi berhasil dihapus!');
        $this->loadMaterials();
    }

    public function confirmDeleteMaterial(int $id): void
    {
        $this->js('
            Swal.fire({
                title: "Hapus materi?",
                text: "Materi dan semua lampirannya akan dihapus permanen.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya, hapus",
                cancelButtonText: "Batal",
                confirmButtonColor: "#dc3545",
                cancelButtonColor: "#6c757d"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("deleteMaterialConfirmed", { id: '.$id.' });
                }
            });
        ');
    }

    #[On('deleteMaterialConfirmed')]
    public function deleteMaterialConfirmed($id = null): void
    {
        if (! $id) {
            return;
        }

        $this->deleteMaterial((int) $id);
    }

    public function togglePublish(int $id): void
    {
        $material = CourseMaterial::find($id);

        if (! $material) {
            return;
        }

        $material->update([
            'is_published' => ! $material->is_published,
        ]);

        $this->recordMaterialVersion(
            $material->fresh('files'),
            'status_changed',
            $material->is_published ? 'Materi dipublikasikan.' : 'Materi disimpan sebagai draft.'
        );

        $this->loadMaterials();
    }
}
?>

<div class="container-xl py-4">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            <h2 class="alert-title"><i class="fas fa-check-circle me-2"></i>Berhasil!</h2>
            <p class="mb-0">{{ session('success') }}</p>
            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible" role="alert">
            <h2 class="alert-title"><i class="fas fa-exclamation-circle me-2"></i>Error!</h2>
            <p class="mb-0">{{ session('error') }}</p>
            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
        </div>
    @endif

    {{-- Hero Section --}}
    <div class="modern-hero" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; padding: 2.5rem; margin-bottom: 2rem; position: relative; overflow: hidden;">
        <div style="position: absolute; top: -50%; right: -20%; width: 400px; height: 400px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
        <div style="position: absolute; bottom: -30%; left: -10%; width: 300px; height: 300px; background: rgba(255,255,255,0.05); border-radius: 50%;"></div>
        
        <div style="position: relative; z-index: 1;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-book-open" style="color: rgba(255,255,255,0.9); font-size: 1.5rem;"></i>
                        <span style="color: rgba(255,255,255,0.9); font-weight: 600; font-size: 0.9rem;">Course Materials</span>
                    </div>
                    <h1 class="mb-2" style="color: white; font-weight: 800; font-size: 1.8rem;">
                        {{ $offeringInfo['course_code'] }} - {{ $offeringInfo['course_name'] }}
                    </h1>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="info-badge" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); color: white;">
                            <i class="fas fa-users"></i>
                            {{ $offeringInfo['label'] }}
                        </span>
                        <span class="info-badge" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); color: white;">
                            <i class="fas fa-hashtag"></i>
                            {{ $offeringInfo['academic_year'] }}
                        </span>
                        @if($offeringInfo['building'] !== '-' || $offeringInfo['room'] !== '-')
                            <span class="info-badge" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); color: white;">
                                <i class="fas fa-building"></i>
                                {{ $offeringInfo['building'] }} / {{ $offeringInfo['room'] }}
                            </span>
                        @endif
                        @if($offeringInfo['delivery_mode'] !== '-')
                            <span class="info-badge" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); color: white;">
                                <i class="fas fa-laptop-house"></i>
                                {{ $offeringInfo['delivery_mode'] }}
                            </span>
                        @endif
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <a href="{{ route('lecturer.course-offerings.index') }}" class="btn btn-lg" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); color: white; border: 2px solid rgba(255,255,255,0.3); border-radius: 12px;">
                        <i class="fas fa-arrow-left me-2"></i>Kembali
                    </a>
                    @if(!$showUploadForm)
                        <button type="button" wire:click="openUploadForm" class="btn btn-lg" style="background: white; color: #667eea; border: none; border-radius: 12px; font-weight: 600; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                            <i class="fas fa-cloud-upload-alt me-2"></i>Upload Materi
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <style>
        .modern-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .modern-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }
        
        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.7rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .material-item {
            background: #f8fafc;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .material-item:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .form-section {
            background: #f8fafc;
            border-radius: 18px;
            padding: 1.25rem;
            margin-top: 2rem;
            border: 2px solid #e2e8f0;
        }

        .upload-header {
            padding: 1.25rem;
            border-radius: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin-bottom: 1.25rem;
        }

        .upload-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.25rem;
        }

        .upload-panel {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
        }

        .upload-panel-title {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1rem;
            font-weight: 800;
            color: #1e293b;
        }

        .upload-panel-title i {
            color: #667eea;
        }

        .upload-field {
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem 1rem;
        }

        .upload-field:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.12);
        }

        .upload-help {
            color: #64748b;
            font-size: 0.78rem;
            line-height: 1.5;
        }

        .upload-dropzone {
            border: 2px dashed #c7d2fe;
            border-radius: 14px;
            background: #eef2ff;
            cursor: pointer;
            padding: 1.25rem;
            transition: all 0.2s ease;
        }

        .upload-dropzone:hover,
        .upload-dropzone.is-dragging {
            background: #e0e7ff;
            border-color: #667eea;
            box-shadow: 0 8px 22px rgba(102, 126, 234, 0.16);
            transform: translateY(-2px);
        }

        .upload-dropzone-icon {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
            margin-bottom: 0.75rem;
        }

        .upload-progress-track {
            height: 0.7rem;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .upload-progress-bar {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.2s ease;
        }

        .publish-box {
            border-radius: 14px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            padding: 1rem;
        }

        .selected-file-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 0.65rem;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #334155;
            font-weight: 600;
            font-size: 0.8rem;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

    {{-- Statistics Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="modern-card p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Total Materi</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: #1e293b; margin-top: 0.25rem;">{{ count($materials) }}</div>
                    </div>
                    <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-folder-open" style="color: white; font-size: 1.3rem;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Published</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: #10b981; margin-top: 0.25rem;">{{ collect($materials)->where('is_published', true)->count() }}</div>
                    </div>
                    <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-eye" style="color: white; font-size: 1.3rem;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Draft</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: #f59e0b; margin-top: 0.25rem;">{{ collect($materials)->where('is_published', false)->count() }}</div>
                    </div>
                    <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-eye-slash" style="color: white; font-size: 1.3rem;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Total Download</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: #3b82f6; margin-top: 0.25rem;">{{ collect($materials)->sum('download_count') }}</div>
                    </div>
                    <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-download" style="color: white; font-size: 1.3rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Inline Upload Form --}}
    @if($showUploadForm)
        <div class="form-section" style="animation: slideDown 0.3s ease;">
            <div class="upload-header">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; opacity: 0.85; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;">Materi Perkuliahan</div>
                            <h3 class="mb-1" style="font-weight: 800;">Upload Materi Baru</h3>
                            <div style="opacity: 0.9;">Tambahkan konten, file pendukung, atau video untuk kelas ini.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeUploadForm"></button>
                </div>
            </div>

            <form wire:submit.prevent="uploadMaterial">
                <div class="row g-4">
                    <div class="col-xl-8">
                        <div class="upload-panel h-100">
                            <div class="upload-panel-title">
                                <i class="fas fa-pen-nib"></i>
                                <span>Konten Materi</span>
                            </div>

                            <div class="mb-4">
                                <label class="form-label" style="font-weight: 700; color: #1e293b;">Judul Materi</label>
                                <input
                                    type="text"
                                    class="form-control form-control-lg upload-field"
                                    wire:model="title"
                                    placeholder="Contoh: Pengantar Basis Data"
                                    required
                                >
                            </div>

                            <div>
                                <label class="form-label" style="font-weight: 700; color: #1e293b;">Konten / Deskripsi</label>
                                <livewire:jodit-text-editor
                                    wire:model.live="description"
                                    identifier="upload-editor"
                                    :height="320"
                                />
                                <div class="upload-help mt-2">
                                    <i class="fas fa-info-circle me-1"></i>Tulis ringkasan, instruksi belajar, atau catatan penting untuk mahasiswa.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="d-flex flex-column gap-3">
                            <div class="upload-panel">
                                <div class="upload-panel-title">
                                    <i class="fas fa-sliders"></i>
                                    <span>Pengaturan</span>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" style="font-weight: 700; color: #1e293b;">Kategori</label>
                                    <select class="form-select form-select-lg upload-field" wire:model="category">
                                        <option value="syllabus">Silabus/RPS</option>
                                        <option value="lecture_notes">Catatan Kuliah</option>
                                        <option value="assignments">Tugas</option>
                                        <option value="references">Referensi</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label" style="font-weight: 700; color: #1e293b;">Pertemuan</label>
                                    <input
                                        type="number"
                                        class="form-control form-control-lg upload-field"
                                        wire:model="meetingNumber"
                                        min="1"
                                        max="20"
                                        placeholder="Opsional"
                                    >
                                </div>
                            </div>

                            <div class="upload-panel">
                                <div class="upload-panel-title">
                                    <i class="fas fa-paperclip"></i>
                                    <span>Lampiran</span>
                                </div>

                                <div
                                    class="upload-dropzone"
                                    x-data="{ uploading: false, progress: 0, dragging: false }"
                                    x-on:dragover.prevent="dragging = true"
                                    x-on:dragleave.prevent="dragging = false"
                                    x-on:drop.prevent="dragging = false; $refs.filesInput.files = $event.dataTransfer.files; $refs.filesInput.dispatchEvent(new Event('change', { bubbles: true }))"
                                    x-on:livewire-upload-start="uploading = true; progress = 0"
                                    x-on:livewire-upload-finish="uploading = false; progress = 100"
                                    x-on:livewire-upload-error="uploading = false"
                                    x-on:livewire-upload-progress="progress = $event.detail.progress"
                                    x-bind:class="{ 'is-dragging': dragging }"
                                    x-on:click="$refs.filesInput.click()"
                                >
                                    <div class="upload-dropzone-icon">
                                        <i class="fas fa-cloud-arrow-up"></i>
                                    </div>
                                    <label class="form-label mb-1" style="font-weight: 800; color: #3730a3;">File Materi</label>
                                    <div class="upload-help mb-3">Tarik file ke area ini atau klik untuk memilih file.</div>
                                    <input
                                        type="file"
                                        class="d-none"
                                        wire:model="files"
                                        multiple
                                        accept=".pdf,.ppt,.pptx,.doc,.docx,.mp4,.jpg,.jpeg,.png"
                                        x-ref="filesInput"
                                        x-on:click.stop
                                    >
                                    <div class="upload-help mt-2">PDF, PPT, DOC, MP4, JPG, PNG. Maksimal 50MB per file.</div>

                                    <div class="mt-3" x-show="uploading" x-cloak>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="upload-help fw-bold">Mengupload file...</span>
                                            <span class="upload-help fw-bold" x-text="progress + '%'"></span>
                                        </div>
                                        <div class="upload-progress-track">
                                            <div class="upload-progress-bar" x-bind:style="`width: ${progress}%`"></div>
                                        </div>
                                    </div>
                                </div>

                                @if($files)
                                    <div class="mt-3">
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach($files as $index => $file)
                                                <div class="selected-file-chip">
                                                    <i class="fas fa-file"></i>
                                                    {{ $file->getClientOriginalName() }}
                                                    <button
                                                        type="button"
                                                        class="btn-close btn-close-xs"
                                                        wire:click="$remove('files', {{ $index }})"
                                                        aria-label="Remove"
                                                    ></button>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="upload-panel">
                                <div class="upload-panel-title">
                                    <i class="fas fa-link"></i>
                                    <span>Video Link</span>
                                </div>

                                <input
                                    type="url"
                                    class="form-control form-control-lg upload-field mb-2"
                                    wire:model="videoUrl"
                                    placeholder="https://www.youtube.com/watch?v=..."
                                >
                                <input
                                    type="text"
                                    class="form-control upload-field"
                                    wire:model="videoTitle"
                                    placeholder="Judul video (opsional)"
                                >
                                <div class="upload-help mt-2">Link akan ditampilkan sebagai embed di halaman learning mahasiswa.</div>
                            </div>

                            <div class="publish-box">
                                <div class="form-check form-switch">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="isPublished"
                                        wire:model="isPublished"
                                        style="width: 3em; height: 1.5em;"
                                    >
                                    <label class="form-check-label" for="isPublished" style="font-weight: 800; color: #065f46;">
                                        <i class="fas fa-eye me-2"></i>Publikasikan
                                    </label>
                                </div>
                                <div class="upload-help mt-2" style="color: #047857;">Materi langsung tersedia untuk mahasiswa setelah disimpan.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-lg" wire:click="closeUploadForm" style="background: #f1f5f9; color: #64748b; border: 2px solid #e2e8f0; border-radius: 12px; font-weight: 600;">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-lg" wire:loading.attr="disabled" wire:target="files,uploadMaterial" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 12px; font-weight: 700; padding: 0.75rem 2rem;">
                        <span wire:loading.remove wire:target="uploadMaterial"><i class="fas fa-cloud-upload-alt me-2"></i>Upload Materi</span>
                        <span wire:loading wire:target="uploadMaterial"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Menyimpan...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Materials List --}}
    <div class="card modern-card mt-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h3 class="mb-0" style="font-weight: 700; color: #1e293b;">
                    <i class="fas fa-list me-2" style="color: #667eea;"></i>Daftar Materi
                </h3>
                <span class="badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 0.5rem 1rem; border-radius: 20px;">
                    {{ count($materials) }} Materi
                </span>
            </div>

            @forelse($materials as $material)
                <div class="material-item">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-start gap-3">
                                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    @php
                                        // Get file type from first file if exists
                                        $firstFileType = !empty($material['files']) ? $material['files'][0]['file_type'] ?? null : null;
                                        $icon = match($firstFileType) {
                                            'pdf' => 'fa-file-pdf',
                                            'ppt', 'pptx' => 'fa-file-powerpoint',
                                            'doc', 'docx' => 'fa-file-word',
                                            'mp4' => 'fa-file-video',
                                            'jpg', 'jpeg', 'png' => 'fa-file-image',
                                            'link' => 'fa-link',
                                            default => 'fa-folder-open',
                                        };
                                    @endphp
                                    <i class="fas {{ $icon }}" style="color: white; font-size: 1.5rem;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <h5 class="mb-0" style="font-weight: 700; color: #1e293b;">{{ $material['title'] }}</h5>
                                        @if($material['is_published'])
                                            <span class="category-badge" style="background: #dcfce7; color: #16a34a;">
                                                <i class="fas fa-check-circle"></i> Published
                                            </span>
                                        @else
                                            <span class="category-badge" style="background: #fef3c7; color: #d97706;">
                                                <i class="fas fa-clock"></i> Draft
                                            </span>
                                        @endif
                                    </div>
                                    @if($material['description'])
                                        <div class="text-muted mb-2" style="font-size: 0.9rem;">{!! Str::limit(strip_tags($material['description']), 150) !!}</div>
                                    @endif
                                    <div class="d-flex flex-wrap gap-2" style="font-size: 0.85rem; color: #64748b;">
                                        @if($material['meeting_number'])
                                            <span>
                                                <i class="fas fa-hashtag me-1"></i>Pertemuan {{ $material['meeting_number'] }}
                                            </span>
                                        @endif
                                        <span>
                                            <i class="fas fa-tag me-1"></i>
                                            {{ ucwords(str_replace('_', ' ', $material['category'])) }}
                                        </span>
                                        @if(!empty($material['files']))
                                            @foreach($material['files'] as $file)
                                                <span>
                                                    <i class="fas fa-file me-1"></i>{{ $file['file_name'] }}
                                                    @if($file['file_type'] === 'link')
                                                        <span class="text-muted">(Link)</span>
                                                    @elseif($file['file_size'])
                                                        <span class="text-muted">({{ number_format($file['file_size'] / 1024, 2) }} KB)</span>
                                                    @endif
                                                </span>
                                            @endforeach
                                        @endif
                                        <span>
                                            <i class="fas fa-clock me-1"></i>{{ $material['uploaded_at'] }}
                                        </span>
                                        <span>
                                            <i class="fas fa-download me-1"></i>{{ $material['download_count'] }} downloads
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                                <a
                                    href="{{ route('lecturer.course-materials.show', ['material' => $material['id']]) }}"
                                    class="action-btn"
                                    style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; text-decoration: none;"
                                >
                                    <i class="fas fa-eye"></i> Detail
                                </a>
                                <button 
                                    type="button"
                                    class="action-btn"
                                    wire:click="togglePublish({{ $material['id'] }})"
                                    style="background: {{ $material['is_published'] ? 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)' : 'linear-gradient(135deg, #10b981 0%, #059669 100%)' }}; color: white;"
                                >
                                    <i class="fas fa-{{ $material['is_published'] ? 'eye-slash' : 'eye' }}"></i>
                                    {{ $material['is_published'] ? 'Unpublish' : 'Publish' }}
                                </button>
                                <button 
                                    type="button"
                                    class="action-btn"
                                    wire:click="openEditForm({{ $material['id'] }})"
                                    style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white;"
                                >
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button
                                    type="button" 
                                    class="action-btn"
                                    wire:click="confirmDeleteMaterial({{ $material['id'] }})"
                                    style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white;"
                                >
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                                @if(!empty($material['files']))
                                    <div class="dropdown">
                                        <button 
                                            type="button"
                                            class="action-btn dropdown-toggle"
                                            data-bs-toggle="dropdown"
                                            style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;"
                                        >
                                            <i class="fas fa-download"></i> Download ({{ count($material['files']) }})
                                        </button>
                                        <ul class="dropdown-menu">
                                            @foreach($material['files'] as $file)
                                                <li>
                                                    @if($file['file_type'] === 'link')
                                                        <a
                                                            href="{{ route('lecturer.course-materials.link', ['id' => $material['id'], 'fileId' => $file['id']]) }}"
                                                            class="dropdown-item"
                                                            target="_blank"
                                                        >
                                                            <i class="fas fa-link me-2"></i>{{ $file['file_name'] }}
                                                            <small class="text-muted">(Link)</small>
                                                        </a>
                                                    @else
                                                        <a 
                                                            href="{{ route('lecturer.course-materials.download', ['id' => $material['id'], 'fileId' => $file['id']]) }}"
                                                            class="dropdown-item"
                                                        >
                                                            <i class="fas fa-file me-2"></i>{{ $file['file_name'] }}
                                                            @if($file['file_size'])
                                                                <small class="text-muted">({{ number_format($file['file_size'] / 1024, 2) }} KB)</small>
                                                            @endif
                                                        </a>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-5">
                    <i class="fas fa-inbox" style="font-size: 4rem; color: #cbd5e1;"></i>
                    <div class="mt-3" style="font-size: 1.1rem; color: #64748b; font-weight: 500;">Belum ada materi perkuliahan.</div>
                    <div style="font-size: 0.9rem; color: #94a3b8; margin-top: 0.5rem;">Klik tombol "Upload Materi" untuk menambahkan materi pertama.</div>
                </div>
            @endforelse
        </div>
    </div>
    
    {{-- Inline Edit Form --}}
    @if($showEditForm)
        <div class="form-section" style="animation: slideDown 0.3s ease;">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-edit" style="font-size: 1.3rem; color: #667eea;"></i>
                    <h3 class="mb-0" style="font-weight: 700;">Edit Materi</h3>
                </div>
                <button type="button" class="btn-close" wire:click="closeEditForm"></button>
            </div>
            
            <form wire:submit.prevent="updateMaterial">
                <div class="mb-4">
                    <label class="form-label" style="font-weight: 600; color: #1e293b;">
                        <i class="fas fa-heading me-2" style="color: #667eea;"></i>Judul Materi
                    </label>
                    <input 
                        type="text" 
                        class="form-control form-control-lg"
                        wire:model="title"
                        placeholder="Masukkan judul materi..."
                        style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 12px 16px;"
                        required
                    >
                </div>
                    
                <div class="mb-4">
                    <label class="form-label" style="font-weight: 600; color: #1e293b;">
                        <i class="fas fa-align-left me-2" style="color: #667eea;"></i>Konten / Deskripsi
                    </label>
                    <livewire:jodit-text-editor 
                        wire:model.live="description" 
                        identifier="edit-editor"
                        :height="300"
                    />
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-info-circle me-1"></i>Tulis konten materi dengan formatting lengkap (bold, italic, lists, headings, dll).
                    </small>
                </div>
                    
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label" style="font-weight: 600; color: #1e293b;">
                            <i class="fas fa-tag me-2" style="color: #667eea;"></i>Kategori
                        </label>
                        <select class="form-select form-select-lg" wire:model="category" style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 12px 16px;">
                            <option value="syllabus">Silabus/RPS</option>
                            <option value="lecture_notes">Catatan Kuliah</option>
                            <option value="assignments">Tugas</option>
                            <option value="references">Referensi</option>
                        </select>
                    </div>
                        
                    <div class="col-md-6">
                        <label class="form-label" style="font-weight: 600; color: #1e293b;">
                            <i class="fas fa-hashtag me-2" style="color: #667eea;"></i>Pertemuan (Opsional)
                        </label>
                        <input 
                            type="number" 
                            class="form-control form-control-lg"
                            wire:model="meetingNumber"
                            min="1"
                            max="20"
                            placeholder="No. pertemuan..."
                            style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 12px 16px;"
                        >
                    </div>
                </div>
                    
                <div class="mb-4">
                    <label class="form-label" style="font-weight: 600; color: #1e293b;">
                        <i class="fas fa-file-upload me-2" style="color: #667eea;"></i>Tambah File Baru (Opsional)
                    </label>
                    
                    @php
                        $editingMaterial = \App\Models\Academic\CourseMaterial::with('files')->find($editingMaterialId);
                    @endphp
                    
                    @if($editingMaterial && $editingMaterial->files->count() > 0)
                        <div class="mb-3">
                            <label class="form-label" style="font-size: 0.85rem; color: #64748b;">File yang sudah ada:</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($editingMaterial->files as $file)
                                    <div class="badge bg-light text-dark p-2" style="border: 1px solid #e2e8f0;">
                                        <i class="fas fa-file me-1"></i>
                                        {{ $file->file_name }}
                                        <span style="font-size: 0.75rem; color: #94a3b8;">({{ number_format($file->file_size / 1024, 2) }} KB)</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    
                    <div
                        class="upload-dropzone"
                        x-data="{ uploading: false, progress: 0, dragging: false }"
                        x-on:dragover.prevent="dragging = true"
                        x-on:dragleave.prevent="dragging = false"
                        x-on:drop.prevent="dragging = false; $refs.editFilesInput.files = $event.dataTransfer.files; $refs.editFilesInput.dispatchEvent(new Event('change', { bubbles: true }))"
                        x-on:livewire-upload-start="uploading = true; progress = 0"
                        x-on:livewire-upload-finish="uploading = false; progress = 100"
                        x-on:livewire-upload-error="uploading = false"
                        x-on:livewire-upload-progress="progress = $event.detail.progress"
                        x-bind:class="{ 'is-dragging': dragging }"
                        x-on:click="$refs.editFilesInput.click()"
                    >
                        <div class="upload-dropzone-icon">
                            <i class="fas fa-file-circle-plus"></i>
                        </div>
                        <div style="font-weight: 800; color: #3730a3;">Tambah file baru</div>
                        <div class="upload-help mt-1">Tarik file ke area ini atau klik untuk memilih file. File yang sudah ada tidak akan dihapus.</div>
                        <input
                            type="file"
                            class="d-none"
                            wire:model="files"
                            multiple
                            accept=".pdf,.ppt,.pptx,.doc,.docx,.mp4,.jpg,.jpeg,.png"
                            x-ref="editFilesInput"
                            x-on:click.stop
                        >

                        <div class="mt-3" x-show="uploading" x-cloak>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="upload-help fw-bold">Mengupload file...</span>
                                <span class="upload-help fw-bold" x-text="progress + '%'"></span>
                            </div>
                            <div class="upload-progress-track">
                                <div class="upload-progress-bar" x-bind:style="`width: ${progress}%`"></div>
                            </div>
                        </div>
                    </div>
                        
                    @if($files)
                        <div class="mt-3">
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($files as $index => $file)
                                    <div class="badge bg-primary text-white p-2" style="border: 1px solid #667eea;">
                                        <i class="fas fa-plus-circle me-1"></i>
                                        {{ $file->getClientOriginalName() }}
                                        <button 
                                            type="button" 
                                            class="btn-close btn-close-xs ms-2" 
                                            style="filter: invert(1);"
                                            wire:click="$remove('files', {{ $index }})"
                                            aria-label="Remove"
                                        ></button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mb-4">
                    <label class="form-label" style="font-weight: 600; color: #1e293b;">
                        <i class="fas fa-link me-2" style="color: #667eea;"></i>Video Link Tambahan (Opsional)
                    </label>
                    <input
                        type="url"
                        class="form-control form-control-lg"
                        wire:model="videoUrl"
                        placeholder="https://www.youtube.com/watch?v=..."
                        style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 12px 16px;"
                    >
                    <input
                        type="text"
                        class="form-control form-control-lg mt-2"
                        wire:model="videoTitle"
                        placeholder="Judul video (opsional)"
                        style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 12px 16px;"
                    >
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-info-circle me-1"></i>Link baru akan ditambahkan sebagai lampiran.
                    </small>
                </div>
                    
                <div class="mb-4">
                    <div class="form-check form-switch">
                        <input 
                            class="form-check-input" 
                            type="checkbox" 
                            id="isPublishedEdit"
                            wire:model="isPublished"
                            style="width: 3em; height: 1.5em;"
                        >
                        <label class="form-check-label" for="isPublishedEdit" style="font-weight: 600; color: #1e293b;">
                            <i class="fas fa-eye me-2" style="color: #667eea;"></i>Publikasikan
                        </label>
                    </div>
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-info-circle me-1"></i>Aktifkan untuk membuat materi langsung tersedia untuk mahasiswa.
                    </small>
                </div>
                    
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-lg" wire:loading.attr="disabled" wire:target="files,updateMaterial" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 12px; font-weight: 600; padding: 0.75rem 2rem;">
                        <span wire:loading.remove wire:target="updateMaterial"><i class="fas fa-save me-2"></i>Simpan Perubahan</span>
                        <span wire:loading wire:target="updateMaterial"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Menyimpan...</span>
                    </button>
                    <button type="button" class="btn btn-lg" wire:click="closeEditForm" style="background: #f1f5f9; color: #64748b; border: 2px solid #e2e8f0; border-radius: 12px; font-weight: 600;">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
