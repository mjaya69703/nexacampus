<?php

use App\Models\Academic\CourseMaterial;
use App\Models\Academic\CourseMaterialBookmark;
use App\Models\Academic\CourseMaterialDownload;
use App\Models\Academic\CourseMaterialFile;
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
            ->where('student_profile_id', $user->studentProfile->id)
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

        if (! $user) {
            return;
        }

        CourseMaterialDownload::updateOrCreate(
            [
                'course_material_id' => $material->id,
                'student_profile_id' => $user->studentProfile->id,
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
            background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 100%);
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
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
            animation: pulse 18s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.4; }
            50% { transform: scale(1.08); opacity: 0.8; }
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

    <div class="card modern-card hero-gradient mb-4">
        <div class="card-body p-4 p-lg-5 position-relative">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-4">
                <div>
                    <div class="text-uppercase" style="letter-spacing: 0.08em; font-size: 0.8rem; opacity: 0.85;">Learning</div>
                    <h2 class="h2 mt-2 mb-2" style="font-weight: 700;">{{ $materialData['title'] }}</h2>
                    <div style="font-size: 1rem; opacity: 0.95;">{{ $materialData['course_code'] }} - {{ $materialData['course_name'] }}</div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        @if($materialData['meeting_number'])
                            <span class="badge bg-light text-dark" style="padding: 6px 12px; border-radius: 8px;">
                                Pertemuan #{{ $materialData['meeting_number'] }}
                            </span>
                        @endif
                        <span class="badge {{ $this->categoryBadgeClass($materialData['category']) }}" style="padding: 6px 12px; border-radius: 8px;">
                            {{ ucfirst(str_replace('_', ' ', $materialData['category'])) }}
                        </span>
                        <span class="badge bg-light text-dark" style="padding: 6px 12px; border-radius: 8px;">
                            {{ count($attachments) }} Lampiran
                        </span>
                    </div>
                </div>
                <div class="text-lg-end" style="min-width: 220px;">
                    <div style="font-size: 0.85rem; opacity: 0.9;">Diupload oleh</div>
                    <div style="font-weight: 600;">{{ $materialData['uploaded_by'] }}</div>
                    <div style="font-size: 0.85rem; opacity: 0.85;">{{ $materialData['uploaded_at'] }}</div>
                    <div class="d-flex flex-column gap-2 mt-3">
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

    <div class="card modern-card mb-4">
        <div class="card-body p-4">
            <h4 class="mb-3" style="font-weight: 700;">Deskripsi</h4>
            <div style="color: #475569; line-height: 1.6;">
                {{ $materialData['description'] ?: 'Tidak ada deskripsi untuk materi ini.' }}
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
</div>
