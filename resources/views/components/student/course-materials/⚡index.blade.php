<?php

use App\Models\Academic\CourseMaterial;
use App\Models\Academic\CourseMaterialDownload;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\StudyPlan;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new class extends Component
{
    public ?int $courseOfferingId = null;
    public array $offeringInfo = [];
    public array $materials = [];
    public array $meetingNumbers = [];
    public string $searchQuery = '';
    public string $selectedCategory = 'all';
    public ?int $selectedMeetingNumber = null;

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
        $this->loadMeetingNumbers();
    }

    private function loadMaterials(): void
    {
        $query = CourseMaterial::query()
            ->with(['uploadedBy', 'files'])
            ->where('course_offering_id', $this->courseOfferingId)
            ->where('is_published', true);

        // Apply search filter
        if ($this->searchQuery) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('description', 'like', '%' . $this->searchQuery . '%');
            });
        }

        // Apply category filter
        if ($this->selectedCategory !== 'all') {
            $query->where('category', $this->selectedCategory);
        }

        // Apply meeting number filter
        if ($this->selectedMeetingNumber) {
            $query->where('meeting_number', $this->selectedMeetingNumber);
        }

        $materials = $query->orderByDesc('meeting_number')
            ->orderByDesc('created_at')
            ->get();

        $this->materials = $materials
            ->map(function (CourseMaterial $material) {
                $filesCount = $material->files->count();
                $primaryFileType = $filesCount > 0
                    ? $material->files->first()?->file_type
                    : $material->file_type;

                return [
                    'id' => $material->id,
                    'title' => $material->title,
                    'description' => $material->description,
                    'category' => $material->category,
                    'meeting_number' => $material->meeting_number,
                    'file_name' => $material->file_name,
                    'file_type' => $primaryFileType,
                    'file_size' => $filesCount > 0 ? $material->files->sum('file_size') : $material->file_size,
                    'download_count' => $filesCount > 0 ? $material->files->sum('download_count') : $material->download_count,
                    'files_count' => $filesCount,
                    'uploaded_at' => $material->created_at->format('d M Y'),
                    'uploaded_by' => $material->uploadedBy?->name ?? '-',
                ];
            })
            ->values()
            ->all();
    }

    private function loadMeetingNumbers(): void
    {
        $this->meetingNumbers = CourseMaterial::query()
            ->where('course_offering_id', $this->courseOfferingId)
            ->where('is_published', true)
            ->whereNotNull('meeting_number')
            ->distinct()
            ->pluck('meeting_number')
            ->sort()
            ->values()
            ->all();
    }

    public function applySearch(string $query): void
    {
        $this->searchQuery = $query;
        $this->loadMaterials();
    }

    public function applyCategoryFilter(string $category): void
    {
        $this->selectedCategory = $category;
        $this->loadMaterials();
    }

    public function applyMeetingFilter(?int $meetingNumber): void
    {
        $this->selectedMeetingNumber = $meetingNumber;
        $this->loadMaterials();
    }

    public function clearFilters(): void
    {
        $this->searchQuery = '';
        $this->selectedCategory = 'all';
        $this->selectedMeetingNumber = null;
        $this->loadMaterials();
    }

    public function download(int $materialId): ?BinaryFileResponse
    {
        $material = CourseMaterial::query()
            ->where('is_published', true)
            ->find($materialId);

        if (! $material) {
            session()->flash('error', 'Materi tidak ditemukan atau belum dipublikasikan!');

            return null;
        }

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

            return null;
        }

        if (! $material->file_path) {
            session()->flash('error', 'File tidak tersedia untuk materi ini!');

            return null;
        }

        CourseMaterialDownload::updateOrCreate(
            [
                'course_material_id' => $material->id,
                'student_id' => $user->id,
            ],
            [
                'downloaded_at' => now(),
                'ip_address' => request()->ip(),
            ]
        );

        $material->increment('download_count');
        $material->update(['last_accessed_at' => now()]);

        if (! Storage::disk('public')->exists($material->file_path)) {
            session()->flash('error', 'File tidak ditemukan di server!');

            return null;
        }

        return response()->download(
            Storage::disk('public')->path($material->file_path),
            $material->file_name ?? 'material'
        );
    }

    public function render(): View
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Course Materials',
        ]);
    }

    public function categoryBadgeClass(string $category): string
    {
        return match ($category) {
            'syllabus' => 'bg-primary-lt text-primary',
            'lecture_notes' => 'bg-info-lt text-info',
            'assignments' => 'bg-warning-lt text-warning',
            'references' => 'bg-secondary-lt text-secondary',
            default => 'bg-light-lt text-secondary',
        };
    }

    public function formatFileSize(?int $bytes): string
    {
        if (! $bytes) {
            return '-';
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
};
?>

@push('styles')
    <style>
        .modern-card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            background: white;
        }

        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }

        .hero-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 15s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .filter-btn {
            padding: 8px 16px;
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            background: white;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            border-color: #667eea;
            background: #f8fafc;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }

        .material-card {
            border-radius: 16px;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .material-card:hover {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-color: #667eea;
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
        }

        .action-btn {
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
    </style>
@endpush

<div>
    <x-alert />

    {{-- Hero Section --}}
    <div class="card modern-card hero-gradient mb-4" style="color: white;">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div style="width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Materi Perkuliahan</div>
                            <h2 class="h2 mb-0" style="font-weight: 700;">{{ $offeringInfo['course_code'] }} - {{ $offeringInfo['course_name'] }}</h2>
                            <div style="font-size: 1.1rem; opacity: 0.95; margin-top: 4px;">Kelas {{ $offeringInfo['label'] }}</div>
                        </div>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="info-badge">
                            <i class="fas fa-calendar-alt"></i>
                            {{ $offeringInfo['academic_year'] }}
                        </span>
                        <span class="info-badge">
                            <i class="fas fa-layer-group"></i>
                            Semester {{ $offeringInfo['semester_no'] }}
                        </span>
                        @if($offeringInfo['building'] !== '-' || $offeringInfo['room'] !== '-')
                            <span class="info-badge">
                                <i class="fas fa-building"></i>
                                {{ $offeringInfo['building'] }} / {{ $offeringInfo['room'] }}
                            </span>
                        @endif
                        @if($offeringInfo['delivery_mode'] !== '-')
                            <span class="info-badge">
                                <i class="fas fa-wifi"></i>
                                {{ $offeringInfo['delivery_mode'] }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="col-lg-4 text-lg-end">
                    <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem;">
                        <div style="font-size: 0.85rem; opacity: 0.9; margin-bottom: 0.5rem;">Total Materi</div>
                        <div style="font-size: 2rem; font-weight: 700;">{{ count($materials) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters Section --}}
    <div class="card modern-card mb-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" style="font-weight: 600; color: #1e293b;">
                        <i class="fas fa-search me-2" style="color: #667eea;"></i>Cari Materi
                    </label>
                    <input 
                        type="text" 
                        class="form-control form-control-lg"
                        wire:model.live.debounce.300ms="searchQuery"
                        placeholder="Cari berdasarkan judul atau deskripsi..."
                        style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 12px 16px;"
                    >
                </div>
                
                <div class="col-md-4">
                    <label class="form-label" style="font-weight: 600; color: #1e293b;">
                        <i class="fas fa-tag me-2" style="color: #667eea;"></i>Kategori
                    </label>
                    <div class="d-flex flex-wrap gap-2">
                        <button 
                            wire:click="applyCategoryFilter('all')"
                            class="filter-btn {{ $selectedCategory === 'all' ? 'active' : '' }}"
                        >
                            Semua
                        </button>
                        <button 
                            wire:click="applyCategoryFilter('syllabus')"
                            class="filter-btn {{ $selectedCategory === 'syllabus' ? 'active' : '' }}"
                        >
                            📋 Silabus
                        </button>
                        <button 
                            wire:click="applyCategoryFilter('lecture_notes')"
                            class="filter-btn {{ $selectedCategory === 'lecture_notes' ? 'active' : '' }}"
                        >
                            📝 Catatan
                        </button>
                        <button 
                            wire:click="applyCategoryFilter('assignments')"
                            class="filter-btn {{ $selectedCategory === 'assignments' ? 'active' : '' }}"
                        >
                            ✍️ Tugas
                        </button>
                        <button 
                            wire:click="applyCategoryFilter('references')"
                            class="filter-btn {{ $selectedCategory === 'references' ? 'active' : '' }}"
                        >
                            📚 Referensi
                        </button>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label" style="font-weight: 600; color: #1e293b;">
                        <i class="fas fa-hashtag me-2" style="color: #667eea;"></i>Pertemuan
                    </label>
                    <div class="d-flex flex-wrap gap-2">
                        <button 
                            wire:click="applyMeetingFilter(null)"
                            class="filter-btn {{ !$selectedMeetingNumber ? 'active' : '' }}"
                        >
                            Semua
                        </button>
                        @foreach($meetingNumbers as $number)
                            <button 
                                wire:click="applyMeetingFilter({{ $number }})"
                                class="filter-btn {{ $selectedMeetingNumber === $number ? 'active' : '' }}"
                            >
                                #{{ $number }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
            
            @if($searchQuery || $selectedCategory !== 'all' || $selectedMeetingNumber)
                <div class="mt-3 pt-3" style="border-top: 2px solid #e2e8f0;">
                    <button 
                        wire:click="clearFilters"
                        class="btn btn-outline-secondary"
                        style="border-radius: 10px; font-weight: 600;"
                    >
                        <i class="fas fa-times me-2"></i>Clear Filters
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Materials List --}}
    <div class="card modern-card">
        <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-list-check" style="font-size: 1.3rem; color: #667eea;"></i>
                <h3 class="card-title mb-0" style="font-weight: 700;">Daftar Materi</h3>
            </div>
            <span class="badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 16px; border-radius: 8px; font-size: 0.9rem;">
                <i class="fas fa-layer-group me-1"></i>{{ number_format(count($materials)) }} materi ditemukan
            </span>
        </div>

        <div class="card-body p-4">
            @forelse ($materials as $material)
                <div class="material-card">
                    <div class="row align-items-center g-3">
                        <div class="col-md-1">
                            <div style="width: 56px; height: 56px; border-radius: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                                <i class="fas fa-file-{{ $material['file_type'] === 'pdf' ? 'pdf' : ($material['file_type'] === 'ppt' || $material['file_type'] === 'pptx' ? 'powerpoint' : ($material['file_type'] === 'doc' || $material['file_type'] === 'docx' ? 'word' : 'alt')) }}"></i>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div style="font-weight: 700; font-size: 1.05rem; color: #1e293b; margin-bottom: 6px;">
                                {{ $material['title'] }}
                            </div>
                            @if($material['description'])
                                <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 6px;">
                                    {{ Str::limit($material['description'], 120) }}
                                </div>
                            @endif
                            <div class="d-flex flex-wrap gap-2">
                                @if($material['meeting_number'])
                                    <span class="badge bg-blue-lt text-blue" style="padding: 6px 12px; border-radius: 8px; font-size: 0.8rem;">
                                        <i class="fas fa-hashtag me-1"></i>Pertemuan #{{ $material['meeting_number'] }}
                                    </span>
                                @endif
                                <span class="badge {{ $this->categoryBadgeClass($material['category']) }}" style="padding: 6px 12px; border-radius: 8px; font-size: 0.8rem;">
                                    {{ ucfirst(str_replace('_', ' ', $material['category'])) }}
                                </span>
                                <span style="font-size: 0.8rem; color: #64748b;">
                                    <i class="fas fa-hdd me-1"></i>{{ $this->formatFileSize($material['file_size']) }}
                                </span>
                                @if($material['files_count'] > 0)
                                    <span style="font-size: 0.8rem; color: #64748b;">
                                        <i class="fas fa-paperclip me-1"></i>{{ $material['files_count'] }} Lampiran
                                    </span>
                                @endif
                                <span style="font-size: 0.8rem; color: #64748b;">
                                    <i class="fas fa-download me-1"></i>{{ $material['download_count'] }}x
                                </span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 6px;">Info Upload</div>
                            <div style="font-size: 0.85rem; color: #1e293b;">
                                <i class="fas fa-user me-1"></i>{{ $material['uploaded_by'] }}
                            </div>
                            <div style="font-size: 0.8rem; color: #64748b; margin-top: 4px;">
                                <i class="fas fa-clock me-1"></i>{{ $material['uploaded_at'] }}
                            </div>
                        </div>
                        <div class="col-md-2 text-end">
                            <a
                                href="{{ route('student.learning.show', ['material' => $material['id']]) }}"
                                class="action-btn w-100 justify-content-center"
                                style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; text-decoration: none;"
                            >
                                <i class="fas fa-eye"></i> Lihat Materi
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-5">
                    <i class="fas fa-inbox" style="font-size: 4rem; color: #cbd5e1;"></i>
                    <div class="mt-3" style="font-size: 1.1rem; color: #64748b; font-weight: 500;">
                        @if($searchQuery || $selectedCategory !== 'all' || $selectedMeetingNumber)
                            Tidak ada materi yang sesuai dengan filter.
                        @else
                            Belum ada materi perkuliahan yang dipublikasikan.
                        @endif
                    </div>
                    @if($searchQuery || $selectedCategory !== 'all' || $selectedMeetingNumber)
                        <button 
                            wire:click="clearFilters"
                            class="btn btn-outline-primary mt-3"
                            style="border-radius: 10px; font-weight: 600;"
                        >
                            <i class="fas fa-times me-2"></i>Reset Filter
                        </button>
                    @endif
                </div>
            @endforelse
        </div>
    </div>
</div>
