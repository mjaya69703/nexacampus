<?php

use App\Models\StudentService\GraduationApplication;
use App\Support\StudentService\GraduationApplicationService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public GraduationApplication $application;

    public array $form = [];

    public $graduationBatches;

    public array $eligibilityReport = [];

    public bool $canSubmit = false;

    public ?TemporaryUploadedFile $attachment = null;

    public array $documentUploads = [];

    public $documentRequirements;

    public string $submitLabel = 'Kirim Ulang';

    public function mount($id): void
    {
        $studentProfile = auth()->user()?->studentProfile;
        abort_unless($studentProfile, 404);

        $this->application = GraduationApplication::with(['documents.requirement', 'graduationBatch.academicPeriod.academicYear', 'graduationBatch.studyProgram'])
            ->where('student_profile_id', $studentProfile->id)
            ->findOrFail($id);
        abort_unless($this->application->status === 'revision_requested', 403);
        $service = app(GraduationApplicationService::class);
        $this->graduationBatches = $service->activeGraduationBatches($studentProfile);
        if ($this->application->graduationBatch && ! $this->graduationBatches->contains('id', $this->application->graduationBatch->id)) {
            $this->graduationBatches->push($this->application->graduationBatch);
        }
        $this->eligibilityReport = $service->eligibilityReport($studentProfile, $this->application->id);
        $this->canSubmit = (bool) $this->eligibilityReport['can_submit'];
        $this->documentRequirements = $service->documentRequirements($studentProfile);

        $this->form = [
            'graduation_batch_id' => $this->application->graduation_batch_id,
            'thesis_title' => $this->application->thesis_title,
            'reason' => $this->application->reason,
            'student_notes' => $this->application->student_notes,
        ];
    }

    public function save(GraduationApplicationService $service): void
    {
        $rules = [
            'form.graduation_batch_id' => ['required', 'integer', 'exists:graduation_batches,id'],
            'form.thesis_title' => ['nullable', 'string', 'max:255'],
            'form.reason' => ['nullable', 'string', 'max:3000'],
            'form.student_notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];

        $this->application->loadMissing('documents');
        $documentsByRequirement = $this->application->documents->keyBy('graduation_document_requirement_id');

        foreach ($this->documentRequirements ?? [] as $requirement) {
            $hasExistingValidDocument = $documentsByRequirement->has($requirement->id)
                && $documentsByRequirement->get($requirement->id)->verification_status !== 'rejected';
            $rules['documentUploads.'.$requirement->id] = [
                $requirement->is_required && ! $hasExistingValidDocument ? 'required' : 'nullable',
                'file',
                'mimes:'.implode(',', $requirement->allowedExtensionsList()),
                'max:'.($requirement->max_size_kb ?: 5120),
            ];
        }

        $validated = $this->validate($rules)['form'];

        try {
            $application = $service->resubmit($this->application, $validated, $this->attachment, $this->documentUploads);
            session()->flash('success', 'Perbaikan pengajuan yudisium berhasil dikirim ulang.');
            $this->redirectRoute('student.student-services.graduations.show', ['id' => $application->id]);
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Student Services',
            'pages' => 'Edit Graduation Application',
        ]);
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
                    <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                        <i class="fas fa-pen-to-square"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Student Services</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">Perbaiki Pengajuan Yudisium</h1>
                        <div style="opacity: 0.9;">Lengkapi revisi sesuai catatan admin lalu kirim ulang.</div>
                    </div>
                </div>
                <a href="{{ route('student.student-services.graduations.show', ['id' => $application->id]) }}" class="action-btn" style="background: rgba(255,255,255,0.94); color: #4f46e5;">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    @if ($application->admin_notes)
        <div class="alert alert-warning">
            <strong>Catatan Admin:</strong> {{ $application->admin_notes }}
        </div>
    @endif

    <div class="card service-card">
        <div class="card-header" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <div>
                <h3 class="card-title mb-0">{{ $application->application_number }}</h3>
                <small class="text-muted d-block mt-1">Perbaiki data pengajuan yudisium.</small>
            </div>
        </div>
        <div class="card-body p-4">
            @if (! $canSubmit)
                <div class="alert alert-warning">
                    <strong>Pengajuan belum bisa dikirim ulang.</strong>
                    <div class="small mt-1">Beberapa syarat yudisium belum terpenuhi atau periode sudah ditutup.</div>
                </div>
            @endif
            @if ($eligibilityReport)
                <div class="row g-2 mb-3">
                    @foreach (($eligibilityReport['checks'] ?? []) as $check)
                        <div class="col-md-6">
                            <div class="border rounded p-2 h-100 {{ ($check['required'] ?? true) && ! $check['passed'] ? 'border-warning bg-warning-lt' : 'border-success bg-green-lt' }}">
                                <div class="fw-bold">{{ $check['label'] }}</div>
                                <div class="small text-muted">{{ $check['message'] ?? 'Saat ini: '.(is_bool($check['actual']) ? ($check['actual'] ? 'Yes' : 'No') : $check['actual']).' | Syarat: '.(is_bool($check['expected']) ? ($check['expected'] ? 'Yes' : 'No') : $check['expected']) }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            <form wire:submit.prevent="save">
                @include('components.student.student-services.graduation-form', ['application' => $application])
            </form>
        </div>
    </div>
</div>
