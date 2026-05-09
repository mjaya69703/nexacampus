<?php

use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementTargetType;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Publication\Announcement;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $title = '';
    public string $content = '';
    public ?int $targetId = null;
    public string $priority = 'normal';
    public bool $isPinned = false;
    public bool $isPublished = false;
    public string $publishedAt = '';
    public string $scheduledAt = '';
    public $attachment = null;

    public array $courseOfferings = [];

    public function mount(): void
    {
        $this->publishedAt = now()->format('Y-m-d\TH:i');
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

        $data = [
            'title' => $this->title,
            'content' => $this->content,
            'target_type' => AnnouncementTargetType::COURSE_OFFERING->value,
            'target_id' => $this->targetId,
            'created_by' => auth()->id(),
            'priority' => $this->priority,
            'is_pinned' => $this->isPinned,
            'is_published' => $this->isPublished,
            'published_at' => $this->isPublished ? ($this->publishedAt ?: now()) : null,
            'scheduled_at' => $this->scheduledAt ?: null,
        ];

        if ($this->attachment) {
            $year = now()->format('Y');
            $month = now()->format('m');
            $path = $this->attachment->store("announcements/{$year}/{$month}", 'public');
            $data['attachment_path'] = $path;
            $data['attachment_name'] = $this->attachment->getClientOriginalName();
            $data['attachment_type'] = $this->attachment->getClientOriginalExtension();
            $data['attachment_size'] = $this->attachment->getSize();
        }

        Announcement::create($data);

        session()->flash('success', 'Pengumuman berhasil dibuat!');
        $this->redirectRoute('lecturer.announcements.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publikasi',
            'pages' => 'Buat Pengumuman',
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
                <i class="fas fa-bullhorn"></i>
            </div>
            <div>
                <div style="font-size:0.85rem;opacity:0.9;">Publikasi</div>
                <h2 class="mb-0" style="font-weight:700;">Buat Pengumuman</h2>
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
                        <input type="text" class="form-control form-field" wire:model="title" placeholder="Judul pengumuman..." required>
                        @error('title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Isi Pengumuman <span class="text-danger">*</span></label>
                        <livewire:jodit-text-editor wire:model.live="content" identifier="lecturer-announcement-create" :height="350" />
                        @error('content') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="card modern-card p-4">
                    <h5 class="mb-3" style="font-weight:700;color:#1e293b;"><i class="fas fa-paperclip me-2" style="color:#667eea;"></i>Lampiran (Opsional)</h5>
                    <input type="file" class="form-control form-field" wire:model="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                    <small class="text-muted d-block mt-2">Maks 5MB. Format: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG.</small>
                    @error('attachment') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    @if($attachment)
                        <div class="mt-2 badge bg-primary-lt text-primary p-2">
                            <i class="fas fa-file me-1"></i>{{ $attachment->getClientOriginalName() }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card modern-card p-4 mb-4">
                    <h5 class="mb-3" style="font-weight:700;color:#1e293b;"><i class="fas fa-chalkboard me-2" style="color:#667eea;"></i>Kelas Tujuan</h5>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih Kelas <span class="text-danger">*</span></label>
                        <select class="form-select form-field" wire:model="targetId">
                            <option value="">-- Pilih Kelas --</option>
                            @foreach($courseOfferings as $co)
                                <option value="{{ $co['id'] }}">{{ $co['label'] }}</option>
                            @endforeach
                        </select>
                        @error('targetId') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
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
                        <label class="form-check-label fw-bold" for="isPublished">Publikasikan Sekarang</label>
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
                        <i class="fas fa-paper-plane me-2"></i>Simpan Pengumuman
                    </button>
                    <a href="{{ route('lecturer.announcements.index') }}" class="btn btn-lg w-100" style="background:#f1f5f9;color:#64748b;border:2px solid #e2e8f0;border-radius:12px;font-weight:600;">
                        Batal
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
