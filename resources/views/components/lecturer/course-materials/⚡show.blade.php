<?php

use App\Models\Academic\CourseMaterial;
use App\Models\Academic\CourseMaterialFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public int $materialId;
    public array $materialData = [];
    public array $attachments = [];

    public string $title = '';
    public string $description = '';
    public string $category = 'lecture_notes';
    public ?int $meetingNumber = null;
    public bool $isPublished = true;
    public $files = [];
    public string $videoUrl = '';
    public string $videoTitle = '';
    public bool $showEditPanel = false;

    public function mount(int|string $material): void
    {
        $this->materialId = (int) $material;
        $this->loadMaterial();
    }

    private function loadMaterial(): void
    {
        $material = CourseMaterial::query()
            ->with(['files', 'uploadedBy', 'courseOffering.course'])
            ->find($this->materialId);

        if (! $material) {
            abort(404, 'Materi tidak ditemukan');
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
            'has_legacy_file' => (bool) $material->file_path,
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

        $this->fillForm($material);
    }

    private function fillForm(CourseMaterial $material): void
    {
        $this->title = $material->title;
        $this->description = $material->description ?? '';
        $this->category = $material->category;
        $this->meetingNumber = $material->meeting_number;
        $this->isPublished = $material->is_published;
        $this->videoUrl = '';
        $this->videoTitle = '';
    }

    public function toggleEditPanel(): void
    {
        $this->showEditPanel = ! $this->showEditPanel;
    }

    public function updateMaterial(): void
    {
        $validated = $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:syllabus,lecture_notes,assignments,references',
            'meetingNumber' => 'nullable|integer|min:1|max:20',
            'isPublished' => 'boolean',
            'files.*' => 'nullable|file|max:51200|mimes:pdf,ppt,pptx,doc,docx,mp4,jpg,jpeg,png',
            'videoUrl' => 'nullable|url|max:255',
            'videoTitle' => 'nullable|string|max:255',
        ]);

        $material = CourseMaterial::find($this->materialId);

        if (! $material) {
            session()->flash('error', 'Materi tidak ditemukan.');

            return;
        }

        $material->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'meeting_number' => $validated['meetingNumber'],
            'is_published' => $validated['isPublished'],
            'updated_by' => auth()->id(),
        ]);

        if (! empty($this->files)) {
            foreach ($this->files as $file) {
                if (! $file) {
                    continue;
                }

                $filePath = $file->store('course-materials', 'public');
                $fileSize = $file->getSize();

                $material->files()->create([
                    'file_path' => $filePath,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getClientOriginalExtension(),
                    'file_size' => $fileSize,
                ]);
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

        $this->files = [];
        $this->videoUrl = '';
        $this->videoTitle = '';
        $this->loadMaterial();
        $this->showEditPanel = true;

        session()->flash('success', 'Materi berhasil diperbarui.');
    }

    public function deleteAttachment(int $fileId): void
    {
        $file = CourseMaterialFile::query()
            ->where('course_material_id', $this->materialId)
            ->find($fileId);

        if (! $file) {
            session()->flash('error', 'File tidak ditemukan.');

            return;
        }

        if ($file->file_path && Storage::disk('public')->exists($file->file_path)) {
            Storage::disk('public')->delete($file->file_path);
        }

        $file->delete();
        $this->loadMaterial();

        session()->flash('success', 'File berhasil dihapus.');
    }

    public function deleteLegacyFile(): void
    {
        $material = CourseMaterial::find($this->materialId);

        if (! $material) {
            session()->flash('error', 'Materi tidak ditemukan.');

            return;
        }

        if ($material->file_path && Storage::disk('public')->exists($material->file_path)) {
            Storage::disk('public')->delete($material->file_path);
        }

        $material->update([
            'file_path' => null,
            'file_name' => null,
            'file_type' => null,
            'file_size' => null,
        ]);

        $this->loadMaterial();

        session()->flash('success', 'File utama berhasil dihapus.');
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

    public function render(): View
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Course Material Detail',
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
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
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
            animation: pulse 18s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.4; }
            50% { transform: scale(1.06); opacity: 0.8; }
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
            text-decoration: none;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }

        .edit-panel {
            border: 2px dashed #cbd5f5;
            border-radius: 18px;
            background: #f8fafc;
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card modern-card hero-gradient mb-4">
        <div class="card-body p-4 p-lg-5 position-relative">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-4">
                <div>
                    <div class="text-uppercase" style="letter-spacing: 0.08em; font-size: 0.8rem; opacity: 0.85;">Materi Perkuliahan</div>
                    <h2 class="h2 mt-2 mb-2" style="font-weight: 700;">{{ $materialData['title'] ?? '-' }}</h2>
                    <div style="font-size: 1rem; opacity: 0.95;">{{ $materialData['course_code'] ?? '-' }} - {{ $materialData['course_name'] ?? '-' }}</div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        @if($materialData['meeting_number'] ?? null)
                            <span class="badge bg-light text-dark" style="padding: 6px 12px; border-radius: 8px;">
                                Pertemuan #{{ $materialData['meeting_number'] }}
                            </span>
                        @endif
                        <span class="badge bg-light text-dark" style="padding: 6px 12px; border-radius: 8px;">
                            {{ ucfirst(str_replace('_', ' ', $materialData['category'] ?? '-')) }}
                        </span>
                        <span class="badge bg-light text-dark" style="padding: 6px 12px; border-radius: 8px;">
                            {{ count($attachments) }} Lampiran
                        </span>
                    </div>
                </div>
                <div class="text-lg-end" style="min-width: 220px;">
                    <div style="font-size: 0.85rem; opacity: 0.9;">Diupload oleh</div>
                    <div style="font-weight: 600;">{{ $materialData['uploaded_by'] ?? '-' }}</div>
                    <div style="font-size: 0.85rem; opacity: 0.85;">{{ $materialData['uploaded_at'] ?? '-' }}</div>
                    <div class="d-flex flex-column gap-2 mt-3">
                        <a
                            href="{{ route('lecturer.course-materials.index', ['offeringId' => $materialData['course_offering_id'] ?? 0]) }}"
                            class="action-btn"
                            style="background: rgba(255,255,255,0.18); color: white;"
                        >
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                        <button
                            type="button"
                            class="action-btn"
                            wire:click="toggleEditPanel"
                            style="background: rgba(255,255,255,0.18); color: white;"
                        >
                            <i class="fas fa-pen"></i> {{ $showEditPanel ? 'Tutup Edit' : 'Edit Materi' }}
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
                {!! $materialData['description'] ?: 'Tidak ada deskripsi untuk materi ini.' !!}
            </div>
        </div>
    </div>

    @if($showEditPanel)
        <div class="card modern-card mb-4 edit-panel">
            <div class="card-body p-4">
                <h4 class="mb-3" style="font-weight: 700;">Edit Materi</h4>
                <form wire:submit.prevent="updateMaterial">
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #1e293b;">Judul Materi</label>
                        <input
                            type="text"
                            class="form-control form-control-lg"
                            wire:model="title"
                            style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 12px 16px;"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #1e293b;">Konten / Deskripsi</label>
                        <livewire:jodit-text-editor
                            wire:model.live="description"
                            identifier="lecturer-material-{{ $materialData['id'] ?? 'material' }}"
                            :height="300"
                        />
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight: 600; color: #1e293b;">Kategori</label>
                            <select class="form-select form-select-lg" wire:model="category" style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 12px 16px;">
                                <option value="syllabus">Silabus/RPS</option>
                                <option value="lecture_notes">Catatan Kuliah</option>
                                <option value="assignments">Tugas</option>
                                <option value="references">Referensi</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight: 600; color: #1e293b;">Pertemuan (Opsional)</label>
                            <input
                                type="number"
                                class="form-control form-control-lg"
                                wire:model="meetingNumber"
                                min="1"
                                max="20"
                                placeholder="No. pertemuan"
                                style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 12px 16px;"
                            >
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #1e293b;">Tambah Lampiran</label>
                        <input
                            type="file"
                            class="form-control form-control-lg"
                            wire:model="files"
                            multiple
                            accept=".pdf,.ppt,.pptx,.doc,.docx,.mp4,.jpg,.jpeg,.png"
                            style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 12px 16px;"
                        >
                        <small class="text-muted d-block mt-2">Format: PDF, PPT, PPTX, DOC, DOCX, MP4, JPG, PNG. Maks 50MB per file.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #1e293b;">Video Link (Opsional)</label>
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
                        <small class="text-muted d-block mt-2">Link akan ditambahkan sebagai lampiran.</small>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="isPublished"
                                wire:model="isPublished"
                                style="width: 3em; height: 1.5em;"
                            >
                            <label class="form-check-label" for="isPublished" style="font-weight: 600; color: #1e293b;">
                                Publikasikan
                            </label>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="action-btn" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                        <button type="button" class="action-btn" wire:click="toggleEditPanel" style="background: #e2e8f0; color: #1e293b;">
                            Tutup
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

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
                    <div class="d-flex gap-2">
                        @if(($attachment['file_type'] ?? '') === 'link')
                            <a
                                href="{{ route('lecturer.course-materials.link', ['id' => $materialData['id'] ?? 0, 'fileId' => $attachment['id']]) }}"
                                class="action-btn"
                                target="_blank"
                                style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white;"
                            >
                                <i class="fas fa-link"></i> Buka Link
                            </a>
                        @else
                            <a
                                href="{{ $attachment['id'] ? route('lecturer.course-materials.download', ['id' => $materialData['id'] ?? 0, 'fileId' => $attachment['id']]) : route('lecturer.course-materials.download', ['id' => $materialData['id'] ?? 0]) }}"
                                class="action-btn"
                                style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white;"
                            >
                                <i class="fas fa-download"></i> Download
                            </a>
                        @endif
                        @if($attachment['id'])
                            <button
                                type="button"
                                class="action-btn"
                                wire:click="deleteAttachment({{ $attachment['id'] }})"
                                style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white;"
                            >
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        @elseif($materialData['has_legacy_file'] ?? false)
                            <button
                                type="button"
                                class="action-btn"
                                wire:click="deleteLegacyFile"
                                style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white;"
                            >
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-4" style="color: #94a3b8;">
                    <i class="fas fa-folder-open" style="font-size: 2.5rem;"></i>
                    <div class="mt-2">Belum ada lampiran untuk materi ini.</div>
                </div>
            @endforelse
        </div>
    </div>
</div>
