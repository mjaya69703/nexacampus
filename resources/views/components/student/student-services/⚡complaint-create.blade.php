<?php

use App\Models\StudentService\StudentComplaintCategory;
use App\Support\StudentService\StudentComplaintService;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public array $form = [];
    public array $attachments = [];
    public array $editorButtons = ['bold', 'italic', 'underline', '|', 'ul', 'ol', '|', 'link', '|', 'undo', 'redo'];

    public function mount(): void
    {
        $this->form = [
            'student_complaint_category_id' => '',
            'subject' => '',
            'description' => '',
            'priority' => 'normal',
        ];
    }

    public function save(StudentComplaintService $service): void
    {
        $profile = auth()->user()?->studentProfile;
        abort_unless($profile, 403);

        $validated = $this->validate([
            'form.student_complaint_category_id' => ['required', 'exists:student_complaint_categories,id'],
            'form.subject' => ['required', 'string', 'max:255'],
            'form.description' => ['required', 'string', 'max:5000'],
            'form.priority' => ['required', 'in:low,normal,high,urgent'],
            'attachments' => ['nullable', 'array', 'max:6'],
            'attachments.*' => ['file', 'extensions:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt'],
        ]);

        try {
            $complaint = $service->create($profile, $validated['form'], $this->attachments);
            session()->flash('success', 'Pengaduan berhasil dikirim.');
            $this->redirectRoute('student.student-services.complaints.show', ['id' => $complaint->id]);
        } catch (Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Student Services', 'pages' => 'Buat Pengaduan']);
    }

    public function getCategoriesProperty()
    {
        return StudentComplaintCategory::where('is_active', true)->orderBy('name')->get();
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }
};
?>

@include('components.student.student-services.service-styles')

<div>
    <x-alert />

    <div class="card service-card hero-gradient mb-4">
        <div class="card-body p-4 p-lg-5" style="position: relative;">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 72px; height: 72px; background: rgba(255,255,255,.2); border-radius: 18px; display:flex; align-items:center; justify-content:center; font-size:2rem; backdrop-filter: blur(10px);">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div>
                        <div style="font-size: .9rem; opacity: .88; margin-bottom: .35rem;">Layanan Mahasiswa</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">Buat Pengaduan</h1>
                        <div style="opacity: .9;">Jelaskan kendalanya dengan detail supaya unit terkait bisa merespons lebih cepat.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="info-badge"><i class="fas fa-clock me-2"></i>Dipantau SLA</span>
                            <span class="info-badge"><i class="fas fa-paperclip me-2"></i>Multi lampiran</span>
                            <span class="info-badge"><i class="fas fa-comments me-2"></i>Thread balasan</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('student.student-services.complaints') }}" class="action-btn" style="background: rgba(255,255,255,.94); color:#4f46e5;">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card service-card">
                <div class="card-header service-section-header">
                    <div class="d-flex align-items-center gap-3">
                        <span class="metric-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-pen-to-square"></i></span>
                        <div>
                            <h3 class="card-title mb-0" style="font-weight: 800;">Detail Pengaduan</h3>
                            <small class="text-muted">Isi judul, kategori, prioritas, dan kronologi pengaduan.</small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form wire:submit="save" class="row g-3">
                        <div class="col-lg-6">
                            <label class="form-label required">Kategori</label>
                            <select class="form-select" wire:model.defer="form.student_complaint_category_id">
                                <option value="">Pilih kategori</option>
                                @foreach ($this->categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('form.student_complaint_category_id') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label required">Prioritas</label>
                            <select class="form-select" wire:model.defer="form.priority">
                                <option value="low">Rendah</option>
                                <option value="normal">Normal</option>
                                <option value="high">Tinggi</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label required">Judul</label>
                            <input type="text" class="form-control" wire:model.defer="form.subject" placeholder="Contoh: Kendala akses materi perkuliahan">
                            @error('form.subject') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label required">Deskripsi</label>
                            <livewire:jodit-text-editor wire:model.live="form.description" identifier="student-complaint-create-description" :buttons="$editorButtons" :height="320" />
                            @error('form.description') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Lampiran Pendukung</label>
                            <div class="upload-dropzone">
                                <input type="file" class="form-control" wire:model="attachments" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                                <div class="form-hint mt-2">Maksimal 6 file, masing-masing 5MB. Format: gambar, PDF, Word, Excel, PowerPoint, atau TXT.</div>
                                <div wire:loading wire:target="attachments" class="mt-3 w-100">
                                    <div class="d-flex align-items-center justify-content-between small text-primary mb-1">
                                        <span><i class="fas fa-spinner fa-spin me-1"></i>Mengunggah lampiran...</span>
                                        <span>Tunggu sampai selesai sebelum kirim</span>
                                    </div>
                                    <div class="upload-progress"><span style="width: 100%;"></span></div>
                                </div>
                                @if ($attachments)
                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        @foreach ($attachments as $index => $file)
                                            <span class="attachment-chip">
                                                <i class="fas fa-paperclip"></i>
                                                {{ $file->getClientOriginalName() }}
                                                <button type="button" class="btn btn-sm p-0 ms-1 text-danger" wire:click="removeAttachment({{ $index }})" title="Hapus lampiran">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            @error('attachments') <span class="text-danger small">{{ $message }}</span> @enderror
                            @error('attachments.*') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2 flex-wrap">
                            <a href="{{ route('student.student-services.complaints') }}" class="btn btn-light">Batal</a>
                            <button type="submit" class="action-btn" style="background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:white;" wire:loading.attr="disabled" wire:target="save,attachments">
                                <i class="fas fa-paper-plane"></i> Kirim Pengaduan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="service-shell">
                <div class="support-panel">
                    <div class="d-flex gap-3">
                        <span class="metric-icon" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-route"></i></span>
                        <div>
                            <div class="fw-bold">Alur Pengaduan</div>
                            <div class="small text-muted mt-1">Tiket akan masuk ke unit default kategori, lalu staff bisa membalas atau meminta informasi tambahan.</div>
                        </div>
                    </div>
                </div>
                <div class="support-panel">
                    <div class="fw-bold mb-2"><i class="fas fa-lightbulb text-warning me-2"></i>Supaya cepat diproses</div>
                    <div class="small text-muted">Tulis kronologi, tanggal kejadian, halaman/menu yang bermasalah, dan sertakan screenshot atau dokumen pendukung kalau ada.</div>
                </div>
            </div>
        </div>
    </div>
</div>
