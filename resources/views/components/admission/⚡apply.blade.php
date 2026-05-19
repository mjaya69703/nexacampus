<?php

use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionPeriod;
use App\Mail\AdmissionApplicationSubmitted;
use App\Support\Admission\AdmissionNumberService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ?AdmissionPeriod $period = null;

    public array $form = [];

    public array $faculties = [];

    public array $studyPrograms = [];

    public array $requirements = [];

    public array $documentUploads = [];

    public ?string $submittedApplicationNumber = null;

    public ?string $submittedAccessToken = null;

    public array $stats = [];

    public function mount(): void
    {
        $this->period = AdmissionPeriod::query()
            ->where('is_active', true)
            ->where('is_published', true)
            ->whereDate('opens_at', '<=', now())
            ->whereDate('closes_at', '>=', now())
            ->orderByDesc('opens_at')
            ->first();

        $this->form = [
            'full_name' => '',
            'email' => '',
            'phone' => '',
            'birth_date' => '',
            'gender' => 'male',
            'address' => '',
            'emergency_contact_name' => '',
            'emergency_contact_phone' => '',
            'high_school_name' => '',
            'high_school_major' => '',
            'high_school_graduation_year' => now()->year,
            'faculty_id' => '',
            'study_program_id' => '',
            'class_type' => 'regular',
        ];

        $this->faculties = Faculty::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        $this->loadStudyPrograms();
        $this->loadRequirements();
        $this->loadStats();
    }

    public function updatedForm($value, string $key): void
    {
        if ($key === 'faculty_id') {
            $this->form['study_program_id'] = '';
            $this->loadStudyPrograms();
        }
    }

    public function loadStudyPrograms(): void
    {
        $this->studyPrograms = StudyProgram::query()
            ->where('is_active', true)
            ->when($this->form['faculty_id'] ?? null, fn ($query, $facultyId) => $query->where('faculty_id', $facultyId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    public function submit(AdmissionNumberService $numberService): void
    {
        abort_unless($this->period, 404);

        $rules = [
            'form.full_name' => 'required|string|max:255',
            'form.email' => 'required|email|max:255',
            'form.phone' => 'required|string|max:40',
            'form.birth_date' => 'required|date',
            'form.gender' => 'required|in:male,female',
            'form.address' => 'required|string',
            'form.emergency_contact_name' => 'required|string|max:255',
            'form.emergency_contact_phone' => 'required|string|max:40',
            'form.high_school_name' => 'required|string|max:255',
            'form.high_school_major' => 'required|string|max:255',
            'form.high_school_graduation_year' => 'required|integer|min:1980|max:'.(now()->year + 1),
            'form.faculty_id' => 'nullable|exists:faculties,id',
            'form.study_program_id' => 'nullable|exists:study_programs,id',
            'form.class_type' => 'nullable|in:regular,evening,weekend',
        ];

        foreach ($this->requirements as $requirement) {
            $key = 'documentUploads.'.$requirement['document_type'];
            $rule = ($requirement['is_required'] ? 'required' : 'nullable').'|file';
            $rules[$key] = $rule;
        }

        $validated = $this->validate($rules);
        $documentFiles = [];

        foreach ($this->requirements as $requirement) {
            $type = $requirement['document_type'];
            $file = $this->documentUploads[$type] ?? null;

            if (! $file) {
                continue;
            }

            $uploadError = $this->validateDocumentUpload($type, $file, $requirement);

            if ($uploadError) {
                $this->addError('documentUploads.'.$type, $uploadError[0]);
                return;
            }

            $documentFiles[$type] = [
                'file' => $file,
                'name' => $file->getClientOriginalName(),
                'size' => $this->uploadedFileSize($file),
            ];
        }

        $application = AdmissionApplication::create([
            ...$validated['form'],
            'admission_period_id' => $this->period->id,
            'application_number' => $numberService->generate($this->period),
            'access_token' => $numberService->token(),
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $application->statusHistories()->create([
            'from_status' => null,
            'to_status' => 'submitted',
            'notes' => 'Application submitted from public form.',
            'changed_by' => null,
        ]);

        foreach ($this->requirements as $requirement) {
            $filePayload = $documentFiles[$requirement['document_type']] ?? null;

            if (! $filePayload) {
                continue;
            }

            $file = $filePayload['file'];
            $path = $file->store('admission/'.$application->application_number, 'public');

            $application->documents()->create([
                'document_requirement_id' => $requirement['id'],
                'document_type' => $requirement['document_type'],
                'file_path' => $path,
                'file_name' => $filePayload['name'],
                'file_size' => $filePayload['size'],
                'verification_status' => 'pending',
            ]);
        }

        $portalUrl = route('admission.portal', [
            'applicationNumber' => $application->application_number,
            'token' => $application->access_token,
        ]);

        try {
            Mail::to($application->email)->send(
                new AdmissionApplicationSubmitted($application->load(['period', 'studyProgram']), $portalUrl),
            );
        } catch (Throwable $exception) {
            Log::warning('Admission application email failed.', [
                'application_id' => $application->id,
                'email' => $application->email,
                'message' => $exception->getMessage(),
            ]);
        }

        $this->submittedApplicationNumber = $application->application_number;
        $this->submittedAccessToken = $application->access_token;
        $this->reset('documentUploads');
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Admission',
            'pages' => 'Apply',
        ]);
    }

    private function loadRequirements(): void
    {
        $this->requirements = $this->period
            ? $this->period->documentRequirements()
                ->orderBy('sort_order')
                ->get(['id', 'document_type', 'label', 'is_required', 'allowed_extensions', 'max_size_kb'])
                ->toArray()
            : [];
    }

    private function loadStats(): void
    {
        $this->stats = [
            'active_periods' => AdmissionPeriod::query()
                ->where('is_active', true)
                ->where('is_published', true)
                ->count(),
            'study_programs' => StudyProgram::query()
                ->where('is_active', true)
                ->count(),
            'requirements' => count($this->requirements),
            'days_left' => $this->period ? max(0, now()->startOfDay()->diffInDays($this->period->closes_at, false)) : 0,
        ];
    }

    private function uploadedFileSize($file): int
    {
        try {
            return (int) $file->getSize();
        } catch (Throwable $exception) {
            Log::warning('Admission temporary upload size unavailable.', [
                'message' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    private function validateDocumentUpload(string $type, $file, array $requirement): ?array
    {
        $fileSize = $this->uploadedFileSize($file);
        $maxBytes = ((int) ($requirement['max_size_kb'] ?: 2048)) * 1024;

        if ($fileSize <= 0) {
            return ['File belum selesai diunggah. Tunggu sebentar lalu submit lagi.'];
        }

        if ($fileSize > $maxBytes) {
            return ['Ukuran file melebihi batas '.number_format($maxBytes / 1024).' KB.'];
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = $this->allowedExtensions($requirement['allowed_extensions'] ?? null);

        if (! in_array($extension, $allowedExtensions, true)) {
            return ['Tipe file tidak diizinkan. Format yang diperbolehkan: '.implode(', ', $allowedExtensions).'.'];
        }

        $allowedMimeTypes = $this->allowedMimeTypes($allowedExtensions);
        $mimeType = $file->getMimeType();

        if ($mimeType && ! in_array($mimeType, $allowedMimeTypes, true)) {
            return ['Isi file tidak sesuai format yang diperbolehkan.'];
        }

        return null;
    }

    private function allowedExtensions(?string $extensions): array
    {
        $allowed = collect(explode(',', $extensions ?: 'pdf,jpg,jpeg,png'))
            ->map(fn (string $extension) => strtolower(trim($extension)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $safeAllowList = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

        return array_values(array_intersect($allowed, $safeAllowList)) ?: ['pdf', 'jpg', 'jpeg', 'png'];
    }

    private function allowedMimeTypes(array $extensions): array
    {
        $map = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
        ];

        return collect($extensions)
            ->flatMap(fn (string $extension) => $map[$extension] ?? [])
            ->unique()
            ->values()
            ->all();
    }
};
?>

<div class="admission-public">
    <div class="container-xl py-4 py-lg-5">
        <div class="row justify-content-center">
            <div class="col-lg-11">
                <div class="admission-hero mb-4">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-7">
                            <div class="admission-kicker">NexaCampus Admission</div>
                            <h1 class="admission-title">Start your application with a clear, guided flow.</h1>
                            <p class="admission-subtitle">
                                Submit your admission data, upload required documents, and continue tracking your progress from the applicant portal.
                            </p>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('admission.status') }}" class="btn btn-light">
                                    <i class="fas fa-magnifying-glass me-2"></i> Check Status
                                </a>
                                <a href="{{ route('auth.signin-index') }}" class="btn btn-outline-light">
                                    <i class="fas fa-right-to-bracket me-2"></i> Login
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <div class="text-white-50 small">Current Intake</div>
                                        <div class="h3 text-white mb-0">{{ $period?->name ?? 'No active intake' }}</div>
                                    </div>
                                    <span class="badge bg-white text-primary">Public Portal</span>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="hero-mini-stat">
                                            <span>{{ $stats['study_programs'] ?? 0 }}</span>
                                            <small>Programs</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="hero-mini-stat">
                                            <span>{{ $stats['days_left'] ?? 0 }}</span>
                                            <small>Days Left</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <x-alert />

                @if ($submittedApplicationNumber)
                    <div class="admission-success">
                        <div class="success-icon">
                            <i class="fas fa-circle-check"></i>
                        </div>
                        <h2>Application Submitted</h2>
                        <p class="text-muted">Your application number is:</p>
                        <div class="application-number">{{ $submittedApplicationNumber }}</div>
                        <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
                            <a href="{{ route('admission.portal', ['applicationNumber' => $submittedApplicationNumber, 'token' => $submittedAccessToken]) }}" class="btn btn-primary">
                                <i class="fas fa-user-check me-2"></i> Open Applicant Portal
                            </a>
                            <a href="{{ route('admission.status') }}" class="btn btn-outline-primary">
                                <i class="fas fa-magnifying-glass me-2"></i> Status Lookup
                            </a>
                        </div>
                    </div>
                @elseif (! $period)
                    <div class="admission-empty">
                        <div class="empty-icon"><i class="fas fa-calendar-xmark"></i></div>
                        <h2>No Active Admission Period</h2>
                        <p class="text-muted mb-3">Applications are currently closed. Please check again later or contact the campus admission team.</p>
                        <a href="{{ route('admission.status') }}" class="btn btn-outline-primary">
                            <i class="fas fa-magnifying-glass me-2"></i> Check Existing Application
                        </a>
                    </div>
                @else
                    <div class="row g-3 mb-4">
                        <div class="col-md-3 col-6">
                            <div class="admission-stat">
                                <i class="fas fa-calendar-check"></i>
                                <span>{{ $stats['active_periods'] }}</span>
                                <small>Open Intakes</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="admission-stat">
                                <i class="fas fa-graduation-cap"></i>
                                <span>{{ $stats['study_programs'] }}</span>
                                <small>Study Programs</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="admission-stat">
                                <i class="fas fa-file-shield"></i>
                                <span>{{ $stats['requirements'] }}</span>
                                <small>Documents</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="admission-stat">
                                <i class="fas fa-hourglass-half"></i>
                                <span>{{ $stats['days_left'] }}</span>
                                <small>Days Left</small>
                            </div>
                        </div>
                    </div>

                    <div class="admission-shortcuts mb-4">
                        <a href="{{ route('admission.status') }}">
                            <i class="fas fa-location-dot"></i>
                            <span>Track existing application</span>
                        </a>
                        <a href="#documents">
                            <i class="fas fa-folder-open"></i>
                            <span>Review document checklist</span>
                        </a>
                        <a href="#program">
                            <i class="fas fa-building-columns"></i>
                            <span>Choose study program</span>
                        </a>
                    </div>

                    <div class="admission-card mb-3">
                        <div class="admission-card-header">
                            <div>
                                <div class="section-kicker">Step 1</div>
                                <h3>Applicant Identity</h3>
                            </div>
                            <span>{{ $period->opens_at?->format('d M') }} - {{ $period->closes_at?->format('d M Y') }}</span>
                        </div>
                        <div class="admission-card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label>Full Name</label>
                                    <input type="text" class="form-control" wire:model.defer="form.full_name">
                                    @error('form.full_name') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label>Email</label>
                                    <input type="email" class="form-control" wire:model.defer="form.email">
                                    @error('form.email') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label>Phone</label>
                                    <input type="text" class="form-control" wire:model.defer="form.phone">
                                    @error('form.phone') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label>Birth Date</label>
                                    <input type="date" class="form-control" wire:model.defer="form.birth_date">
                                    @error('form.birth_date') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label>Gender</label>
                                    <select class="form-control" wire:model.defer="form.gender">
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label>Class Type</label>
                                    <select class="form-control" wire:model.defer="form.class_type">
                                        <option value="regular">Regular</option>
                                        <option value="evening">Evening</option>
                                        <option value="weekend">Weekend</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label>Address</label>
                                    <textarea class="form-control" rows="3" wire:model.defer="form.address"></textarea>
                                    @error('form.address') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="admission-card mb-3" id="program">
                        <div class="admission-card-header">
                            <div>
                                <div class="section-kicker">Step 2</div>
                                <h3>Program Selection & Education</h3>
                            </div>
                            <span>{{ count($studyPrograms) }} available options</span>
                        </div>
                        <div class="admission-card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label>Faculty</label>
                                    <select class="form-control" wire:model.live="form.faculty_id">
                                        <option value="">Select Faculty</option>
                                        @foreach ($faculties as $faculty)
                                            <option value="{{ $faculty['id'] }}">{{ $faculty['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label>Study Program</label>
                                    <select class="form-control" wire:model.defer="form.study_program_id">
                                        <option value="">Select Study Program</option>
                                        @foreach ($studyPrograms as $studyProgram)
                                            <option value="{{ $studyProgram['id'] }}">{{ $studyProgram['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label>High School</label>
                                    <input type="text" class="form-control" wire:model.defer="form.high_school_name">
                                    @error('form.high_school_name') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label>Major</label>
                                    <input type="text" class="form-control" wire:model.defer="form.high_school_major">
                                    @error('form.high_school_major') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label>Graduation Year</label>
                                    <input type="number" class="form-control" wire:model.defer="form.high_school_graduation_year">
                                    @error('form.high_school_graduation_year') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label>Emergency Contact Name</label>
                                    <input type="text" class="form-control" wire:model.defer="form.emergency_contact_name">
                                    @error('form.emergency_contact_name') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label>Emergency Contact Phone</label>
                                    <input type="text" class="form-control" wire:model.defer="form.emergency_contact_phone">
                                    @error('form.emergency_contact_phone') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="admission-card" id="documents">
                        <div class="admission-card-header">
                            <div>
                                <div class="section-kicker">Step 3</div>
                                <h3>Documents</h3>
                            </div>
                            <span>{{ count($requirements) }} checklist items</span>
                        </div>
                        <div class="admission-card-body">
                            <div class="row g-3">
                                @foreach ($requirements as $requirement)
                                    <div class="col-md-6" wire:key="doc-{{ $requirement['id'] }}">
                                        <div class="document-upload">
                                            <div class="d-flex justify-content-between gap-2 mb-2">
                                                <label class="mb-0">{{ $requirement['label'] }}</label>
                                                @if($requirement['is_required'])
                                                    <span class="badge bg-danger">Required</span>
                                                @else
                                                    <span class="badge bg-secondary">Optional</span>
                                                @endif
                                            </div>
                                            <input type="file" class="form-control" wire:model="documentUploads.{{ $requirement['document_type'] }}">
                                            <small class="text-muted">Max {{ $requirement['max_size_kb'] ?? 2048 }} KB. Allowed: {{ $requirement['allowed_extensions'] ?: 'pdf,jpg,jpeg,png' }}</small>
                                        </div>
                                        @error('documentUploads.'.$requirement['document_type']) <div class="text-danger">{{ $message }}</div> @enderror
                                    </div>
                                @endforeach
                            </div>
                            <div class="d-flex justify-content-end mt-4">
                                <button class="btn btn-primary btn-lg" wire:click="submit" wire:loading.attr="disabled" wire:target="submit,documentUploads">
                                    <i class="fas fa-paper-plane me-2" wire:loading.remove></i>
                                    <span wire:loading.remove>Submit Application</span>
                                    <span wire:loading>Submitting...</span>
                                </button>
                            </div>
                            <div class="text-muted text-end mt-2" wire:loading wire:target="documentUploads">
                                Uploading documents, please wait...
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
    .admission-public {
        background: linear-gradient(180deg, #f7f3ff 0%, #ffffff 42%, #f8fafc 100%);
        min-height: calc(100vh - 120px);
    }

    .admission-hero {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 58%, #16a3b8 100%);
        border-radius: 20px;
        color: #fff;
        overflow: hidden;
        padding: 2rem;
        box-shadow: 0 22px 55px rgba(102, 126, 234, 0.16);
    }

    .admission-kicker,
    .section-kicker {
        font-size: .76rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .admission-kicker {
        color: rgba(255, 255, 255, .72);
    }

    .admission-title {
        font-size: clamp(2rem, 4vw, 3.6rem);
        line-height: 1.05;
        margin: .5rem 0 1rem;
        letter-spacing: 0;
    }

    .admission-subtitle {
        color: rgba(255, 255, 255, .82);
        font-size: 1rem;
        max-width: 42rem;
    }

    .admission-hero-panel,
    .hero-mini-stat {
        background: rgba(255, 255, 255, .14);
        border: 1px solid rgba(255, 255, 255, .22);
        border-radius: 16px;
        backdrop-filter: blur(10px);
    }

    .admission-hero-panel {
        padding: 1.25rem;
    }

    .hero-mini-stat {
        padding: 1rem;
    }

    .hero-mini-stat span {
        display: block;
        font-size: 1.8rem;
        font-weight: 800;
    }

    .hero-mini-stat small {
        color: rgba(255, 255, 255, .76);
    }

    .admission-stat,
    .admission-card,
    .admission-shortcuts,
    .admission-success,
    .admission-empty {
        background: #fff;
        border: 1px solid rgba(102, 126, 234, .12);
        border-radius: 16px;
        box-shadow: 0 12px 30px rgba(0, 0, 0, .06);
    }

    .admission-stat {
        padding: 1rem;
        min-height: 116px;
    }

    .admission-stat i {
        color: #667eea;
        font-size: 1.15rem;
        margin-bottom: .8rem;
    }

    .admission-stat span {
        display: block;
        font-size: 1.7rem;
        font-weight: 800;
        color: #1f2937;
    }

    .admission-stat small {
        color: #64748b;
        font-weight: 600;
    }

    .admission-shortcuts {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        overflow: hidden;
    }

    .admission-shortcuts a {
        display: flex;
        align-items: center;
        gap: .7rem;
        padding: 1rem;
        color: #334155;
        text-decoration: none;
        border-right: 1px solid #edf2f7;
        font-weight: 700;
    }

    .admission-shortcuts a:last-child {
        border-right: 0;
    }

    .admission-shortcuts i {
        color: #667eea;
    }

    .admission-card-header {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.15rem 1.25rem;
        border-bottom: 1px solid #edf2f7;
    }

    .admission-card-header h3 {
        margin: 0;
        font-size: 1.12rem;
    }

    .admission-card-header > span {
        color: #64748b;
        font-weight: 600;
        font-size: .875rem;
    }

    .section-kicker {
        color: #667eea;
    }

    .admission-card-body {
        padding: 1.25rem;
    }

    .document-upload {
        border: 1px dashed #cbd5e1;
        border-radius: 12px;
        padding: 1rem;
        background: #f8fafc;
        height: 100%;
    }

    .admission-success,
    .admission-empty {
        text-align: center;
        padding: 3rem 1.5rem;
    }

    .success-icon,
    .empty-icon {
        width: 72px;
        height: 72px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin-bottom: 1rem;
        font-size: 2rem;
    }

    .success-icon {
        background: #dcfce7;
        color: #16a34a;
    }

    .empty-icon {
        background: #fef3c7;
        color: #d97706;
    }

    .application-number {
        display: inline-block;
        padding: .65rem 1rem;
        border-radius: 10px;
        background: #f1f5f9;
        font-size: 1.5rem;
        font-weight: 800;
        color: #334155;
    }

    @media (max-width: 767.98px) {
        .admission-hero {
            padding: 1.5rem;
        }

        .admission-shortcuts {
            grid-template-columns: 1fr;
        }

        .admission-shortcuts a {
            border-right: 0;
            border-bottom: 1px solid #edf2f7;
        }

        .admission-card-header {
            flex-direction: column;
        }
    }
</style>
