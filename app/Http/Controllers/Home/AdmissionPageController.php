<?php

namespace App\Http\Controllers\Home;

use App\Mail\AdmissionApplicationSubmitted;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionPeriod;
use App\Models\Financial\TuitionFee;
use App\Models\Publication\Faq;
use App\Models\Settings\Campus;
use App\Models\Settings\System;
use App\Support\Admission\AdmissionNumberService;
use App\Support\Inertia\PublicUser;
use App\Enums\FaqType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AdmissionPageController extends \App\Http\Controllers\Controller
{
    public function apply(Request $request): Response
    {
        $period = $this->openPeriod();
        $requirements = $this->periodRequirements($period);
        $faculties = Faculty::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $studyPrograms = StudyProgram::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'faculty_id', 'name']);

        return $this->render($request, 'Home/Admission/Apply', [
            'period' => $period ? [
                'id' => $period->id,
                'name' => $period->name,
                'code' => $period->code,
                'opensAt' => $period->opens_at?->format('d M'),
                'closesAt' => $period->closes_at?->format('d M Y'),
            ] : null,
            'faculties' => $faculties->map(fn (Faculty $faculty) => ['id' => $faculty->id, 'name' => $faculty->name])->values()->all(),
            'studyPrograms' => $studyPrograms->map(fn (StudyProgram $program) => [
                'id' => $program->id,
                'facultyId' => $program->faculty_id,
                'name' => $program->name,
            ])->values()->all(),
            'requirements' => $requirements,
            'stats' => [
                'activePeriods' => AdmissionPeriod::where('is_active', true)->where('is_published', true)->count(),
                'studyPrograms' => StudyProgram::where('is_active', true)->count(),
                'requirements' => count($requirements),
                'daysLeft' => $period ? max(0, now()->startOfDay()->diffInDays($period->closes_at, false)) : 0,
            ],
        ]);
    }

    public function store(Request $request, AdmissionNumberService $numberService): RedirectResponse
    {
        $period = $this->openPeriod();
        abort_unless($period, 404);

        $requirements = $this->periodRequirements($period);

        $rules = [
            'fullName' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:40',
            'birthDate' => 'required|date',
            'gender' => 'required|in:male,female',
            'address' => 'required|string',
            'emergencyContactName' => 'required|string|max:255',
            'emergencyContactPhone' => 'required|string|max:40',
            'highSchoolName' => 'required|string|max:255',
            'highSchoolMajor' => 'required|string|max:255',
            'highSchoolGraduationYear' => 'required|integer|min:1980|max:'.(now()->year + 1),
            'facultyId' => ['nullable', Rule::exists('faculties', 'id')],
            'studyProgramId' => ['nullable', Rule::exists('study_programs', 'id')],
            'classType' => ['nullable', Rule::in(['regular', 'evening', 'weekend'])],
        ];

        foreach ($requirements as $requirement) {
            $rules['documents.'.$requirement['documentType']] = ($requirement['isRequired'] ? 'required' : 'nullable').'|file';
        }

        $validated = $request->validate($rules);

        foreach ($requirements as $requirement) {
            $file = $request->file('documents.'.$requirement['documentType']);

            if (! $file) {
                continue;
            }

            $uploadError = $this->validateDocumentUpload($file, $requirement);

            if ($uploadError) {
                return back()
                    ->withInput()
                    ->withErrors(['documents.'.$requirement['documentType'] => $uploadError]);
            }
        }

        $application = AdmissionApplication::create([
            'full_name' => $validated['fullName'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'birth_date' => $validated['birthDate'],
            'gender' => $validated['gender'],
            'address' => $validated['address'],
            'emergency_contact_name' => $validated['emergencyContactName'],
            'emergency_contact_phone' => $validated['emergencyContactPhone'],
            'high_school_name' => $validated['highSchoolName'],
            'high_school_major' => $validated['highSchoolMajor'],
            'high_school_graduation_year' => $validated['highSchoolGraduationYear'],
            'faculty_id' => $validated['facultyId'] ?? null,
            'study_program_id' => $validated['studyProgramId'] ?? null,
            'class_type' => $validated['classType'] ?? null,
            'admission_period_id' => $period->id,
            'application_number' => $numberService->generate($period),
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

        foreach ($requirements as $requirement) {
            $file = $request->file('documents.'.$requirement['documentType']);

            if (! $file) {
                continue;
            }

            $path = $file->store('admission/'.$application->application_number, 'public');

            $application->documents()->create([
                'document_requirement_id' => $requirement['id'],
                'document_type' => $requirement['documentType'],
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $this->uploadedFileSize($file),
                'verification_status' => 'pending',
            ]);
        }

        try {
            Mail::to($application->email)->send(
                new AdmissionApplicationSubmitted($application->load(['period', 'studyProgram']), route('root.admission.portal', [
                    'applicationNumber' => $application->application_number,
                    'token' => $application->access_token,
                ])),
            );
        } catch (Throwable $exception) {
            Log::warning('Admission application email failed.', [
                'application_id' => $application->id,
                'email' => $application->email,
                'message' => $exception->getMessage(),
            ]);
        }

        session()->flash('success', 'Pendaftaran berhasil dikirim. Nomor pendaftaran Anda: '.$application->application_number);

        return redirect()->route('root.admission.portal', [
            'applicationNumber' => $application->application_number,
            'token' => $application->access_token,
        ]);
    }

    public function status(Request $request): Response
    {
        return $this->render($request, 'Home/Admission/Status', [
            'stats' => [
                'openIntakes' => AdmissionPeriod::query()
                    ->where('is_active', true)
                    ->where('is_published', true)
                    ->whereDate('opens_at', '<=', now())
                    ->whereDate('closes_at', '>=', now())
                    ->count(),
                'studyPrograms' => StudyProgram::query()->where('is_active', true)->count(),
                'submitted' => AdmissionApplication::query()->where('status', 'submitted')->count(),
                'underReview' => AdmissionApplication::query()->where('status', 'under_review')->count(),
            ],
        ]);
    }

    public function checkStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'applicationNumber' => 'required|string',
            'email' => 'required|email',
        ]);

        $application = AdmissionApplication::query()
            ->where('application_number', $validated['applicationNumber'])
            ->where('email', $validated['email'])
            ->first();

        if (! $application) {
            return back()->withErrors([
                'applicationNumber' => 'Nomor pendaftaran atau email tidak ditemukan di dalam sistem.',
            ]);
        }

        return redirect()->route('root.admission.portal', [
            'applicationNumber' => $application->application_number,
            'token' => $application->access_token,
        ]);
    }

    public function portal(Request $request, string $applicationNumber, string $token): Response
    {
        $application = $this->findApplicationOrFail($applicationNumber, $token);
        $requirements = $this->periodRequirements($application->period);

        $requiredTypes = collect($requirements)->filter(fn (array $requirement) => $requirement['isRequired'])->pluck('documentType');
        $uploadedRequired = $application->documents->whereIn('document_type', $requiredTypes)->count();

        return $this->render($request, 'Home/Admission/Portal', [
            'application' => [
                'applicationNumber' => $application->application_number,
                'fullName' => $application->full_name,
                'status' => str($application->status)->replace('_', ' ')->title(),
                'statusRaw' => $application->status,
                'email' => $application->email,
                'phone' => $application->phone,
                'classType' => ucfirst((string) $application->class_type),
                'periodName' => $application->period?->name,
                'programName' => $application->studyProgram?->name ?? '-',
                'finalScore' => $application->final_score !== null ? (float) $application->final_score : null,
                'nim' => $application->user?->studentProfile?->nim,
                'convertedAt' => $application->converted_at?->format('d M Y'),
            ],
            'requirements' => $requirements,
            'documents' => $application->documents->map(fn ($document) => [
                'id' => $document->id,
                'documentType' => $document->document_type,
                'fileName' => $document->file_name,
                'fileSizeKb' => number_format($document->file_size / 1024, 1),
                'verificationStatus' => $document->verification_status,
                'verificationNotes' => $document->verification_notes,
                'previewUrl' => route('root.admission.documents.preview', [
                    'applicationNumber' => $application->application_number,
                    'token' => $application->access_token,
                    'document' => $document->id,
                ]),
                'isImage' => in_array(strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true),
                'canPreview' => in_array(strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'], true),
            ])->values()->all(),
            'examSessions' => $application->examParticipants->map(fn ($participant) => [
                'id' => $participant->id,
                'title' => $participant->schedule?->title,
                'examType' => $participant->schedule?->exam_type ? str($participant->schedule->exam_type)->replace('_', ' ')->title() : '-',
                'examDate' => $participant->schedule?->exam_date?->format('d F Y'),
                'examTime' => $participant->schedule?->exam_time?->format('H:i'),
                'venue' => $participant->schedule?->venue ?? 'Online / TBA',
                'meetingLink' => $participant->schedule?->meeting_link,
                'attendanceStatus' => ucfirst((string) $participant->attendance_status),
            ])->values()->all(),
            'scores' => $application->scores->map(fn ($score) => [
                'id' => $score->id,
                'type' => str($score->score_type)->replace('_', ' ')->title(),
                'weight' => $score->weight,
                'score' => $score->score,
            ])->values()->all(),
            'timeline' => $application->statusHistories
                ->sortByDesc('created_at')
                ->map(fn ($history) => [
                    'id' => $history->id,
                    'fromStatus' => $history->from_status ?: 'new',
                    'toStatus' => $history->to_status,
                    'notes' => $history->notes,
                    'createdAt' => $history->created_at?->format('d M Y, H:i'),
                ])
                ->values()
                ->all(),
            'stats' => [
                'verifiedDocuments' => $application->documents->where('verification_status', 'verified')->count(),
                'uploadedDocuments' => $application->documents->count(),
                'completion' => min(100, (int) round(($uploadedRequired / max(1, $requiredTypes->count())) * 100)),
                'timelineItems' => $application->statusHistories->count(),
                'assignedSessions' => $application->examParticipants->count(),
            ],
        ]);
    }

    public function uploadDocument(Request $request, string $applicationNumber, string $token): RedirectResponse
    {
        $application = $this->findApplicationOrFail($applicationNumber, $token);
        $requirements = collect($this->periodRequirements($application->period));
        $requirement = $requirements->firstWhere('id', (int) $request->input('requirementId'));
        abort_unless($requirement, 404);

        $request->validate(['document' => 'required|file']);

        $file = $request->file('document');
        $uploadError = $this->validateDocumentUpload($file, $requirement);

        if ($uploadError) {
            return back()->withErrors(['document.'.$requirement['documentType'] => $uploadError]);
        }

        $existing = $application->documents()->where('document_requirement_id', $requirement['id'])->first();

        if ($existing && $existing->file_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($existing->file_path);
        }

        $path = $file->store('admission/'.$application->application_number, 'public');

        $application->documents()->updateOrCreate(
            ['document_requirement_id' => $requirement['id']],
            [
                'document_type' => $requirement['documentType'],
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $this->uploadedFileSize($file),
                'verification_status' => 'pending',
                'verification_notes' => null,
                'verified_by' => null,
                'verified_at' => null,
            ],
        );

        session()->flash('success', 'Dokumen berhasil diunggah.');

        return back();
    }

    public function requirements(Request $request): Response
    {
        $periods = AdmissionPeriod::query()
            ->with(['documentRequirements' => fn ($query) => $query->orderBy('sort_order')])
            ->where('is_published', true)
            ->get();

        return $this->render($request, 'Home/Admission/Requirements', [
            'stats' => [
                'openPeriods' => AdmissionPeriod::where('is_active', true)->where('is_published', true)->count(),
                'totalPrograms' => StudyProgram::where('is_active', true)->count(),
            ],
            'periods' => $periods->map(fn (AdmissionPeriod $period) => [
                'id' => $period->id,
                'name' => $period->name,
                'code' => $period->code,
                'description' => $period->description,
                'opensAt' => $period->opens_at?->format('d M Y'),
                'closesAt' => $period->closes_at?->format('d M Y'),
                'isActive' => (bool) $period->is_active,
                'requirements' => $period->documentRequirements->map(fn ($requirement) => [
                    'label' => $requirement->label,
                    'isRequired' => (bool) $requirement->is_required,
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }

    public function tuition(Request $request): Response
    {
        $tuitions = TuitionFee::query()
            ->with(['studyProgram.faculty'])
            ->where('is_active', true)
            ->orderBy('study_program_id')
            ->get()
            ->groupBy('study_program_id')
            ->map(fn ($fees) => [
                'programName' => $fees->first()->studyProgram?->name ?? 'Program Studi Tidak Diketahui',
                'programCode' => $fees->first()->studyProgram?->code ?? '-',
                'facultyName' => $fees->first()->studyProgram?->faculty?->name ?? '-',
                'baseFee' => (float) $fees->first()->base_fee,
                'labFee' => (float) $fees->first()->lab_fee,
                'libraryFee' => (float) $fees->first()->library_fee,
                'activityFee' => (float) $fees->first()->activity_fee,
                'total' => $fees->first()->totalAmount(),
                'deadline' => $fees->first()->payment_deadline?->format('d M Y') ?? '-',
            ])
            ->values();

        return $this->render($request, 'Home/Admission/Tuition', [
            'tuitions' => $tuitions->all(),
            'lowestTotal' => $tuitions->min('total'),
        ]);
    }

    public function faq(Request $request): Response
    {
        $faqs = Faq::query()
            ->where('is_active', true)
            ->where('type', FaqType::ADMISSION->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Faq $faq) => [
                'id' => $faq->id,
                'category' => $faq->category,
                'question' => $faq->question,
                'answer' => $faq->answer,
            ])
            ->values()
            ->all();

        $categories = collect($faqs)->pluck('category')->unique()->values()->all();

        if ($categories === []) {
            $categories = ['Pendaftaran', 'Biaya', 'Seleksi', 'Dokumen'];
        }

        return $this->render($request, 'Home/Admission/Faq', [
            'faqs' => $faqs,
            'categories' => $categories,
        ]);
    }

    private function openPeriod(): ?AdmissionPeriod
    {
        return AdmissionPeriod::query()
            ->where('is_active', true)
            ->where('is_published', true)
            ->whereDate('opens_at', '<=', now())
            ->whereDate('closes_at', '>=', now())
            ->orderByDesc('opens_at')
            ->first();
    }

    private function findApplicationOrFail(string $applicationNumber, string $token): AdmissionApplication
    {
        return AdmissionApplication::query()
            ->with([
                'period.documentRequirements',
                'faculty',
                'studyProgram',
                'documents',
                'examParticipants.schedule',
                'scores',
                'user.studentProfile',
                'statusHistories',
            ])
            ->where('application_number', $applicationNumber)
            ->where('access_token', $token)
            ->firstOrFail();
    }

    private function periodRequirements(?AdmissionPeriod $period): array
    {
        return $period?->documentRequirements()
            ->orderBy('sort_order')
            ->get(['id', 'document_type', 'label', 'is_required', 'allowed_extensions', 'max_size_kb'])
            ->map(fn ($requirement) => [
                'id' => $requirement->id,
                'documentType' => $requirement->document_type,
                'label' => $requirement->label,
                'isRequired' => (bool) $requirement->is_required,
                'allowedExtensions' => strtoupper((string) ($requirement->allowed_extensions ?: 'pdf,jpg,jpeg,png')),
                'maxSizeKb' => (int) ($requirement->max_size_kb ?: 2048),
            ])
            ->values()
            ->all() ?? [];
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

    private function validateDocumentUpload($file, array $requirement): ?string
    {
        $fileSize = $this->uploadedFileSize($file);
        $maxBytes = ((int) ($requirement['maxSizeKb'] ?: 2048)) * 1024;

        if ($fileSize <= 0) {
            return 'File belum selesai diunggah. Tunggu sebentar lalu submit lagi.';
        }

        if ($fileSize > $maxBytes) {
            return 'Ukuran file melebihi batas '.number_format($maxBytes / 1024).' KB.';
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = $this->allowedExtensions($requirement['allowedExtensions'] ?? null);

        if (! in_array($extension, $allowedExtensions, true)) {
            return 'Tipe file tidak diizinkan. Format yang diperbolehkan: '.implode(', ', $allowedExtensions).'.';
        }

        $allowedMimeTypes = $this->allowedMimeTypes($allowedExtensions);
        $mimeType = $file->getMimeType();

        if ($mimeType && ! in_array($mimeType, $allowedMimeTypes, true)) {
            return 'Isi file tidak sesuai format yang diperbolehkan.';
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

    private function render(Request $request, string $component, array $props = []): Response
    {
        $user = $request->user();
        $dashboardRoute = $user ? $user->prefix.'dashboard.index' : null;
        $dashboardUrl = $dashboardRoute && Route::has($dashboardRoute) ? route($dashboardRoute) : route('auth.select-role');

        return Inertia::render($component, array_merge([
            'campus' => [
                'name' => Campus::value('name') ?? config('app.name', 'NexaCampus'),
                'logo' => Campus::value('logo_horizontal') ?? asset('storage/images/default/logo-horizontal.png'),
                'description' => System::value('app_description') ?? 'Sistem informasi akademik perguruan tinggi terpadu.',
            ],
            'user' => PublicUser::make($user, $dashboardUrl),
            'links' => [
                'login' => route('auth.signin-index'),
                'admission' => route('root.admission.apply'),
                'admissionStatus' => route('root.admission.status'),
                'tuition' => route('root.admission.tuition'),
                'requirements' => route('root.admission.requirements'),
                'faq' => route('root.faq'),
                'contact' => route('root.kontak'),
                'announcements' => route('root.publication.announcements'),
            ],
        ], $props));
    }
}
