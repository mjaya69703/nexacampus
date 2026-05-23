<?php

use App\Support\StudentService\GraduationApplicationService;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public array $form = [
        'graduation_batch_id' => '',
        'thesis_title' => '',
        'reason' => '',
        'student_notes' => '',
    ];

    public $graduationBatches;

    public array $eligibilityReport = [];

    public bool $canSubmit = false;

    public ?TemporaryUploadedFile $attachment = null;

    public array $documentUploads = [];

    public $documentRequirements;

    public string $submitLabel = 'Submit Pengajuan';

    public function mount(): void
    {
        $service = app(GraduationApplicationService::class);
        $studentProfile = auth()->user()?->studentProfile;

        if ($studentProfile) {
            $this->graduationBatches = $service->activeGraduationBatches($studentProfile);
            $this->form['graduation_batch_id'] = $this->graduationBatches->count() === 1 ? $this->graduationBatches->first()->id : '';
            $this->eligibilityReport = $service->eligibilityReport($studentProfile);
            $this->canSubmit = (bool) $this->eligibilityReport['can_submit'];
            $this->documentRequirements = $service->documentRequirements($studentProfile);
        } else {
            $this->graduationBatches = collect();
        }
    }

    public function save(GraduationApplicationService $service): void
    {
        $studentProfile = auth()->user()?->studentProfile;
        abort_unless($studentProfile, 422, 'Student profile belum tersedia.');

        $rules = [
            'form.graduation_batch_id' => ['required', 'integer', 'exists:graduation_batches,id'],
            'form.thesis_title' => ['nullable', 'string', 'max:255'],
            'form.reason' => ['nullable', 'string', 'max:3000'],
            'form.student_notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];

        foreach ($this->documentRequirements ?? [] as $requirement) {
            $rules['documentUploads.'.$requirement->id] = [
                $requirement->is_required ? 'required' : 'nullable',
                'file',
                'mimes:'.implode(',', $requirement->allowedExtensionsList()),
                'max:'.($requirement->max_size_kb ?: 5120),
            ];
        }

        $validated = $this->validate($rules)['form'];

        try {
            $application = $service->create($studentProfile, $validated, $this->attachment, $this->documentUploads);
            session()->flash('success', 'Pengajuan yudisium berhasil dikirim.');
            $this->redirectRoute('student.student-services.graduations.show', ['id' => $application->id]);
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Student Services',
            'pages' => 'Create Graduation Application',
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
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Student Services</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">Ajukan Yudisium</h1>
                        <div style="opacity: 0.9;">Pastikan tagihan graduation sudah clear sebelum mengirim pengajuan.</div>
                    </div>
                </div>
                <a href="{{ route('student.student-services.graduations') }}" class="action-btn" style="background: rgba(255,255,255,0.94); color: #4f46e5;">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <div class="card service-card">
        <div class="card-header" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <div>
                <h3 class="card-title mb-0">Form Pengajuan Yudisium</h3>
                <small class="text-muted d-block mt-1">Pengajuan yang sudah finalized tidak bisa direvisi lagi.</small>
            </div>
        </div>
        <div class="card-body p-4">
            @if (! $canSubmit)
                <div class="alert alert-warning">
                    <strong>Pengajuan belum bisa dikirim.</strong>
                    <div class="small mt-1">Beberapa syarat yudisium belum terpenuhi. Hubungi akademik kalau data nilai/transkrip belum tersinkron.</div>
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
                @include('components.student.student-services.graduation-form')
            </form>
        </div>
    </div>
</div>
