<?php

use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementTargetType;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Publication\Announcement;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public int $announcementId;
    public array $announcementData = [];

    public string $title = '';
    public string $content = '';
    public ?int $targetId = null;
    public string $priority = 'normal';
    public bool $isPinned = false;
    public bool $isPublished = false;
    public string $publishedAt = '';
    public string $scheduledAt = '';
    public $attachment = null;
    public bool $removeAttachment = false;

    public array $courseOfferings = [];

    public function mount(int $id): void
    {
        $user = auth()->user();
        $announcement = Announcement::where('id', $id)
            ->where('created_by', $user->id)
            ->firstOrFail();

        $this->announcementId = $id;
        $this->announcementData = [
            'attachment_name' => $announcement->attachment_name,
            'attachment_path' => $announcement->attachment_path,
            'attachment_type' => $announcement->attachment_type,
            'attachment_size' => $announcement->formatted_attachment_size,
        ];

        $this->title = $announcement->title;
        $this->content = $announcement->content;
        $this->targetId = $announcement->target_id;
        $this->priority = $announcement->priority->value;
        $this->isPinned = $announcement->is_pinned;
        $this->isPublished = $announcement->is_published;
        $this->publishedAt = $announcement->published_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i');
        $this->scheduledAt = $announcement->scheduled_at?->format('Y-m-d\TH:i') ?? '';

        $this->loadCourseOfferings();
    }

    private function loadCourseOfferings(): void
    {
        $user = auth()->user();
        $lecturerProfile = $user->lecturerProfile;

        if (! $lecturerProfile) {
            return;
        }

        $this->courseOfferings = CourseOfferingLecturer::query()
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->where('is_active', true)
            ->with(['courseOffering.course', 'courseOffering.academicYear'])
            ->get()
            ->map(fn ($col) => [
                'id' => $col->courseOffering->id,
                'label' => ($col->courseOffering->course?->code ?? '-').' - '.($col->courseOffering->course?->name ?? '-').' ('.$col->courseOffering->academicYear?->name.')',
            ])
            ->values()
            ->toArray();
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'targetId' => 'required|integer',
            'priority' => 'required|in:'.implode(',', array_column(AnnouncementPriority::cases(), 'value')),
            'isPinned' => 'boolean',
            'isPublished' => 'boolean',
            'publishedAt' => 'nullable|date',
            'scheduledAt' => 'nullable|date',
            'attachment' => 'nullable|file|max:5120|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
        ]);

        $announcement = Announcement::where('id', $this->announcementId)
            ->where('created_by', auth()->id())
            ->firstOrFail();

        $data = [
            'title' => $this->title,
            'content' => $this->content,
            'target_type' => AnnouncementTargetType::COURSE_OFFERING->value,
            'target_id' => $this->targetId,
            'priority' => $this->priority,
            'is_pinned' => $this->isPinned,
            'is_published' => $this->isPublished,
            'published_at' => $this->isPublished ? ($this->publishedAt ?: now()) : null,
            'scheduled_at' => $this->scheduledAt ?: null,
            'updated_by' => auth()->id(),
        ];

        if ($this->removeAttachment && $announcement->attachment_path) {
            Storage::disk('public')->delete($announcement->attachment_path);
            $data['attachment_path'] = null;
            $data['attachment_name'] = null;
            $data['attachment_type'] = null;
            $data['attachment_size'] = null;
        }

        if ($this->attachment) {
            if ($announcement->attachment_path) {
                Storage::disk('public')->delete($announcement->attachment_path);
            }
            $year = now()->format('Y');
            $month = now()->format('m');
            $path = $this->attachment->store("announcements/{$year}/{$month}", 'public');
            $data['attachment_path'] = $path;
            $data['attachment_name'] = $this->attachment->getClientOriginalName();
            $data['attachment_type'] = $this->attachment->getClientOriginalExtension();
            $data['attachment_size'] = $this->attachment->getSize();
        }

        $announcement->update($data);

        session()->flash('success', 'Pengumuman berhasil diperbarui!');
        $this->redirectRoute('lecturer.announcements.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publikasi',
            'pages' => 'Edit Pengumuman',
        ]);
    }
};
?>

@push('styles')
<style>
    .modern-card { border-radius:20px;border:none;box-shadow:0 4px 20px rgba(0,0,0,0.08);background:white; }
    .hero-gradient { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:20px;color:white; }
    .form-field { border-radius:12px;border:2px solid #e2e8f0;padding:0.75rem 1rem;transition:border-color 0.2s; }
    .form-field:focus { border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,0.12); }
</style>
@endpush

