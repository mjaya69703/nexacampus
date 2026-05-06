<?php

use App\Models\Academic\CourseMaterial;
use App\Models\Academic\CourseMaterialBookmark;
use Livewire\Component;

new class extends Component
{
    public array $materials = [];
    public array $courses = [];
    public ?int $selectedCourseId = null;
    public bool $showBookmarkedOnly = false;
    public string $searchQuery = '';
    public ?int $offeringId = null; // For specific course offering view
    
    public function mount($offeringId = null): void
    {
        if ($offeringId) {
            $this->offeringId = (int) $offeringId;
            $this->selectedCourseId = $this->offeringId;
        }
        
        $this->loadCourses();
        $this->loadMaterials();
    }
    
    private function loadCourses(): void
    {
        $user = auth()->user();
        
        $studyPlans = $user->studentProfile?->studyPlans()
            ->with(['details.courseOffering.course', 'details.courseOffering.academicYear'])
            ->get();
        
        $this->courses = $studyPlans
            ->flatMap(function ($plan) {
                return $plan->details->map(function ($detail) {
                    return [
                        'id' => $detail->courseOffering->id,
                        'course_code' => $detail->courseOffering->course->code ?? '-',
                        'course_name' => $detail->courseOffering->course->name ?? '-',
                        'label' => $detail->courseOffering->label ?? '-',
                        'academic_year' => $detail->courseOffering->academicYear->name ?? '-',
                    ];
                });
            })
            ->values()
            ->all();
    }
    
    private function loadMaterials(): void
    {
        $user = auth()->user();
        
        // Get all course offerings the student is enrolled in
        $studyPlans = $user->studentProfile?->studyPlans()
            ->with('details.courseOffering')
            ->get();
        
        $courseOfferingIds = $studyPlans
            ->flatMap(function ($plan) {
                return $plan->details->pluck('course_offering_id');
            })
            ->unique()
            ->values()
            ->all();
        
        if (empty($courseOfferingIds)) {
            $this->materials = [];
            return;
        }
        
        $query = CourseMaterial::query()
            ->with(['files', 'courseOffering.course'])
            ->whereIn('course_offering_id', $courseOfferingIds)
            ->where('is_published', true);
        
        // Filter by selected course
        if ($this->selectedCourseId) {
            $query->where('course_offering_id', $this->selectedCourseId);
        }
        
        // Filter bookmarked only
        if ($this->showBookmarkedOnly) {
            $bookmarkedIds = CourseMaterialBookmark::where('student_profile_id', $user->studentProfile->id)
                ->pluck('course_material_id')
                ->toArray();
            
            $query->whereIn('id', $bookmarkedIds);
        }
        
        // Search query
        if ($this->searchQuery) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('description', 'like', '%' . $this->searchQuery . '%');
            });
        }
        
        $materials = $query->orderByDesc('created_at')->get();
        
        $this->materials = $materials
            ->map(function (CourseMaterial $material) use ($user) {
                $isBookmarked = CourseMaterialBookmark::where('student_profile_id', $user->studentProfile->id)
                    ->where('course_material_id', $material->id)
                    ->exists();
                
                return [
                    'id' => $material->id,
                    'title' => $material->title,
                    'description' => $material->description,
                    'category' => $material->category,
                    'meeting_number' => $material->meeting_number,
                    'is_published' => $material->is_published,
                    'uploaded_at' => $material->created_at->format('d M Y H:i'),
                    'course_code' => $material->courseOffering->course->code ?? '-',
                    'course_name' => $material->courseOffering->course->name ?? '-',
                    'course_offering_id' => $material->course_offering_id,
                    'is_bookmarked' => $isBookmarked,
                    'files' => $material->files->map(function ($file) {
                        return [
                            'id' => $file->id,
                            'file_name' => $file->file_name,
                            'file_type' => $file->file_type,
                            'file_size' => $file->file_size,
                            'file_path' => $file->file_path,
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
    
    public function toggleBookmark(int $materialId): void
    {
        $user = auth()->user();
        
        $bookmark = CourseMaterialBookmark::where('student_profile_id', $user->studentProfile->id)
            ->where('course_material_id', $materialId)
            ->first();
        
        if ($bookmark) {
            $bookmark->delete();
        } else {
            CourseMaterialBookmark::create([
                'student_profile_id' => $user->studentProfile->id,
                'course_material_id' => $materialId,
            ]);
        }
        
        $this->loadMaterials();
    }
    
    public function filterByCourse(?int $courseId): void
    {
        $this->selectedCourseId = $courseId;
        $this->loadMaterials();
    }
    
    public function toggleBookmarkedFilter(): void
    {
        $this->showBookmarkedOnly = !$this->showBookmarkedOnly;
        $this->loadMaterials();
    }
    
    public function updatedSearchQuery(): void
    {
        $this->loadMaterials();
    }
    
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'My Materials',
        ]);
    }
};
?>

<div class="container-xl py-4">
    {{-- Hero Section --}}
    <div class="modern-hero" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; padding: 2.5rem; margin-bottom: 2rem; position: relative; overflow: hidden;">
        <div style="position: absolute; top: -50%; right: -20%; width: 400px; height: 400px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
        <div style="position: absolute; bottom: -30%; left: -10%; width: 300px; height: 300px; background: rgba(255,255,255,0.05); border-radius: 50%;"></div>
        
        <div style="position: relative; z-index: 1;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-book-open" style="color: rgba(255,255,255,0.9); font-size: 1.5rem;"></i>
                        <span style="color: rgba(255,255,255,0.9); font-weight: 600; font-size: 0.9rem;">My Course Materials</span>
                    </div>
                    <h1 class="mb-2" style="color: white; font-weight: 800; font-size: 1.8rem;">
                        Semua Materi Perkuliahan
                    </h1>
                    <p style="color: rgba(255,255,255,0.9); font-size: 1rem; margin-top: 0.5rem;">
                        Akses semua materi dari course yang kamu enroll. Bookmark materi penting untuk akses cepat!
                    </p>
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
                        <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Bookmarked</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: #f59e0b; margin-top: 0.25rem;">{{ collect($materials)->where('is_bookmarked', true)->count() }}</div>
                    </div>
                    <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-bookmark" style="color: white; font-size: 1.3rem;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Courses</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: #10b981; margin-top: 0.25rem;">{{ count($courses) }}</div>
                    </div>
                    <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-chalkboard-teacher" style="color: white; font-size: 1.3rem;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;">With Files</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: #3b82f6; margin-top: 0.25rem;">{{ collect($materials)->filter(fn($m) => !empty($m['files']))->count() }}</div>
                    </div>
                    <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-file-alt" style="color: white; font-size: 1.3rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters & Search --}}
    <div class="modern-card p-4 mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" style="font-weight: 600; color: #1e293b;">
                    <i class="fas fa-filter me-2" style="color: #667eea;"></i>Filter by Course
                </label>
                <select 
                    class="form-select"
                    wire:change="filterByCourse($event.target.value)"
                    style="border-radius: 12px; border: 2px solid #e2e8f0;"
                >
                    <option value="">All Courses</option>
                    @foreach($courses as $course)
                        <option value="{{ $course['id'] }}" {{ $selectedCourseId == $course['id'] ? 'selected' : '' }}>
                            {{ $course['course_code'] }} - {{ $course['course_name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <div class="col-md-4">
                <label class="form-label" style="font-weight: 600; color: #1e293b;">
                    <i class="fas fa-search me-2" style="color: #667eea;"></i>Search
                </label>
                <input 
                    type="text" 
                    class="form-control"
                    wire:model.live="searchQuery"
                    placeholder="Cari materi..."
                    style="border-radius: 12px; border: 2px solid #e2e8f0;"
                >
            </div>
            
            <div class="col-md-4">
                <label class="form-label" style="font-weight: 600; color: #1e293b; visibility: hidden;">
                    Filter
                </label>
                <button 
                    type="button"
                    wire:click="toggleBookmarkedFilter"
                    class="btn w-100"
                    style="background: {{ $showBookmarkedOnly ? 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)' : '#f1f5f9' }}; color: {{ $showBookmarkedOnly ? 'white' : '#64748b' }}; border: 2px solid {{ $showBookmarkedOnly ? '#f59e0b' : '#e2e8f0' }}; border-radius: 12px; font-weight: 600;"
                >
                    <i class="fas fa-bookmark me-2"></i>{{ $showBookmarkedOnly ? 'Show All' : 'Bookmarked Only' }}
                </button>
            </div>
        </div>
    </div>

    {{-- Materials List --}}
    <div class="card modern-card">
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
                        <div class="col-md-9">
                            <div class="d-flex align-items-start gap-3">
                                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    @php
                                        $firstFileType = !empty($material['files']) ? $material['files'][0]['file_type'] ?? null : null;
                                        $icon = match($firstFileType) {
                                            'pdf' => 'fa-file-pdf',
                                            'ppt', 'pptx' => 'fa-file-powerpoint',
                                            'doc', 'docx' => 'fa-file-word',
                                            'mp4' => 'fa-file-video',
                                            'jpg', 'jpeg', 'png' => 'fa-file-image',
                                            default => 'fa-folder-open',
                                        };
                                    @endphp
                                    <i class="fas {{ $icon }}" style="color: white; font-size: 1.5rem;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <h5 class="mb-0" style="font-weight: 700; color: #1e293b;">{{ $material['title'] }}</h5>
                                        @if($material['is_bookmarked'])
                                            <span class="category-badge" style="background: #fef3c7; color: #d97706;">
                                                <i class="fas fa-bookmark"></i> Bookmarked
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mb-2" style="font-size: 0.9rem; color: #64748b;">
                                        <i class="fas fa-chalkboard me-1"></i>{{ $material['course_code'] }} - {{ $material['course_name'] }}
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
                                                    <i class="fas fa-file me-1"></i>{{ $file['file_name'] }} ({{ number_format($file['file_size'] / 1024, 2) }} KB)
                                                </span>
                                            @endforeach
                                        @endif
                                        <span>
                                            <i class="fas fa-clock me-1"></i>{{ $material['uploaded_at'] }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 text-md-end mt-3 mt-md-0">
                            <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                                <a 
                                    href="{{ route('student.learning.show', $material['id']) }}"
                                    class="action-btn"
                                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;"
                                >
                                    <i class="fas fa-eye"></i> Detail
                                </a>
                                <button 
                                    type="button"
                                    class="action-btn"
                                    wire:click="toggleBookmark({{ $material['id'] }})"
                                    style="background: {{ $material['is_bookmarked'] ? 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)' : 'linear-gradient(135deg, #64748b 0%, #475569 100%)' }}; color: white;"
                                >
                                    <i class="fas fa-{{ $material['is_bookmarked'] ? 'bookmark' : 'bookmark' }}"></i>
                                    {{ $material['is_bookmarked'] ? 'Unbookmark' : 'Bookmark' }}
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
                                                    <a 
                                                        href="{{ route('student.course-materials.download', ['id' => $material['id'], 'fileId' => $file['id']]) }}"
                                                        class="dropdown-item"
                                                    >
                                                        <i class="fas fa-file me-2"></i>{{ $file['file_name'] }}
                                                        <small class="text-muted">({{ number_format($file['file_size'] / 1024, 2) }} KB)</small>
                                                    </a>
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
                    <div class="mt-3" style="font-size: 1.1rem; color: #64748b; font-weight: 500;">
                        @if($showBookmarkedOnly)
                            Belum ada materi yang di-bookmark.
                        @else
                            Belum ada materi perkuliahan yang tersedia.
                        @endif
                    </div>
                    <div style="font-size: 0.9rem; color: #94a3b8; margin-top: 0.5rem;">
                        @if($showBookmarkedOnly)
                            Klik tombol "Bookmark" pada materi untuk menambahkannya ke sini.
                        @else
                            Materi akan muncul setelah dosen mengupload dan mempublikasikannya.
                        @endif
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>