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

    public int $announcementId;
    public array $announcementData = [];

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
    public bool $removeAttachment = false;

    public array $targetOptions = [];

    public function mount(int $id): void
    {
        $announcement = Announcement::findOrFail($id);
        $this->announcementId = $id;
        $this->announcementData = [
            'attachment_name' => $announcement->attachment_name,
            'attachment_path' => $announcement->attachment_path,
            'attachment_type' => $announcement->attachment_type,
            'attachment_size' => $announcement->formatted_attachment_size,
        ];

        $this->title = $announcement->title;
        $this->content = $announcement->content;
        $this->targetType = $announcement->target_type->value;
        $this->targetId = $announcement->target_id;
        $this->priority = $announcement->priority->value;
        $this->isPinned = $announcement->is_pinned;
        $this->isPublished = $announcement->is_published;
        $this->publishedAt = $announcement->published_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i');
        $this->scheduledAt = $announcement->scheduled_at?->format('Y-m-d\TH:i') ?? '';

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

        $announcement = Announcement::findOrFail($this->announcementId);

        $data = [
            'title' => $this->title,
            'content' => $this->content,
            'target_type' => $this->targetType,
            'target_id' => $targetEnum->requiresTargetId() ? $this->targetId : null,
            'priority' => $this->priority,
            'is_pinned' => $this->isPinned,
            'is_published' => $this->isPublished,
            'published_at' => $this->isPublished ? ($this->publishedAt ?: now()) : null,
            'scheduled_at' => $this->scheduledAt ?: null,
            'updated_by' => auth()->id(),
        ];

        // Handle attachment removal
        if ($this->removeAttachment && $announcement->attachment_path) {
            Storage::disk('public')->delete($announcement->attachment_path);
            $data['attachment_path'] = null;
            $data['attachment_name'] = null;
            $data['attachment_type'] = null;
            $data['attachment_size'] = null;
        }

        // Handle new attachment
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
        $this->redirectRoute('admin.publication.announcements.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publication',
            'pages' => 'Edit Pengumuman',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.publication.header
        title="Edit Pengumuman"
        description="Perbarui konten, target penerima, prioritas, dan pengaturan publikasi pengumuman yang sudah ada."
        icon="bullhorn"
    >
        <a href="{{ route('admin.publication.announcements.index') }}" class="btn  btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i>
            <span>Kembali ke daftar</span>
        </a>
    </x-admin.publication.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-pen-nib fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Pengumuman</h4>
                            <div class="text-muted small">Perbarui data pengumuman dengan lengkap dan konsisten.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Judul Pengumuman <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" placeholder="Masukkan judul pengumuman..." wire:model.defer="title">
                        @error('title')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Isi Pengumuman <span class="text-danger">*</span></label>
                        <livewire:jodit-text-editor wire:model.live="content" identifier="announcement-edit-content" :height="350" />
                        @error('content')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Lampiran</label>

                        @if($announcementData['attachment_name'] && !$removeAttachment)
                            <div class="d-flex align-items-center gap-3 p-3 mb-3 border rounded-4 bg-light">
                                <i class="fa fa-file-alt fa-2x text-warning"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">{{ $announcementData['attachment_name'] }}</div>
                                    <div class="text-muted small">{{ strtoupper($announcementData['attachment_type']) }} &middot; {{ $announcementData['attachment_size'] }}</div>
                                </div>
                                <button type="button" class="btn  btn-outline-danger d-inline-flex align-items-center gap-1" wire:click="$set('removeAttachment', true)">
                                    <i class="fa fa-trash"></i>
                                    <span>Hapus</span>
                                </button>
                            </div>
                        @endif

                        @if(!$announcementData['attachment_name'] || $removeAttachment)
                            <input type="file" class="form-control rounded-3" wire:model="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                            <div class="d-flex align-items-start gap-2 mt-2">
                                <span class="text-muted small">Maks 5MB. Format: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG.</span>
                            </div>
                            @error('attachment')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                            @if($attachment)
                                <div class="mt-2 d-flex align-items-center gap-2">
                                    <i class="fa fa-file text-warning"></i>
                                    <span class="small">{{ $attachment->getClientOriginalName() }}</span>
                                </div>
                            @endif
                            @if($removeAttachment)
                                <button type="button" class="btn  btn-outline-secondary mt-2 d-inline-flex align-items-center gap-1" wire:click="$set('removeAttachment', false)">
                                    <i class="fa fa-undo"></i>
                                    <span>Batalkan Hapus</span>
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-crosshairs fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Target Penerima</h4>
                            <div class="text-muted small">Pilih siapa yang akan menerima pengumuman.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipe Target <span class="text-danger">*</span></label>
                        <select class="form-select rounded-3" wire:model.live="targetType">
                            @foreach(\App\Enums\AnnouncementTargetType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if(!empty($targetOptions))
                        <div class="mb-0">
                            <label class="form-label fw-semibold">Pilih Target <span class="text-danger">*</span></label>
                            <select class="form-select rounded-3" wire:model="targetId">
                                <option value="">-- Pilih --</option>
                                @foreach($targetOptions as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                            @error('targetId')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-sliders fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Pengaturan</h4>
                            <div class="text-muted small">Atur prioritas dan opsi penayangan.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Prioritas</label>
                        <select class="form-select rounded-3" wire:model="priority">
                            @foreach(\App\Enums\AnnouncementPriority::cases() as $p)
                                <option value="{{ $p->value }}">{{ $p->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="isPinned" wire:model="isPinned">
                            <label class="form-check-label fw-semibold" for="isPinned">
                                <i class="fa fa-thumbtack me-2 text-info"></i>Pin Pengumuman
                            </label>
                        </div>
                        <div class="text-muted small mt-2">Pengumuman yang di-pin akan selalu muncul di urutan teratas.</div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-paper-plane fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Publikasi</h4>
                            <div class="text-muted small">Atur status dan jadwal publikasi.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="border rounded-4 p-3 bg-light bg-opacity-50 mb-3">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="isPublished" wire:model.live="isPublished">
                            <label class="form-check-label fw-semibold" for="isPublished">Publikasikan</label>
                        </div>
                    </div>

                    @if($isPublished)
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tanggal Publikasi</label>
                            <input type="datetime-local" class="form-control rounded-3" wire:model="publishedAt">
                        </div>
                    @endif

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Jadwalkan (Opsional)</label>
                        <input type="datetime-local" class="form-control rounded-3" wire:model="scheduledAt">
                    </div>
                </div>
            </div>

            <div class="d-flex flex-column gap-2">
                <button type="button" class="btn btn-primary rounded-pill px-4 w-100 d-inline-flex align-items-center justify-content-center gap-2" wire:click="save">
                    <i class="fa fa-save"></i>
                    <span>Simpan Perubahan</span>
                </button>
                <a href="{{ route('admin.publication.announcements.index') }}" class="btn btn-outline-secondary rounded-pill px-4 w-100 d-inline-flex align-items-center justify-content-center gap-2">
                    <i class="fa fa-times"></i>
                    <span>Batal</span>
                </a>
            </div>
        </div>
    </div>
</div>
