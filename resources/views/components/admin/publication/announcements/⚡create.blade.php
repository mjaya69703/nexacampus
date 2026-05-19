<?php

use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementTargetType;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Publication\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $title = '';
    public string $content = '';
    public string $targetType = 'global';
    public ?int $targetId = null;
    public string $priority = 'normal';
    public bool $isPinned = false;
    public bool $isPublished = false;
    public string $publishedAt = '';
    public string $scheduledAt = '';
    public $attachment = null;

    // Dynamic target options
    public array $targetOptions = [];

    public function mount(): void
    {
        $this->publishedAt = now()->format('Y-m-d\TH:i');
        $this->loadTargetOptions();
    }

    public function updatedTargetType(): void
    {
        $this->targetId = null;
        $this->loadTargetOptions();
    }

    private function loadTargetOptions(): void
    {
        $this->targetOptions = match ($this->targetType) {
            'faculty' => Faculty::orderBy('name')->get()->map(fn ($f) => ['id' => $f->id, 'label' => $f->name])->toArray(),
            'study_program' => StudyProgram::with('faculty')->orderBy('name')->get()->map(fn ($sp) => ['id' => $sp->id, 'label' => $sp->name.' ('.$sp->faculty?->name.')'])->toArray(),
            'course_offering' => CourseOffering::with(['course', 'academicYear'])->orderByDesc('id')->limit(100)->get()->map(fn ($co) => ['id' => $co->id, 'label' => ($co->course?->code ?? '-').' - '.($co->course?->name ?? '-').' ('.$co->academicYear?->name.')'])->toArray(),
            'lecturer' => User::role('lecturer')->orderBy('name')->get()->map(fn ($u) => ['id' => $u->lecturerProfile?->id, 'label' => $u->name])->filter(fn ($u) => $u['id'])->values()->toArray(),
            'student' => User::role('student')->orderBy('name')->get()->map(fn ($u) => ['id' => $u->studentProfile?->id, 'label' => $u->name])->filter(fn ($u) => $u['id'])->values()->toArray(),
            default => [],
        };
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'targetType' => 'required|in:'.implode(',', array_column(AnnouncementTargetType::cases(), 'value')),
            'targetId' => 'nullable|integer',
            'priority' => 'required|in:'.implode(',', array_column(AnnouncementPriority::cases(), 'value')),
            'isPinned' => 'boolean',
            'isPublished' => 'boolean',
            'publishedAt' => 'nullable|date',
            'scheduledAt' => 'nullable|date',
            'attachment' => 'nullable|file|max:5120|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
        ]);

        $targetEnum = AnnouncementTargetType::from($this->targetType);
        if ($targetEnum->requiresTargetId() && ! $this->targetId) {
            $this->addError('targetId', 'Target harus dipilih untuk tipe ini.');
            return;
        }

        $data = [
            'title' => $this->title,
            'content' => $this->content,
            'target_type' => $this->targetType,
            'target_id' => $targetEnum->requiresTargetId() ? $this->targetId : null,
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
        $this->redirectRoute('admin.publication.announcements.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publication',
            'pages' => 'Buat Pengumuman',
        ]);
    }
};
?>

@push('styles')
<style>
    .modern-card {
        border-radius: 20px;
        border: none;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        background: white;
    }
    .hero-gradient {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        border-radius: 20px;
        color: white;
        position: relative;
        overflow: hidden;
    }
    .form-field {
        border-radius: 12px;
        border: 2px solid #e2e8f0;
        padding: 0.75rem 1rem;
        transition: border-color 0.2s;
    }
    .form-field:focus {
        border-color: #f59e0b;
        box-shadow: 0 0 0 3px rgba(245,158,11,0.12);
    }
</style>
@endpush

<div>
    <x-alert />

    {{-- Hero --}}
    <div class="hero-gradient p-4 mb-4">
        <div class="d-flex align-items-center gap-3">
            <div style="width:56px;height:56px;background:rgba(255,255,255,0.2);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">
                <i class="fas fa-bullhorn"></i>
            </div>
            <div>
                <div style="font-size:0.85rem;opacity:0.9;">Publication</div>
                <h2 class="mb-0" style="font-weight:700;">Buat Pengumuman Baru</h2>
            </div>
        </div>
    </div>

    <form wire:submit.prevent="save">
        <div class="row g-4">
            {{-- Left: Content --}}
            <div class="col-xl-8">
                <div class="card modern-card p-4 mb-4">
                    <h5 class="mb-3" style="font-weight:700;color:#1e293b;"><i class="fas fa-pen-nib me-2" style="color:#f59e0b;"></i>Konten Pengumuman</h5>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Judul <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-field" wire:model="title" placeholder="Judul pengumuman..." required>
                        @error('title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Isi Pengumuman <span class="text-danger">*</span></label>
                        <livewire:jodit-text-editor wire:model.live="content" identifier="announcement-create-content" :height="350" />
                        @error('content') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- Attachment --}}
                <div class="card modern-card p-4">
                    <h5 class="mb-3" style="font-weight:700;color:#1e293b;"><i class="fas fa-paperclip me-2" style="color:#f59e0b;"></i>Lampiran (Opsional)</h5>
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

            {{-- Right: Settings --}}
            <div class="col-xl-4">
                {{-- Target --}}
                <div class="card modern-card p-4 mb-4">
                    <h5 class="mb-3" style="font-weight:700;color:#1e293b;"><i class="fas fa-crosshairs me-2" style="color:#f59e0b;"></i>Target Penerima</h5>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Tipe Target <span class="text-danger">*</span></label>
                        <select class="form-select form-field" wire:model.live="targetType">
                            @foreach(\App\Enums\AnnouncementTargetType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if(!empty($targetOptions))
                        <div class="mb-3">
                            <label class="form-label fw-bold">Pilih Target <span class="text-danger">*</span></label>
                            <select class="form-select form-field" wire:model="targetId">
                                <option value="">-- Pilih --</option>
                                @foreach($targetOptions as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                            @error('targetId') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    @endif
                </div>

                {{-- Priority & Pin --}}
                <div class="card modern-card p-4 mb-4">
                    <h5 class="mb-3" style="font-weight:700;color:#1e293b;"><i class="fas fa-sliders me-2" style="color:#f59e0b;"></i>Pengaturan</h5>

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

                {{-- Publish --}}
                <div class="card modern-card p-4 mb-4">
                    <h5 class="mb-3" style="font-weight:700;color:#1e293b;"><i class="fas fa-paper-plane me-2" style="color:#f59e0b;"></i>Publikasi</h5>

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
                        <small class="text-muted">Kosongkan jika tidak dijadwalkan.</small>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="d-flex flex-column gap-2">
                    <button type="submit" class="btn btn-lg w-100" style="background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%);color:white;border:none;border-radius:12px;font-weight:700;">
                        <i class="fas fa-paper-plane me-2"></i>Simpan Pengumuman
                    </button>
                    <a href="{{ route('admin.publication.announcements.index') }}" class="btn btn-lg w-100" style="background:#f1f5f9;color:#64748b;border:2px solid #e2e8f0;border-radius:12px;font-weight:600;">
                        Batal
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