<div>
    <x-alert />

    <div class="hero-gradient p-4 mb-4">
        <div class="d-flex align-items-center gap-3">
            <div style="width:56px;height:56px;background:rgba(255,255,255,0.2);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">
                <i class="fas fa-edit"></i>
            </div>
            <div>
                <div style="font-size:0.85rem;opacity:0.9;">Publikasi</div>
                <h2 class="mb-0" style="font-weight:700;">Edit Pengumuman</h2>
            </div>
        </div>
    </div>

    <form wire:submit.prevent="save">
        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card modern-card p-4 mb-4">
                    <h5 class="mb-3" style="font-weight:700;color:#1e293b;"><i class="fas fa-pen-nib me-2" style="color:#667eea;"></i>Konten Pengumuman</h5>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Judul <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-field" wire:model="title" required>
                        @error('title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Isi Pengumuman <span class="text-danger">*</span></label>
                        <livewire:jodit-text-editor wire:model.live="content" identifier="lecturer-announcement-edit" :height="350" />
                        @error('content') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="card modern-card p-4">
                    <h5 class="mb-3" style="font-weight:700;color:#1e293b;"><i class="fas fa-paperclip me-2" style="color:#667eea;"></i>Lampiran</h5>

                    @if($announcementData['attachment_name'] && !$removeAttachment)
                        <div class="d-flex align-items-center gap-3 p-3 mb-3" style="background:#f8fafc;border-radius:12px;border:2px solid #e2e8f0;">
                            <i class="fas fa-file-alt fa-2x" style="color:#667eea;"></i>
                            <div class="flex-grow-1">
                                <div class="fw-bold">{{ $announcementData['attachment_name'] }}</div>
                                <div class="text-muted small">{{ strtoupper($announcementData['attachment_type']) }} · {{ $announcementData['attachment_size'] }}</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="$set('removeAttachment', true)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    @endif

                    @if(!$announcementData['attachment_name'] || $removeAttachment)
                        <input type="file" class="form-control form-field" wire:model="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                        <small class="text-muted d-block mt-2">Maks 5MB. Format: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG.</small>
                        @if($removeAttachment)
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" wire:click="$set('removeAttachment', false)">
                                <i class="fas fa-undo me-1"></i>Batalkan Hapus
                            </button>
                        @endif
                    @endif
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card modern-card p-4 mb-4">
                    <h5 class="mb-3" style="font-weight:700;color:#1e293b;"><i class="fas fa-chalkboard me-2" style="color:#667eea;"></i>Kelas Tujuan</h5>
                    <select class="form-select form-field" wire:model="targetId">
                        <option value="">-- Pilih Kelas --</option>
                        @foreach($courseOfferings as $co)
                            <option value="{{ $co['id'] }}">{{ $co['label'] }}</option>
                        @endforeach
                    </select>
                    @error('targetId') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="card modern-card p-4 mb-4">
                    <h5 class="mb-3" style="font-weight:700;color:#1e293b;"><i class="fas fa-sliders me-2" style="color:#667eea;"></i>Pengaturan</h5>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Prioritas</label>
                        <select class="form-select form-field" wire:model="priority">
                            @foreach(\App\Enums\AnnouncementPriority::cases() as $p)
                                <option value="{{ $p->value }}">{{ $p->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="isPinned" wire:model="isPinned" style="width:3em;height:1.5em;">
                        <label class="form-check-label fw-bold" for="isPinned"><i class="fas fa-thumbtack me-2"></i>Pin Pengumuman</label>
                    </div>
                </div>

                <div class="card modern-card p-4 mb-4">
                    <h5 class="mb-3" style="font-weight:700;color:#1e293b;"><i class="fas fa-paper-plane me-2" style="color:#667eea;"></i>Publikasi</h5>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="isPublished" wire:model.live="isPublished" style="width:3em;height:1.5em;">
                        <label class="form-check-label fw-bold" for="isPublished">Publikasikan</label>
                    </div>

                    @if($isPublished)
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tanggal Publikasi</label>
                            <input type="datetime-local" class="form-control form-field" wire:model="publishedAt">
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label fw-bold">Jadwalkan (Opsional)</label>
                        <input type="datetime-local" class="form-control form-field" wire:model="scheduledAt">
                    </div>
                </div>

                <div class="d-flex flex-column gap-2">
                    <button type="submit" class="btn btn-lg w-100" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;border:none;border-radius:12px;font-weight:700;">
                        <i class="fas fa-save me-2"></i>Simpan Perubahan
                    </button>
                    <a href="{{ route('lecturer.announcements.index') }}" class="btn btn-lg w-100" style="background:#f1f5f9;color:#64748b;border:2px solid #e2e8f0;border-radius:12px;font-weight:600;">
                        Batal
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
