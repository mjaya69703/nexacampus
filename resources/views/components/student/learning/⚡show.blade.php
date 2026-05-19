<?php

use App\Models\Academic\CourseMaterial;
use App\Models\Academic\CourseMaterialBookmark;
use App\Models\Academic\CourseMaterialDownload;
use App\Models\Academic\CourseMaterialFile;
use App\Models\Academic\MaterialLike;
use App\Models\Academic\StudyPlan;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new class extends Component
{
    public int $materialId;
    public array $materialData = [];
    public array $attachments = [];
    public bool $isBookmarked = false;
    public bool $isLiked = false;
    public int $likesCount = 0;

    public function mount(int|string $material): void
    {
        $this->materialId = (int) $material;
        $this->loadMaterial();
    }

    private function loadMaterial(): void
    {
        $material = CourseMaterial::query()
            ->with(['files', 'uploadedBy', 'courseOffering.course'])
            ->where('is_published', true)
            ->find($this->materialId);

        if (! $material) {
            abort(404, 'Materi tidak ditemukan');
        }

        if (! $this->canAccessMaterial($material)) {
            abort(403, 'Anda tidak memiliki akses ke materi ini');
        }

        $this->materialData = [
            'id' => $material->id,
            'course_offering_id' => $material->course_offering_id,
            'course_code' => $material->courseOffering?->course?->code ?? '-',
            'course_name' => $material->courseOffering?->course?->name ?? '-',
            'title' => $material->title,
            'description' => $material->description,
            'category' => $material->category,
            'meeting_number' => $material->meeting_number,
            'uploaded_at' => $material->created_at->format('d M Y'),
            'uploaded_by' => $material->uploadedBy?->name ?? '-',
            'download_count' => $material->files->count() > 0
                ? $material->files->sum('download_count')
                : $material->download_count,
        ];

        $this->attachments = $material->files
            ->map(function (CourseMaterialFile $file) {
                return [
                    'id' => $file->id,
                    'file_name' => $file->file_name,
                    'file_type' => $file->file_type,
                    'file_size' => $file->file_size,
                    'download_count' => $file->download_count,
                    'file_path' => $file->file_path,
                ];
            })
            ->values()
            ->all();

        if (empty($this->attachments) && $material->file_path) {
            $this->attachments[] = [
                'id' => null,
                'file_name' => $material->file_name ?? 'material',
                'file_type' => $material->file_type,
                'file_size' => $material->file_size,
                'download_count' => $material->download_count,
                'file_path' => $material->file_path,
            ];
        }

        $this->loadBookmarkState();
        $this->loadLikeState();
    }

    private function loadBookmarkState(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->hasRole('student')) {
            $this->isBookmarked = false;

            return;
        }

        $this->isBookmarked = CourseMaterialBookmark::query()
            ->where('course_material_id', $this->materialId)
            ->where('student_profile_id', $user->studentProfile?->id)
            ->exists();
    }
    
    private function loadLikeState(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->hasRole('student')) {
            $this->isLiked = false;
            $this->likesCount = 0;

            return;
        }

        $material = CourseMaterial::find($this->materialId);
        $this->likesCount = $material->likes_count ?? 0;
        $this->isLiked = MaterialLike::where('course_material_id', $this->materialId)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function toggleBookmark(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->hasRole('student')) {
            return;
        }

        $bookmark = CourseMaterialBookmark::query()
            ->where('course_material_id', $this->materialId)
            ->where('student_profile_id', $user->studentProfile->id)
            ->first();

        if ($bookmark) {
            $bookmark->delete();
            $this->isBookmarked = false;

            session()->flash('success', 'Bookmark dihapus.');

            return;
        }

        CourseMaterialBookmark::create([
            'course_material_id' => $this->materialId,
            'student_profile_id' => $user->studentProfile->id,
        ]);

        $this->isBookmarked = true;

        session()->flash('success', 'Materi ditambahkan ke bookmark.');
    }
    
    public function toggleMaterialLike(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->hasRole('student')) {
            session()->flash('error', 'Anda harus login sebagai mahasiswa.');
            return;
        }

        $existingLike = MaterialLike::where('course_material_id', $this->materialId)
            ->where('user_id', $user->id)
            ->first();

        if ($existingLike) {
            // Unlike
            $existingLike->delete();
            $this->isLiked = false;
            
            // Decrement likes count
            $material = CourseMaterial::find($this->materialId);
            if ($material) {
                $material->decrement('likes_count');
                $this->likesCount = $material->likes_count;
            }
            
            session()->flash('success', 'Like dihapus.');
        } else {
            // Like
            MaterialLike::create([
                'course_material_id' => $this->materialId,
                'user_id' => $user->id,
            ]);
            $this->isLiked = true;
            
            // Increment likes count
            $material = CourseMaterial::find($this->materialId);
            if ($material) {
                $material->increment('likes_count');
                $this->likesCount = $material->likes_count;
            }
            
            session()->flash('success', 'Materi disukai!');
        }
    }

    public function downloadAttachment(int $materialId, ?int $fileId = null): ?BinaryFileResponse
    {
        $material = CourseMaterial::query()
            ->with('files')
            ->where('is_published', true)
            ->find($materialId);

        if (! $material) {
            session()->flash('error', 'Materi tidak ditemukan atau belum dipublikasikan!');

            return null;
        }

        if (! $this->canAccessMaterial($material)) {
            session()->flash('error', 'Anda tidak memiliki akses ke materi ini!');

            return null;
        }

        if ($fileId) {
            $file = $material->files->firstWhere('id', $fileId)
                ?? CourseMaterialFile::query()
                    ->where('course_material_id', $material->id)
                    ->find($fileId);

            if (! $file) {
                session()->flash('error', 'File tidak ditemukan!');

                return null;
            }

            if (! Storage::disk('public')->exists($file->file_path)) {
                session()->flash('error', 'File tidak ditemukan di server!');

                return null;
            }

            $this->trackDownload($material);
            $file->increment('download_count');

            return response()->download(
                Storage::disk('public')->path($file->file_path),
                $file->file_name
            );
        }

        if (! $material->file_path) {
            session()->flash('error', 'File tidak tersedia untuk materi ini!');

            return null;
        }

        if (! Storage::disk('public')->exists($material->file_path)) {
            session()->flash('error', 'File tidak ditemukan di server!');

            return null;
        }

        $this->trackDownload($material);
        $material->increment('download_count');

        return response()->download(
            Storage::disk('public')->path($material->file_path),
            $material->file_name ?? 'material'
        );
    }

    private function canAccessMaterial(CourseMaterial $material): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $studentProfileId = $user->studentProfile?->id;
        $isEnrolled = $studentProfileId
            ? StudyPlan::query()
                ->where('student_profile_id', $studentProfileId)
                ->whereHas('details', function ($query) use ($material) {
                    $query->where('course_offering_id', $material->course_offering_id);
                })
                ->exists()
            : false;

        return $isEnrolled || $user->hasRole('lecturer');
    }

    private function trackDownload(CourseMaterial $material): void
    {
        $user = auth()->user();
        $studentProfileId = $user?->studentProfile?->id;

        if (! $user || ! $studentProfileId) {
            return;
        }

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

        $material->update(['last_accessed_at' => now()]);
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

        return round($bytes, 2).' '.$units[$i];
    }

    public function getVideoEmbedUrl(string $url): ?string
    {
        $normalized = trim($url);

        if ($normalized === '') {
            return null;
        }

        if (preg_match('~(youtu\.be/|youtube\.com/watch\?v=|youtube\.com/embed/)([A-Za-z0-9_-]{6,})~', $normalized, $matches)) {
            return 'https://www.youtube.com/embed/'.$matches[2];
        }

        return null;
    }

    public function render(): View
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Learning',
        ]);
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
            color: white;
        }

        .hero-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 16s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.85; }
        }

        .hero-icon {
            width: 64px;
            height: 64px;
            background: rgba(255,255,255,0.2);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.5rem 0.85rem;
            border-radius: 999px;
            background: rgba(255,255,255,0.2);
            color: white;
            font-weight: 700;
            font-size: 0.85rem;
            backdrop-filter: blur(10px);
        }

        .hero-meta-panel {
            min-width: 250px;
            padding: 1rem;
            border-radius: 16px;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.22);
            backdrop-filter: blur(10px);
        }

        .hero-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.65rem;
        }

        .hero-actions .action-btn {
            justify-content: center;
            width: 100%;
        }

        .attachment-card {
            border-radius: 14px;
            border: 2px solid #e2e8f0;
            padding: 1rem 1.25rem;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .attachment-card:hover {
            border-color: #6366f1;
            background: #eef2ff;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(99, 102, 241, 0.12);
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
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card modern-card hero-gradient mb-4" style="color: white;">
        <div class="card-body p-4 p-lg-5 position-relative">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="hero-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div>
                            <div class="text-uppercase" style="letter-spacing: 0.08em; font-size: 0.8rem; opacity: 0.85;">Learning</div>
                            <h2 class="h2 mt-2 mb-2" style="font-weight: 700;">{{ $materialData['title'] }}</h2>
                            <div style="font-size: 1.05rem; opacity: 0.95;">{{ $materialData['course_code'] }} - {{ $materialData['course_name'] }}</div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if($materialData['meeting_number'])
                            <span class="hero-badge">
                                <i class="fas fa-calendar-day"></i>
                                Pertemuan #{{ $materialData['meeting_number'] }}
                            </span>
                        @endif
                        <span class="hero-badge">
                            <i class="fas fa-layer-group"></i>
                            {{ ucfirst(str_replace('_', ' ', $materialData['category'])) }}
                        </span>
                        <span class="hero-badge">
                            <i class="fas fa-paperclip"></i>
                            {{ count($attachments) }} Lampiran
                        </span>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="hero-meta-panel ms-lg-auto">
                        <div style="font-size: 0.8rem; opacity: 0.85; font-weight: 600;">Diupload oleh</div>
                        <div style="font-weight: 700; font-size: 1.05rem;">{{ $materialData['uploaded_by'] }}</div>
                        <div class="mb-3" style="font-size: 0.85rem; opacity: 0.85;">{{ $materialData['uploaded_at'] }}</div>
                        <div class="hero-actions">
                        <a
                            href="{{ route('student.course-materials.index', ['offeringId' => $materialData['course_offering_id']]) }}"
                            class="action-btn"
                            style="background: rgba(255,255,255,0.18); color: white; text-decoration: none;"
                        >
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                        <button
                            type="button"
                            class="action-btn"
                            wire:click="toggleMaterialLike"
                            style="background: {{ $isLiked ? 'rgba(239, 68, 68, 0.9)' : 'rgba(255,255,255,0.18)' }}; color: {{ $isLiked ? 'white' : 'white' }};"
                        >
                            <i class="fas fa-heart {{ $isLiked ? 'fa-solid' : 'fa-regular' }}"></i>
                            {{ $likesCount }} {{ $isLiked ? 'Disukai' : 'Suka' }}
                        </button>
                        <button
                            type="button"
                            class="action-btn"
                            wire:click="toggleBookmark"
                            style="background: {{ $isBookmarked ? 'rgba(251, 191, 36, 0.9)' : 'rgba(255,255,255,0.18)' }}; color: {{ $isBookmarked ? '#1e293b' : 'white' }};"
                        >
                            <i class="fas fa-star"></i> {{ $isBookmarked ? 'Tersimpan' : 'Bookmark' }}
                        </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card modern-card mb-4">
        <div class="card-body p-4">
            <h4 class="mb-3" style="font-weight: 700;">Deskripsi</h4>
            <div style="color: #475569; line-height: 1.6;">
                {!! $materialData['description'] ?: 'Tidak ada deskripsi untuk materi ini.' !!}
            </div>
        </div>
    </div>

    <div class="card modern-card">
        <div class="card-header" style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
            <h4 class="mb-0" style="font-weight: 700;">Lampiran</h4>
        </div>
        <div class="card-body p-4">
            @forelse($attachments as $attachment)
                <div class="attachment-card d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                    <div>
                        <div style="font-weight: 600; color: #1e293b;">
                            <i class="fas fa-paperclip me-2"></i>{{ $attachment['file_name'] }}
                        </div>
                        <div class="d-flex flex-wrap gap-3 mt-2" style="font-size: 0.85rem; color: #64748b;">
                            <span><i class="fas fa-file-alt me-1"></i>{{ strtoupper($attachment['file_type'] ?? '-') }}</span>
                            @if(($attachment['file_type'] ?? '') === 'link')
                                <span><i class="fas fa-link me-1"></i>Link</span>
                            @else
                                <span><i class="fas fa-hdd me-1"></i>{{ $this->formatFileSize($attachment['file_size']) }}</span>
                            @endif
                            <span><i class="fas fa-download me-1"></i>{{ $attachment['download_count'] }}x</span>
                        </div>
                    </div>
                    <div>
                        @if(($attachment['file_type'] ?? '') === 'link')
                            <a
                                href="{{ route('student.learning.link', ['material' => $materialData['id'], 'fileId' => $attachment['id']]) }}"
                                class="action-btn"
                                target="_blank"
                                style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; text-decoration: none;"
                            >
                                <i class="fas fa-link"></i> Buka Link
                            </a>
                        @else
                            @if($attachment['id'])
                                <button
                                    type="button"
                                    class="action-btn"
                                    wire:click="downloadAttachment({{ $materialData['id'] }}, {{ $attachment['id'] }})"
                                    style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;"
                                >
                                    <i class="fas fa-download"></i> Download
                                </button>
                            @else
                                <button
                                    type="button"
                                    class="action-btn"
                                    wire:click="downloadAttachment({{ $materialData['id'] }})"
                                    style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;"
                                >
                                    <i class="fas fa-download"></i> Download
                                </button>
                            @endif
                        @endif
                    </div>
                </div>

                @if(($attachment['file_type'] ?? '') === 'link')
                    @php
                        $embedUrl = $this->getVideoEmbedUrl($attachment['file_path'] ?? '');
                    @endphp
                    @if($embedUrl)
                        <div class="mb-4" style="border-radius: 16px; overflow: hidden; border: 2px solid #e2e8f0;">
                            <div class="ratio ratio-16x9">
                                <iframe
                                    src="{{ $embedUrl }}"
                                    title="{{ $attachment['file_name'] }}"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                ></iframe>
                            </div>
                        </div>
                    @endif
                @elseif(($attachment['file_type'] ?? '') === 'pdf')
                    <div class="mb-4" style="border-radius: 16px; overflow: hidden; border: 2px solid #e2e8f0;">
                        <iframe
                            src="{{ $attachment['id'] ? route('student.learning.preview', ['material' => $materialData['id'], 'fileId' => $attachment['id']]) : route('student.learning.preview', ['material' => $materialData['id']]) }}"
                            style="width: 100%; height: 520px; border: 0;"
                            loading="lazy"
                        ></iframe>
                    </div>
                @endif
            @empty
                <div class="text-center py-4" style="color: #94a3b8;">
                    <i class="fas fa-folder-open" style="font-size: 2.5rem;"></i>
                    <div class="mt-2">Belum ada lampiran untuk materi ini.</div>
                </div>
            @endforelse
        </div>
    </div>
    
    {{-- Comments/Discussion Section --}}
    <div class="mt-5">
        <livewire:student.course-material-comments :material-id="$materialId" />
    </div>
</div>
