<?php

use App\Models\StudentService\ServiceLetterRequest;
use App\Support\StudentService\ServiceLetterRequestService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ServiceLetterRequest $request;

    public array $form = [
        'purpose' => '',
        'student_notes' => '',
        'request_data' => [],
    ];

    public ?TemporaryUploadedFile $attachment = null;

    public function mount($id): void
    {
        $studentProfile = auth()->user()?->studentProfile;
        abort_unless($studentProfile, 404);

        $this->request = ServiceLetterRequest::with('letterType')
            ->where('student_profile_id', $studentProfile->id)
            ->findOrFail($id);

        abort_unless($this->request->status === 'revision_requested', 403);

        $this->form = [
            'purpose' => $this->request->purpose,
            'student_notes' => $this->request->student_notes,
            'request_data' => $this->request->request_data ?? [],
        ];

        foreach (($this->request->letterType?->required_fields ?? []) as $field) {
            if ($field !== 'purpose' && ! array_key_exists($field, $this->form['request_data'])) {
                $this->form['request_data'][$field] = '';
            }
        }
    }

    public function save(ServiceLetterRequestService $service): void
    {
        $letterType = $this->request->letterType;

        $attachmentRequired = $letterType->requires_attachment && blank($this->request->attachment_path);
        $rules = [
            'form.purpose' => ['required', 'string', 'max:1000'],
            'form.student_notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => array_values(array_filter([
                $attachmentRequired ? 'required' : 'nullable',
                'file',
                $letterType->allowed_extensions ? 'mimes:'.$letterType->allowed_extensions : null,
                'max:'.($letterType->max_file_size_kb ?: 2048),
            ])),
        ];

        foreach (($letterType->required_fields ?? []) as $field) {
            if ($field !== 'purpose') {
                $rules['form.request_data.'.$field] = ['required', 'string', 'max:1000'];
            }
        }

        $validated = $this->validate($rules)['form'];

        try {
            $request = $service->resubmit($this->request, $validated, $this->attachment);
            session()->flash('success', 'Perbaikan pengajuan berhasil dikirim ulang.');
            $this->redirectRoute('student.student-services.letters.show', ['id' => $request->id]);
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Student Services',
            'pages' => 'Perbaiki Pengajuan Surat',
        ]);
    }

    public function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
};
?>

@push('styles')
    <style>
        .service-card {
            border: 0;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
            background: white;
        }
        .hero-gradient {
            border-radius: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            overflow: hidden;
            position: relative;
        }
        .hero-gradient::before {
            content: '';
            position: absolute;
            inset: -60% -30% auto auto;
            width: 420px;
            height: 420px;
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
        }
        .form-shell {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 16px;
            padding: 1.25rem;
        }
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            border-radius: 10px;
            padding: .65rem 1.05rem;
            border: 0;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card service-card hero-gradient mb-4">
        <div class="card-body p-4 p-lg-5" style="position: relative;">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                        <i class="fas fa-pen-to-square"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Student Services</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">Perbaiki Pengajuan</h1>
                        <div style="opacity: 0.9;">Lengkapi revisi sesuai catatan admin lalu kirim ulang untuk direview.</div>
                    </div>
                </div>
                <a href="{{ route('student.student-services.letters.show', ['id' => $request->id]) }}" class="action-btn" style="background: rgba(255,255,255,0.94); color: #4f46e5;">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    @if ($request->admin_notes)
        <div class="alert alert-warning">
            <strong>Catatan Admin:</strong> {{ $request->admin_notes }}
        </div>
    @endif

    <div class="card service-card">
        <div class="card-header" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <div>
                <h3 class="card-title mb-0">{{ $request->letterType?->name }}</h3>
                <small class="text-muted d-block mt-1">{{ $request->request_number }}</small>
            </div>
        </div>
        <div class="card-body p-4">
            <form wire:submit.prevent="save">
                <div class="row g-3 form-shell">
                    <div class="col-12">
                        <label class="form-label required">Keperluan</label>
                        <textarea wire:model="form.purpose" class="form-control" rows="3"></textarea>
                        @error('form.purpose') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    @foreach (($request->letterType?->required_fields ?? []) as $field)
                        @continue($field === 'purpose')
                        <div class="col-md-6">
                            <label class="form-label required">{{ str($field)->replace('_', ' ')->title() }}</label>
                            <input type="text" wire:model="form.request_data.{{ $field }}" class="form-control">
                            @error('form.request_data.'.$field) <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                    @endforeach

                    <div class="col-12">
                        <label class="form-label">Lampiran Pendukung</label>
                        @if ($request->attachment_path)
                            <div class="mb-2">
                                <a href="{{ $this->fileUrl($request->attachment_path) }}" target="_blank" class="badge bg-blue-lt text-blue text-decoration-none">
                                    <i class="fas fa-paperclip me-1"></i> Lampiran saat ini
                                </a>
                            </div>
                        @endif
                        <input type="file" wire:model="attachment" class="form-control">
                        <small class="text-muted">Upload file baru jika lampiran sebelumnya perlu diganti.</small>
                        @error('attachment') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Catatan Tambahan</label>
                        <textarea wire:model="form.student_notes" class="form-control" rows="3"></textarea>
                        @error('form.student_notes') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="action-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <i class="fas fa-paper-plane me-1"></i> Kirim Ulang
                        </button>
                        <a href="{{ route('student.student-services.letters.show', ['id' => $request->id]) }}" class="action-btn" style="background: #e5e7eb; color: #374151;">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
