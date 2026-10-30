<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\StudentInvoice;
use App\Models\StudentService\GraduationApplication;
use App\Models\StudentService\GraduationDocument;
use App\Models\StudentService\ServiceLetterRequest;
use App\Models\StudentService\ServiceLetterType;
use App\Models\StudentService\StudentComplaint;
use App\Models\StudentService\StudentComplaintCategory;
use App\Models\StudentService\StudentLeaveApplication;
use App\Models\StudentService\StudentTransferRequest;
use App\Support\Inertia\ShellProps;
use App\Support\StudentService\GraduationApplicationService;
use App\Support\StudentService\ServiceLetterRequestService;
use App\Support\StudentService\StudentComplaintService;
use App\Support\StudentService\StudentLeaveApplicationService;
use App\Support\StudentService\StudentTransferRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class StudentServiceController extends Controller
{
    // ---------------------------------------------------------------- Letters
    public function lettersIndex(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();

        $requests = collect();
        $summary = ['total' => 0, 'in_progress' => 0, 'issued' => 0, 'rejected' => 0];

        if ($profile !== null) {
            $requests = ServiceLetterRequest::query()
                ->with('letterType')
                ->where('student_profile_id', $profile->id)
                ->orderByDesc('created_at')
                ->get();

            $summary = [
                'total' => $requests->count(),
                'in_progress' => $requests->whereIn('status', ['submitted', 'under_review', 'revision_requested', 'approved'])->count(),
                'issued' => $requests->where('status', 'issued')->count(),
                'rejected' => $requests->where('status', 'rejected')->count(),
            ];
        }

        return Inertia::render('Student/Services/Letters/Index', [
            'shell' => ShellProps::make($user, 'Student Services', 'Surat Mahasiswa'),
            'hasProfile' => $profile !== null,
            'requests' => $requests->map(fn (ServiceLetterRequest $r) => $this->letterRow($r))->values()->all(),
            'summary' => $summary,
            'urls' => [
                'index' => route('student.student-services.letters'),
                'create' => route('student.student-services.letters.create'),
            ],
        ]);
    }

    public function lettersCreate(Request $request): Response
    {
        $user = $request->user();

        $letterTypes = ServiceLetterType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (ServiceLetterType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'code' => $t->code,
                'description' => $t->description,
                'fulfillmentMode' => $t->fulfillment_mode,
                'requiresAttachment' => (bool) $t->requires_attachment,
                'allowedExtensions' => $t->allowed_extensions,
                'maxFileSizeKb' => $t->max_file_size_kb,
                'requiredFields' => $t->required_fields ?? [],
            ])->values()->all();

        return Inertia::render('Student/Services/Letters/Create', [
            'shell' => ShellProps::make($user, 'Student Services', 'Ajukan Surat'),
            'hasProfile' => $user->studentProfile()->exists(),
            'letterTypes' => $letterTypes,
            'urls' => [
                'index' => route('student.student-services.letters'),
                'store' => route('student.student-services.letters.store'),
            ],
        ]);
    }

    public function lettersStore(Request $request): RedirectResponse
    {
        $profile = $request->user()->studentProfile()->first();
        abort_unless($profile !== null, 422, 'Student profile belum tersedia.');

        $selectedType = ServiceLetterType::findOrFail($request->input('form.service_letter_type_id'));

        $rules = [
            'form.service_letter_type_id' => ['required', Rule::exists('service_letter_types', 'id')],
            'form.purpose' => ['required', 'string', 'max:1000'],
            'form.student_notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => array_values(array_filter([
                $selectedType->requires_attachment ? 'required' : 'nullable',
                'file',
                $selectedType->allowed_extensions ? 'mimes:'.$selectedType->allowed_extensions : null,
                'max:'.($selectedType->max_file_size_kb ?: 2048),
            ])),
        ];

        foreach (($selectedType->required_fields ?? []) as $field) {
            if ($field !== 'purpose') {
                $rules['form.request_data.'.$field] = ['required', 'string', 'max:1000'];
            }
        }

        $validated = $request->validate($rules);

        try {
            $letterRequest = app(ServiceLetterRequestService::class)->create(
                $selectedType,
                $profile,
                $validated['form'],
                $request->file('attachment')
            );
            return redirect()->route('student.student-services.letters.show', ['id' => $letterRequest->id])
                ->with('success', 'Pengajuan surat berhasil dikirim.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function lettersEdit(Request $request, int $id): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();
        abort_unless($profile !== null, 404);

        $letterRequest = ServiceLetterRequest::with('letterType')
            ->where('student_profile_id', $profile->id)
            ->findOrFail($id);

        abort_unless($letterRequest->status === 'revision_requested', 403);

        return Inertia::render('Student/Services/Letters/Edit', [
            'shell' => ShellProps::make($user, 'Student Services', 'Perbaiki Pengajuan Surat'),
            'request' => $this->letterDetail($letterRequest),
            'urls' => [
                'index' => route('student.student-services.letters'),
                'show' => route('student.student-services.letters.show', ['id' => $letterRequest->id]),
                'update' => route('student.student-services.letters.update', ['id' => $letterRequest->id]),
            ],
        ]);
    }

    public function lettersUpdate(Request $request, int $id): RedirectResponse
    {
        $profile = $request->user()->studentProfile()->first();
        abort_unless($profile !== null, 404);

        $letterRequest = ServiceLetterRequest::with('letterType')
            ->where('student_profile_id', $profile->id)
            ->findOrFail($id);

        abort_unless($letterRequest->status === 'revision_requested', 403);

        $letterType = $letterRequest->letterType;

        $attachmentRequired = $letterType->requires_attachment && blank($letterRequest->attachment_path);
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

        $validated = $request->validate($rules);

        try {
            $updated = app(ServiceLetterRequestService::class)->resubmit(
                $letterRequest,
                $validated['form'],
                $request->file('attachment')
            );
            return redirect()->route('student.student-services.letters.show', ['id' => $updated->id])
                ->with('success', 'Perbaikan pengajuan berhasil dikirim ulang.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function lettersShow(Request $request, int $id): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();
        abort_unless($profile !== null, 404);

        $letterRequest = ServiceLetterRequest::with([
            'letterType',
            'histories.changedBy',
            'approvalRequest.steps.approverUser',
            'approvalRequest.steps.organizationalPosition',
            'approvalRequest.steps.workUnit',
        ])
            ->where('student_profile_id', $profile->id)
            ->findOrFail($id);

        return Inertia::render('Student/Services/Letters/Show', [
            'shell' => ShellProps::make($user, 'Student Services', 'Detail Surat'),
            'request' => $this->letterDetail($letterRequest),
            'urls' => [
                'index' => route('student.student-services.letters'),
                'edit' => $letterRequest->status === 'revision_requested'
                    ? route('student.student-services.letters.edit', ['id' => $letterRequest->id])
                    : null,
                'download' => $letterRequest->isDownloadable()
                    ? route('student.student-services.letters.download', ['request' => $letterRequest->id])
                    : null,
            ],
        ]);
    }

    private function letterRow(ServiceLetterRequest $r): array
    {
        return [
            'id' => $r->id,
            'number' => $r->request_number,
            'type' => $r->letterType?->name ?? '-',
            'purpose' => $r->purpose,
            'status' => $r->status,
            'statusLabel' => $this->statusLabel($r->status),
            'submittedAt' => $r->created_at?->format('d M Y H:i'),
            'downloadUrl' => $r->isDownloadable()
                ? route('student.student-services.letters.download', ['request' => $r->id])
                : null,
            'url' => route('student.student-services.letters.show', ['id' => $r->id]),
        ];
    }

    private function letterDetail(ServiceLetterRequest $r): array
    {
        return [
            'id' => $r->id,
            'number' => $r->request_number,
            'type' => $r->letterType?->name ?? '-',
            'typeDescription' => $r->letterType?->description,
            'fulfillmentMode' => $r->letterType?->fulfillment_mode,
            'purpose' => $r->purpose,
            'status' => $r->status,
            'statusLabel' => $this->statusLabel($r->status),
            'requestData' => collect($r->request_data ?? [])->except(['purpose'])->all(),
            'studentNotes' => $r->student_notes,
            'adminNotes' => $r->admin_notes,
            'attachmentPath' => $r->attachment_path,
            'attachmentUrl' => $r->attachment_path ? Storage::disk('public')->url($r->attachment_path) : null,
            'finalFilePath' => $r->finalFilePath(),
            'isDownloadable' => $r->isDownloadable(),
            'submittedAt' => $r->created_at?->format('d M Y H:i'),
            'updatedAt' => $r->updated_at?->format('d M Y H:i'),
            'issuedAt' => $r->issued_at?->format('d M Y H:i'),
            'histories' => $r->histories->map(fn ($h) => [
                'id' => $h->id,
                'from' => $h->from_status,
                'to' => $h->to_status,
                'notes' => $h->notes,
                'changedBy' => $h->changedBy?->name,
                'changedAt' => $h->created_at?->format('d M Y H:i'),
            ])->values()->all(),
            'approval' => $r->approvalRequest ? [
                'status' => $r->approvalRequest->status,
                'waitingMessage' => method_exists($r->approvalRequest, 'waitingMessage') ? $r->approvalRequest->waitingMessage() : null,
            ] : null,
        ];
    }

    // ---------------------------------------------------------------- Leaves
    public function leavesIndex(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();

        $applications = collect();
        $summary = ['total' => 0, 'in_progress' => 0, 'active' => 0, 'returned' => 0];

        if ($profile !== null) {
            $applications = StudentLeaveApplication::query()
                ->with('academicYear')
                ->where('student_profile_id', $profile->id)
                ->orderByDesc('created_at')
                ->get();

            $summary = [
                'total' => $applications->count(),
                'in_progress' => $applications->whereIn('status', ['submitted', 'under_review', 'revision_requested', 'approved', 'approved_pending_payment'])->count(),
                'active' => $applications->where('status', 'activated')->count(),
                'returned' => $applications->where('status', 'returned')->count(),
            ];
        }

        return Inertia::render('Student/Services/Leaves/Index', [
            'shell' => ShellProps::make($user, 'Student Services', 'Cuti Akademik'),
            'hasProfile' => $profile !== null,
            'applications' => $applications->map(fn (StudentLeaveApplication $a) => [
                'id' => $a->id,
                'number' => $a->application_number,
                'academicYear' => $a->academicYear?->name ?? '-',
                'semester' => $a->semester,
                'duration' => $a->duration_semesters,
                'reasonCategory' => $a->reason_category,
                'reasonLabel' => $this->reasonCategoryLabel((string) $a->reason_category),
                'reason' => $a->reason,
                'leaveFeeAmount' => $a->leave_fee_amount !== null ? (float) $a->leave_fee_amount : null,
                'status' => $a->status,
                'statusLabel' => $this->statusLabel($a->status),
                'submittedAt' => $a->created_at?->format('d M Y H:i'),
                'url' => route('student.student-services.leaves.show', ['id' => $a->id]),
            ])->values()->all(),
            'summary' => $summary,
            'urls' => [
                'index' => route('student.student-services.leaves'),
                'create' => route('student.student-services.leaves.create'),
            ],
        ]);
    }

    public function leavesCreate(Request $request): Response
    {
        $user = $request->user();
        $academicYears = AcademicYear::query()->orderByDesc('start_date')->get()
            ->map(fn (AcademicYear $y) => ['id' => $y->id, 'name' => $y->name])->values()->all();

        return Inertia::render('Student/Services/Leaves/Create', [
            'shell' => ShellProps::make($user, 'Student Services', 'Ajukan Cuti'),
            'hasProfile' => $user->studentProfile()->exists(),
            'academicYears' => $academicYears,
            'currentSemester' => $user->studentProfile?->current_semester ?: 1,
            'urls' => [
                'index' => route('student.student-services.leaves'),
                'store' => route('student.student-services.leaves.store'),
            ],
        ]);
    }

    public function leavesStore(Request $request): RedirectResponse
    {
        $profile = $request->user()->studentProfile()->first();
        abort_unless($profile !== null, 422, 'Student profile belum tersedia.');

        $validated = $request->validate([
            'form.academic_year_id' => ['required', Rule::exists('academic_years', 'id')],
            'form.semester' => ['required', 'integer', 'min:1', 'max:14'],
            'form.duration_semesters' => ['required', 'integer', 'min:1', 'max:2'],
            'form.reason_category' => ['required', Rule::in(['personal', 'medical', 'financial', 'family', 'work', 'other'])],
            'form.reason' => ['required', 'string', 'max:3000'],
            'form.student_notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        try {
            $application = app(StudentLeaveApplicationService::class)->create(
                $profile,
                $validated['form'],
                $request->file('attachment')
            );
            return redirect()->route('student.student-services.leaves.show', ['id' => $application->id])
                ->with('success', 'Pengajuan cuti berhasil dikirim.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function leavesEdit(Request $request, int $id): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();
        abort_unless($profile !== null, 404);

        $application = StudentLeaveApplication::where('student_profile_id', $profile->id)->findOrFail($id);
        abort_unless($application->status === 'revision_requested', 403);

        $academicYears = AcademicYear::query()->orderByDesc('start_date')->get()
            ->map(fn (AcademicYear $y) => ['id' => $y->id, 'name' => $y->name])->values()->all();

        return Inertia::render('Student/Services/Leaves/Edit', [
            'shell' => ShellProps::make($user, 'Student Services', 'Perbaiki Pengajuan Cuti'),
            'application' => $this->leaveDetail($application),
            'academicYears' => $academicYears,
            'urls' => [
                'index' => route('student.student-services.leaves'),
                'show' => route('student.student-services.leaves.show', ['id' => $application->id]),
                'update' => route('student.student-services.leaves.update', ['id' => $application->id]),
            ],
        ]);
    }

    public function leavesUpdate(Request $request, int $id): RedirectResponse
    {
        $profile = $request->user()->studentProfile()->first();
        abort_unless($profile !== null, 404);

        $application = StudentLeaveApplication::where('student_profile_id', $profile->id)->findOrFail($id);
        abort_unless($application->status === 'revision_requested', 403);

        $validated = $request->validate([
            'form.academic_year_id' => ['required', Rule::exists('academic_years', 'id')],
            'form.semester' => ['required', 'integer', 'min:1', 'max:14'],
            'form.duration_semesters' => ['required', 'integer', 'min:1', 'max:2'],
            'form.reason_category' => ['required', Rule::in(['personal', 'medical', 'financial', 'family', 'work', 'other'])],
            'form.reason' => ['required', 'string', 'max:3000'],
            'form.student_notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        try {
            $updated = app(StudentLeaveApplicationService::class)->resubmit(
                $application,
                $validated['form'],
                $request->file('attachment')
            );
            return redirect()->route('student.student-services.leaves.show', ['id' => $updated->id])
                ->with('success', 'Perbaikan pengajuan cuti berhasil dikirim ulang.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function leavesShow(Request $request, int $id): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();
        abort_unless($profile !== null, 404);

        $application = StudentLeaveApplication::with([
            'academicYear',
            'histories.changedBy',
            'leaveFeeInvoice',
            'approvalRequest.steps.approverUser',
            'approvalRequest.steps.organizationalPosition',
            'approvalRequest.steps.workUnit',
        ])
            ->where('student_profile_id', $profile->id)
            ->findOrFail($id);

        return Inertia::render('Student/Services/Leaves/Show', [
            'shell' => ShellProps::make($user, 'Student Services', 'Detail Cuti'),
            'application' => $this->leaveDetail($application),
            'urls' => [
                'index' => route('student.student-services.leaves'),
                'edit' => $application->status === 'revision_requested'
                    ? route('student.student-services.leaves.edit', ['id' => $application->id])
                    : null,
            ],
        ]);
    }

    private function leaveDetail(StudentLeaveApplication $a): array
    {
        return [
            'id' => $a->id,
            'number' => $a->application_number,
            'academicYear' => $a->academicYear?->name ?? '-',
            'academicYearId' => $a->academic_year_id,
            'semester' => $a->semester,
            'duration' => $a->duration_semesters,
            'reasonCategory' => $a->reason_category,
            'reasonLabel' => $this->reasonCategoryLabel((string) $a->reason_category),
            'reason' => $a->reason,
            'status' => $a->status,
            'statusLabel' => $this->statusLabel($a->status),
            'studentNotes' => $a->student_notes,
            'adminNotes' => $a->admin_notes,
            'attachmentPath' => $a->attachment_path,
            'attachmentUrl' => $a->attachment_path ? Storage::disk('public')->url($a->attachment_path) : null,
            'leaveFeeAmount' => $a->leave_fee_amount !== null ? (float) $a->leave_fee_amount : null,
            'leaveFeeInvoiceId' => $a->leave_fee_invoice_id,
            'invoice' => $this->invoiceSummary($a->leaveFeeInvoice),
            'submittedAt' => $a->created_at?->format('d M Y H:i'),
            'updatedAt' => $a->updated_at?->format('d M Y H:i'),
            'histories' => $a->histories->map(fn ($h) => [
                'id' => $h->id,
                'from' => $h->from_status,
                'to' => $h->to_status,
                'notes' => $h->notes,
                'changedBy' => $h->changedBy?->name,
                'changedAt' => $h->created_at?->format('d M Y H:i'),
            ])->values()->all(),
            'approval' => $a->approvalRequest ? [
                'status' => $a->approvalRequest->status,
                'waitingMessage' => method_exists($a->approvalRequest, 'waitingMessage') ? $a->approvalRequest->waitingMessage() : null,
            ] : null,
        ];
    }

    // ---------------------------------------------------------------- Transfers
    public function transfersIndex(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();

        $requests = collect();
        $summary = ['total' => 0, 'in_progress' => 0, 'approved' => 0, 'applied' => 0];

        if ($profile !== null) {
            $requests = StudentTransferRequest::query()
                ->with(['fromStudyProgram', 'toStudyProgram'])
                ->where('student_profile_id', $profile->id)
                ->orderByDesc('created_at')
                ->get();

            $summary = [
                'total' => $requests->count(),
                'in_progress' => $requests->whereIn('status', ['submitted', 'under_review', 'revision_requested', 'approved_pending_payment'])->count(),
                'approved' => $requests->where('status', 'approved')->count(),
                'applied' => $requests->where('status', 'applied')->count(),
            ];
        }

        return Inertia::render('Student/Services/Transfers/Index', [
            'shell' => ShellProps::make($user, 'Student Services', 'Pindah Program'),
            'hasProfile' => $profile !== null,
            'requests' => $requests->map(fn (StudentTransferRequest $r) => [
                'id' => $r->id,
                'number' => $r->request_number,
                'fromProgram' => $r->fromStudyProgram?->name ?? '-',
                'toProgram' => $r->toStudyProgram?->name ?? '-',
                'type' => $r->transfer_type,
                'typeLabel' => $this->transferTypeLabel((string) $r->transfer_type),
                'fromClassType' => $r->from_class_type,
                'toClassType' => $r->to_class_type,
                'transferFeeAmount' => $r->transfer_fee_amount !== null ? (float) $r->transfer_fee_amount : null,
                'status' => $r->status,
                'statusLabel' => $this->statusLabel($r->status),
                'submittedAt' => $r->created_at?->format('d M Y H:i'),
                'url' => route('student.student-services.transfers.show', ['id' => $r->id]),
            ])->values()->all(),
            'summary' => $summary,
            'urls' => [
                'index' => route('student.student-services.transfers'),
                'create' => route('student.student-services.transfers.create'),
            ],
        ]);
    }

    public function transfersCreate(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();

        $faculties = Faculty::query()
            ->when($profile?->studyProgram?->faculty_id, fn ($q, $fid) => $q->whereKeyNot($fid))
            ->orderBy('name')
            ->get()
            ->map(fn (Faculty $f) => ['id' => $f->id, 'name' => $f->name])->values()->all();

        $studyPrograms = StudyProgram::query()
            ->with('faculty')
            ->orderBy('name')
            ->get()
            ->map(fn (StudyProgram $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'facultyId' => $p->faculty_id,
                'facultyName' => $p->faculty?->name,
            ])->values()->all();

        $classTypeOptions = [
            ['value' => 'regular', 'label' => 'Kelas Reguler'],
            ['value' => 'evening', 'label' => 'Kelas Sore'],
            ['value' => 'weekend', 'label' => 'Kelas Akhir Pekan'],
        ];

        return Inertia::render('Student/Services/Transfers/Create', [
            'shell' => ShellProps::make($user, 'Student Services', 'Ajukan Pindah Program'),
            'hasProfile' => $profile !== null,
            'currentProgramId' => $profile?->study_program_id,
            'currentClassType' => $profile?->class_type,
            'currentFacultyId' => $profile?->studyProgram?->faculty_id,
            'faculties' => $faculties,
            'studyPrograms' => $studyPrograms,
            'classTypeOptions' => $classTypeOptions,
            'currentSemester' => $profile?->current_semester,
            'urls' => [
                'index' => route('student.student-services.transfers'),
                'store' => route('student.student-services.transfers.store'),
            ],
        ]);
    }

    public function transfersStore(Request $request): RedirectResponse
    {
        $profile = $request->user()->studentProfile()->first();
        abort_unless($profile !== null, 422, 'Student profile belum tersedia.');

        $validated = $request->validate([
            'form.target_faculty_id' => ['nullable', Rule::exists('faculties', 'id')],
            'form.to_study_program_id' => ['required', Rule::exists('study_programs', 'id')],
            'form.to_class_type' => ['nullable', Rule::in(['regular', 'evening', 'weekend'])],
            'form.recommended_semester' => ['nullable', 'integer', 'min:1', 'max:14'],
            'form.transfer_type' => ['required', Rule::in(['study_program', 'faculty', 'class_type', 'other'])],
            'form.reason' => ['required', 'string', 'max:3000'],
            'form.student_notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        try {
            $transferRequest = app(StudentTransferRequestService::class)->create(
                $profile,
                $validated['form'],
                $request->file('attachment')
            );
            return redirect()->route('student.student-services.transfers.show', ['id' => $transferRequest->id])
                ->with('success', 'Pengajuan transfer berhasil dikirim.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function transfersEdit(Request $request, int $id): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();
        abort_unless($profile !== null, 404);

        $transferRequest = StudentTransferRequest::with(['fromStudyProgram', 'toStudyProgram'])
            ->where('student_profile_id', $profile->id)
            ->findOrFail($id);

        abort_unless($transferRequest->status === 'revision_requested', 403);

        $faculties = Faculty::query()
            ->when($profile?->studyProgram?->faculty_id, fn ($q, $fid) => $q->whereKeyNot($fid))
            ->orderBy('name')
            ->get()
            ->map(fn (Faculty $f) => ['id' => $f->id, 'name' => $f->name])->values()->all();

        $studyPrograms = StudyProgram::query()
            ->with('faculty')
            ->orderBy('name')
            ->get()
            ->map(fn (StudyProgram $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'facultyId' => $p->faculty_id,
                'facultyName' => $p->faculty?->name,
            ])->values()->all();

        $classTypeOptions = [
            ['value' => 'regular', 'label' => 'Kelas Reguler'],
            ['value' => 'evening', 'label' => 'Kelas Sore'],
            ['value' => 'weekend', 'label' => 'Kelas Akhir Pekan'],
        ];

        $targetFacultyId = $transferRequest->transfer_type === 'faculty'
            ? $transferRequest->toStudyProgram?->faculty_id
            : null;

        return Inertia::render('Student/Services/Transfers/Edit', [
            'shell' => ShellProps::make($user, 'Student Services', 'Perbaiki Pengajuan Pindah'),
            'request' => $this->transferDetail($transferRequest),
            'targetFacultyId' => $targetFacultyId,
            'faculties' => $faculties,
            'studyPrograms' => $studyPrograms,
            'classTypeOptions' => $classTypeOptions,
            'urls' => [
                'index' => route('student.student-services.transfers'),
                'show' => route('student.student-services.transfers.show', ['id' => $transferRequest->id]),
                'update' => route('student.student-services.transfers.update', ['id' => $transferRequest->id]),
            ],
        ]);
    }

    public function transfersUpdate(Request $request, int $id): RedirectResponse
    {
        $profile = $request->user()->studentProfile()->first();
        abort_unless($profile !== null, 404);

        $transferRequest = StudentTransferRequest::where('student_profile_id', $profile->id)->findOrFail($id);
        abort_unless($transferRequest->status === 'revision_requested', 403);

        $validated = $request->validate([
            'form.target_faculty_id' => ['nullable', Rule::exists('faculties', 'id')],
            'form.to_study_program_id' => ['required', Rule::exists('study_programs', 'id')],
            'form.to_class_type' => ['nullable', Rule::in(['regular', 'evening', 'weekend'])],
            'form.recommended_semester' => ['nullable', 'integer', 'min:1', 'max:14'],
            'form.transfer_type' => ['required', Rule::in(['study_program', 'faculty', 'class_type', 'other'])],
            'form.reason' => ['required', 'string', 'max:3000'],
            'form.student_notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        try {
            $updated = app(StudentTransferRequestService::class)->resubmit(
                $transferRequest,
                $validated['form'],
                $request->file('attachment')
            );
            return redirect()->route('student.student-services.transfers.show', ['id' => $updated->id])
                ->with('success', 'Perbaikan pengajuan transfer berhasil dikirim ulang.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function transfersShow(Request $request, int $id): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();
        abort_unless($profile !== null, 404);

        $transferRequest = StudentTransferRequest::with([
            'fromStudyProgram',
            'toStudyProgram',
            'histories.changedBy',
            'transferFeeInvoice',
            'approvalRequest.steps.approverUser',
            'approvalRequest.steps.organizationalPosition',
            'approvalRequest.steps.workUnit',
        ])
            ->where('student_profile_id', $profile->id)
            ->findOrFail($id);

        return Inertia::render('Student/Services/Transfers/Show', [
            'shell' => ShellProps::make($user, 'Student Services', 'Detail Pindah Program'),
            'request' => $this->transferDetail($transferRequest),
            'urls' => [
                'index' => route('student.student-services.transfers'),
                'edit' => $transferRequest->status === 'revision_requested'
                    ? route('student.student-services.transfers.edit', ['id' => $transferRequest->id])
                    : null,
            ],
        ]);
    }

    private function transferDetail(StudentTransferRequest $r): array
    {
        return [
            'id' => $r->id,
            'number' => $r->request_number,
            'fromProgram' => $r->fromStudyProgram?->name ?? '-',
            'toProgram' => $r->toStudyProgram?->name ?? '-',
            'fromProgramId' => $r->from_study_program_id,
            'toProgramId' => $r->to_study_program_id,
            'transferType' => $r->transfer_type,
            'transferTypeLabel' => $this->transferTypeLabel((string) $r->transfer_type),
            'fromClassType' => $r->from_class_type,
            'toClassType' => $r->to_class_type,
            'fromClassTypeLabel' => $this->classTypeLabel($r->from_class_type),
            'toClassTypeLabel' => $this->classTypeLabel($r->to_class_type),
            'currentSemester' => $r->current_semester,
            'recommendedSemester' => $r->recommended_semester,
            'reason' => $r->reason,
            'status' => $r->status,
            'statusLabel' => $this->statusLabel($r->status),
            'studentNotes' => $r->student_notes,
            'adminNotes' => $r->admin_notes,
            'academicEvaluationNotes' => $r->academic_evaluation_notes,
            'creditMappingNotes' => $r->credit_mapping_notes,
            'financeNotes' => $r->finance_notes,
            'transferFeeAmount' => $r->transfer_fee_amount !== null ? (float) $r->transfer_fee_amount : null,
            'transferFeeDueDate' => $r->transfer_fee_due_date?->format('d M Y'),
            'invoice' => $this->invoiceSummary($r->transferFeeInvoice),
            'attachmentPath' => $r->attachment_path,
            'attachmentUrl' => $r->attachment_path ? Storage::disk('public')->url($r->attachment_path) : null,
            'submittedAt' => $r->created_at?->format('d M Y H:i'),
            'updatedAt' => $r->updated_at?->format('d M Y H:i'),
            'histories' => $r->histories->map(fn ($h) => [
                'id' => $h->id,
                'from' => $h->from_status,
                'to' => $h->to_status,
                'notes' => $h->notes,
                'changedBy' => $h->changedBy?->name,
                'changedAt' => $h->created_at?->format('d M Y H:i'),
            ])->values()->all(),
            'approval' => $r->approvalRequest ? [
                'status' => $r->approvalRequest->status,
                'waitingMessage' => method_exists($r->approvalRequest, 'waitingMessage') ? $r->approvalRequest->waitingMessage() : null,
            ] : null,
        ];
    }

    // ---------------------------------------------------------------- Graduations
    public function graduationsIndex(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();

        $applications = collect();
        $summary = ['total' => 0, 'in_progress' => 0, 'approved' => 0, 'finalized' => 0];

        if ($profile !== null) {
            $applications = GraduationApplication::query()
                ->where('student_profile_id', $profile->id)
                ->orderByDesc('created_at')
                ->get();

            $summary = [
                'total' => $applications->count(),
                'in_progress' => $applications->whereIn('status', ['submitted', 'under_review', 'revision_requested'])->count(),
                'approved' => $applications->where('status', 'approved')->count(),
                'finalized' => $applications->where('status', 'finalized')->count(),
            ];
        }

        return Inertia::render('Student/Services/Graduations/Index', [
            'shell' => ShellProps::make($user, 'Student Services', 'Yudisium'),
            'hasProfile' => $profile !== null,
            'applications' => $applications->map(fn (GraduationApplication $a) => [
                'id' => $a->id,
                'number' => $a->application_number,
                'status' => $a->status,
                'statusLabel' => $this->statusLabel($a->status),
                'graduationPeriod' => $a->graduation_period,
                'graduationDate' => $a->graduation_date?->format('d M Y'),
                'thesisTitle' => $a->thesis_title,
                'submittedAt' => $a->created_at?->format('d M Y H:i'),
                'url' => route('student.student-services.graduations.show', ['id' => $a->id]),
            ])->values()->all(),
            'summary' => $summary,
            'urls' => [
                'index' => route('student.student-services.graduations'),
                'create' => route('student.student-services.graduations.create'),
            ],
        ]);
    }

    public function graduationsCreate(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();

        $service = app(GraduationApplicationService::class);

        $graduationBatches = collect();
        $eligibilityReport = [];
        $canSubmit = false;
        $documentRequirements = collect();

        if ($profile !== null) {
            $graduationBatches = $service->activeGraduationBatches($profile);
            $eligibilityReport = $service->eligibilityReport($profile);
            $canSubmit = (bool) ($eligibilityReport['can_submit'] ?? false);
            $documentRequirements = $service->documentRequirements($profile);
        }

        return Inertia::render('Student/Services/Graduations/Create', [
            'shell' => ShellProps::make($user, 'Student Services', 'Ajukan Yudisium'),
            'hasProfile' => $profile !== null,
            'graduationBatches' => $graduationBatches->map(fn ($b) => [
                'id' => $b->id,
                'label' => ($b->academicPeriod?->name ?? '-') . ' - ' . ($b->yudisium_date?->format('d F Y') ?? '-'),
                'academicPeriod' => $b->academicPeriod?->name,
                'yudisiumDate' => $b->yudisium_date?->format('d F Y'),
            ])->values()->all(),
            'eligibilityReport' => $eligibilityReport,
            'canSubmit' => $canSubmit,
            'documentRequirements' => $documentRequirements->map(fn ($r) => [
                'id' => $r->id,
                'label' => $r->label,
                'description' => $r->description,
                'documentType' => $r->document_type,
                'isRequired' => (bool) $r->is_required,
                'allowedExtensions' => $r->allowedExtensionsList(),
                'maxSizeKb' => $r->max_size_kb,
            ])->values()->all(),
            'urls' => [
                'index' => route('student.student-services.graduations'),
                'store' => route('student.student-services.graduations.store'),
                'preview' => null,
            ],
        ]);
    }

    public function graduationsStore(Request $request): RedirectResponse
    {
        $profile = $request->user()->studentProfile()->first();
        abort_unless($profile !== null, 422, 'Student profile belum tersedia.');

        $service = app(GraduationApplicationService::class);
        $documentRequirements = $service->documentRequirements($profile);

        $rules = [
            'form.graduation_batch_id' => ['required', 'integer', 'exists:graduation_batches,id'],
            'form.thesis_title' => ['nullable', 'string', 'max:255'],
            'form.reason' => ['nullable', 'string', 'max:3000'],
            'form.student_notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];

        foreach ($documentRequirements as $requirement) {
            $rules['documentUploads.'.$requirement->id] = [
                $requirement->is_required ? 'required' : 'nullable',
                'file',
                'mimes:'.implode(',', $requirement->allowedExtensionsList()),
                'max:'.($requirement->max_size_kb ?: 5120),
            ];
        }

        $validated = $request->validate($rules);

        try {
            $application = $service->create(
                $profile,
                $validated['form'],
                $request->file('attachment'),
                $request->file('documentUploads', []) ?: []
            );
            return redirect()->route('student.student-services.graduations.show', ['id' => $application->id])
                ->with('success', 'Pengajuan yudisium berhasil dikirim.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function graduationsEdit(Request $request, int $id): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();
        abort_unless($profile !== null, 404);

        $application = GraduationApplication::with(['documents.requirement', 'graduationBatch.academicPeriod.academicYear', 'graduationBatch.studyProgram'])
            ->where('student_profile_id', $profile->id)
            ->findOrFail($id);

        abort_unless($application->status === 'revision_requested', 403);

        $service = app(GraduationApplicationService::class);
        $graduationBatches = $service->activeGraduationBatches($profile);
        if ($application->graduationBatch && ! $graduationBatches->contains('id', $application->graduationBatch->id)) {
            $graduationBatches->push($application->graduationBatch);
        }
        $eligibilityReport = $service->eligibilityReport($profile, $application->id);
        $canSubmit = (bool) ($eligibilityReport['can_submit'] ?? false);
        $documentRequirements = $service->documentRequirements($profile);

        return Inertia::render('Student/Services/Graduations/Edit', [
            'shell' => ShellProps::make($user, 'Student Services', 'Perbaiki Pengajuan Yudisium'),
            'application' => $this->graduationDetail($application),
            'graduationBatches' => $graduationBatches->map(fn ($b) => [
                'id' => $b->id,
                'label' => ($b->academicPeriod?->name ?? '-') . ' - ' . ($b->yudisium_date?->format('d F Y') ?? '-'),
                'academicPeriod' => $b->academicPeriod?->name,
                'yudisiumDate' => $b->yudisium_date?->format('d F Y'),
            ])->values()->all(),
            'eligibilityReport' => $eligibilityReport,
            'canSubmit' => $canSubmit,
            'documentRequirements' => $documentRequirements->map(fn ($r) => [
                'id' => $r->id,
                'label' => $r->label,
                'description' => $r->description,
                'documentType' => $r->document_type,
                'isRequired' => (bool) $r->is_required,
                'allowedExtensions' => $r->allowedExtensionsList(),
                'maxSizeKb' => $r->max_size_kb,
            ])->values()->all(),
            'existingDocuments' => $application->documents->keyBy('graduation_document_requirement_id')->map(fn (GraduationDocument $d) => [
                'id' => $d->id,
                'requirementId' => $d->graduation_document_requirement_id,
                'fileName' => $d->file_name,
                'verificationStatus' => $d->verification_status,
                'verificationNotes' => $d->verification_notes,
                'previewUrl' => route('student.student-services.graduation-documents.preview', ['document' => $d->id]),
            ])->values()->all(),
            'urls' => [
                'index' => route('student.student-services.graduations'),
                'show' => route('student.student-services.graduations.show', ['id' => $application->id]),
                'update' => route('student.student-services.graduations.update', ['id' => $application->id]),
            ],
        ]);
    }

    public function graduationsUpdate(Request $request, int $id): RedirectResponse
    {
        $profile = $request->user()->studentProfile()->first();
        abort_unless($profile !== null, 404);

        $application = GraduationApplication::with('documents')
            ->where('student_profile_id', $profile->id)
            ->findOrFail($id);

        abort_unless($application->status === 'revision_requested', 403);

        $service = app(GraduationApplicationService::class);
        $documentRequirements = $service->documentRequirements($profile);

        $documentsByRequirement = $application->documents->keyBy('graduation_document_requirement_id');

        $rules = [
            'form.graduation_batch_id' => ['required', 'integer', 'exists:graduation_batches,id'],
            'form.thesis_title' => ['nullable', 'string', 'max:255'],
            'form.reason' => ['nullable', 'string', 'max:3000'],
            'form.student_notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];

        foreach ($documentRequirements as $requirement) {
            $hasExistingValid = $documentsByRequirement->has($requirement->id)
                && $documentsByRequirement->get($requirement->id)->verification_status !== 'rejected';
            $rules['documentUploads.'.$requirement->id] = [
                $requirement->is_required && ! $hasExistingValid ? 'required' : 'nullable',
                'file',
                'mimes:'.implode(',', $requirement->allowedExtensionsList()),
                'max:'.($requirement->max_size_kb ?: 5120),
            ];
        }

        $validated = $request->validate($rules);

        try {
            $updated = $service->resubmit(
                $application,
                $validated['form'],
                $request->file('attachment'),
                $request->file('documentUploads', []) ?: []
            );
            return redirect()->route('student.student-services.graduations.show', ['id' => $updated->id])
                ->with('success', 'Perbaikan pengajuan yudisium berhasil dikirim ulang.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function graduationsShow(Request $request, int $id): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();
        abort_unless($profile !== null, 404);

        $application = GraduationApplication::with([
            'histories.changedBy',
            'academicPeriod',
            'graduationBatch.academicPeriod.academicYear',
            'graduationBatch.studyProgram',
            'documents.requirement',
            'documents.verifiedBy',
            'approvalRequest.steps.approverUser',
            'approvalRequest.steps.organizationalPosition',
            'approvalRequest.steps.workUnit',
        ])
            ->where('student_profile_id', $profile->id)
            ->findOrFail($id);

        $checklistLabels = [
            'transcript_checked' => 'Transkrip dicek',
            'final_project_checked' => 'Tugas akhir dicek',
            'library_clearance' => 'Bebas perpustakaan',
            'lab_clearance' => 'Bebas laboratorium',
            'document_complete' => 'Dokumen lengkap',
        ];

        $checklist = $application->admin_checklist ?? [];
        $checklistAliases = [
            'transcript_checked' => ['transcript_checked', 'transcript'],
            'final_project_checked' => ['final_project_checked', 'thesis'],
            'library_clearance' => ['library_clearance', 'library'],
            'lab_clearance' => ['lab_clearance', 'lab'],
            'document_complete' => ['document_complete', 'documents'],
        ];
        $checklistItems = [];
        foreach ($checklistLabels as $key => $label) {
            $item = [];
            foreach ($checklistAliases[$key] ?? [$key] as $alias) {
                if (array_key_exists($alias, $checklist)) {
                    $item = $checklist[$alias];
                    break;
                }
            }

            $isArray = is_array($item);
            $checklistItems[] = [
                'key' => $key,
                'label' => $label,
                'checked' => $isArray ? (bool) ($item['checked'] ?? false) : (bool) $item,
                'checkedAt' => $isArray ? ($item['checked_at'] ?? null) : null,
                'checkedBy' => $isArray ? ($item['checked_by_name'] ?? null) : null,
                'notes' => $isArray ? ($item['notes'] ?? null) : null,
            ];
        }

        return Inertia::render('Student/Services/Graduations/Show', [
            'shell' => ShellProps::make($user, 'Student Services', 'Detail Yudisium'),
            'application' => $this->graduationDetail($application),
            'checklistItems' => $checklistItems,
            'checklistCompleted' => collect($checklistItems)->where('checked', true)->count(),
            'documents' => $application->documents->map(fn (GraduationDocument $d) => [
                'id' => $d->id,
                'fileName' => $d->file_name,
                'verificationStatus' => $d->verification_status,
                'verificationNotes' => $d->verification_notes,
                'verifiedAt' => $d->verified_at?->format('d M Y H:i'),
                'verifiedBy' => $d->verifiedBy?->name,
                'previewUrl' => route('student.student-services.graduation-documents.preview', ['document' => $d->id]),
                'isPdf' => $d->isPdf(),
                'isImage' => $d->isImage(),
            ])->values()->all(),
            'urls' => [
                'index' => route('student.student-services.graduations'),
                'edit' => $application->status === 'revision_requested'
                    ? route('student.student-services.graduations.edit', ['id' => $application->id])
                    : null,
            ],
        ]);
    }

    private function graduationDetail(GraduationApplication $a): array
    {
        return [
            'id' => $a->id,
            'number' => $a->application_number,
            'status' => $a->status,
            'statusLabel' => $this->statusLabel($a->status),
            'graduationPeriod' => $a->graduation_period,
            'graduationDate' => $a->graduation_date?->format('d M Y'),
            'thesisTitle' => $a->thesis_title,
            'reason' => $a->reason,
            'studentNotes' => $a->student_notes,
            'adminNotes' => $a->admin_notes,
            'attachmentPath' => $a->attachment_path,
            'attachmentUrl' => $a->attachment_path ? Storage::disk('public')->url($a->attachment_path) : null,
            'academicPeriod' => $a->academicPeriod?->name,
            'graduationBatch' => $a->graduationBatch?->name,
            'graduationBatchId' => $a->graduation_batch_id,
            'eligibilitySnapshot' => $a->eligibility_snapshot,
            'submittedAt' => $a->created_at?->format('d M Y H:i'),
            'updatedAt' => $a->updated_at?->format('d M Y H:i'),
            'histories' => $a->histories->map(fn ($h) => [
                'id' => $h->id,
                'from' => $h->from_status,
                'to' => $h->to_status,
                'notes' => $h->notes,
                'changedBy' => $h->changedBy?->name,
                'changedAt' => $h->created_at?->format('d M Y H:i'),
            ])->values()->all(),
            'approval' => $a->approvalRequest ? [
                'status' => $a->approvalRequest->status,
                'waitingMessage' => method_exists($a->approvalRequest, 'waitingMessage') ? $a->approvalRequest->waitingMessage() : null,
            ] : null,
        ];
    }

    // ---------------------------------------------------------------- Complaints
    public function complaintsIndex(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();

        $complaints = collect();
        $summary = ['total' => 0, 'active' => 0, 'waiting' => 0, 'resolved' => 0];

        if ($profile !== null) {
            $complaints = StudentComplaint::with(['category', 'assignedWorkUnit'])
                ->where('student_profile_id', $profile->id)
                ->latest()
                ->get();

            $summary = [
                'total' => $complaints->count(),
                'active' => $complaints->whereNotIn('status', ['resolved', 'closed', 'rejected'])->count(),
                'waiting' => $complaints->where('status', 'waiting_student')->count(),
                'resolved' => $complaints->whereIn('status', ['resolved', 'closed'])->count(),
            ];
        }

        return Inertia::render('Student/Services/Complaints/Index', [
            'shell' => ShellProps::make($user, 'Student Services', 'Pengaduan'),
            'hasProfile' => $profile !== null,
            'complaints' => $complaints->map(fn (StudentComplaint $c) => [
                'id' => $c->id,
                'ticketNumber' => $c->ticket_number,
                'subject' => $c->subject,
                'category' => $c->category?->name ?? '-',
                'priority' => $c->priority,
                'priorityLabel' => $this->priorityLabel((string) $c->priority),
                'status' => $c->status,
                'statusLabel' => $this->statusLabel($c->status),
                'assignedUnit' => $c->assignedWorkUnit?->name,
                'submittedAt' => $c->created_at?->format('d M Y H:i'),
                'dueAt' => $c->due_at?->format('d M Y H:i'),
                'url' => route('student.student-services.complaints.show', ['id' => $c->id]),
            ])->values()->all(),
            'summary' => $summary,
            'urls' => [
                'index' => route('student.student-services.complaints'),
                'create' => route('student.student-services.complaints.create'),
            ],
        ]);
    }

    public function complaintsCreate(Request $request): Response
    {
        $user = $request->user();

        $categories = StudentComplaintCategory::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (StudentComplaintCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
            ])->values()->all();

        return Inertia::render('Student/Services/Complaints/Create', [
            'shell' => ShellProps::make($user, 'Student Services', 'Buat Pengaduan'),
            'hasProfile' => $user->studentProfile()->exists(),
            'categories' => $categories,
            'urls' => [
                'index' => route('student.student-services.complaints'),
                'store' => route('student.student-services.complaints.store'),
            ],
        ]);
    }

    public function complaintsStore(Request $request): RedirectResponse
    {
        $profile = $request->user()->studentProfile()->first();
        abort_unless($profile !== null, 403);

        $validated = $request->validate([
            'form.student_complaint_category_id' => ['required', 'exists:student_complaint_categories,id'],
            'form.subject' => ['required', 'string', 'max:255'],
            'form.description' => ['required', 'string', 'max:5000'],
            'form.priority' => ['required', 'in:low,normal,high,urgent'],
            'attachments' => ['nullable', 'array', 'max:6'],
            'attachments.*' => ['file', 'extensions:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt'],
        ]);

        try {
            $complaint = app(StudentComplaintService::class)->create(
                $profile,
                $validated['form'],
                $request->file('attachments', []) ?: []
            );
            return redirect()->route('student.student-services.complaints.show', ['id' => $complaint->id])
                ->with('success', 'Pengaduan berhasil dikirim.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function complaintsShow(Request $request, int $id): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();
        abort_unless($profile !== null, 404);

        $complaint = StudentComplaint::with(['category', 'assignedWorkUnit', 'messages.user', 'messages.attachments', 'histories.changedBy'])
            ->where('student_profile_id', $profile->id)
            ->findOrFail($id);

        $visibleMessages = $complaint->messages
            ->reject(fn ($m) => (bool) $m->is_internal_note)
            ->values();

        return Inertia::render('Student/Services/Complaints/Show', [
            'shell' => ShellProps::make($user, 'Student Services', 'Detail Pengaduan'),
            'complaint' => [
                'id' => $complaint->id,
                'ticketNumber' => $complaint->ticket_number,
                'subject' => $complaint->subject,
                'description' => $complaint->description,
                'priority' => $complaint->priority,
                'priorityLabel' => $this->priorityLabel((string) $complaint->priority),
                'status' => $complaint->status,
                'statusLabel' => $this->statusLabel($complaint->status),
                'category' => $complaint->category?->name ?? '-',
                'assignedUnit' => $complaint->assignedWorkUnit?->name,
                'submittedAt' => $complaint->created_at?->format('d M Y H:i'),
                'updatedAt' => $complaint->updated_at?->format('d M Y H:i'),
                'dueAt' => $complaint->due_at?->format('d M Y H:i'),
                'messages' => $visibleMessages->map(fn ($m) => [
                    'id' => $m->id,
                    'senderType' => $m->sender_type,
                    'senderName' => $m->user?->name ?? ($m->sender_type === 'student' ? 'Mahasiswa' : 'Staff'),
                    'message' => $m->message,
                    'createdAt' => $m->created_at?->format('d M Y H:i'),
                    'attachments' => $m->attachments->map(fn ($a) => [
                        'id' => $a->id,
                        'fileName' => $a->file_name,
                        'url' => route('student.student-services.complaint-attachments.download', ['attachment' => $a->id]),
                    ])->values()->all(),
                ])->values()->all(),
                'histories' => $complaint->histories->map(fn ($h) => [
                    'id' => $h->id,
                    'from' => $h->from_status,
                    'to' => $h->to_status,
                    'notes' => $h->notes,
                    'changedBy' => $h->changedBy?->name,
                    'changedAt' => $h->created_at?->format('d M Y H:i'),
                ])->values()->all(),
            ],
            'canReply' => ! in_array($complaint->status, ['closed', 'rejected'], true),
            'canClose' => $complaint->status === 'resolved',
            'urls' => [
                'index' => route('student.student-services.complaints'),
                'reply' => route('student.student-services.complaints.reply', ['id' => $complaint->id]),
                'close' => route('student.student-services.complaints.close', ['id' => $complaint->id]),
                'attachmentDownload' => route('student.student-services.complaint-attachments.download', ['attachment' => 0]),
            ],
        ]);
    }

    public function complaintsReply(Request $request, int $id): RedirectResponse
    {
        $profile = $request->user()->studentProfile()->first();
        abort_unless($profile !== null, 404);

        $complaint = StudentComplaint::where('student_profile_id', $profile->id)->findOrFail($id);

        $validated = $request->validate([
            'replyMessage' => ['required', 'string', 'max:5000'],
            'replyAttachments' => ['nullable', 'array', 'max:6'],
            'replyAttachments.*' => ['file', 'extensions:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt'],
        ]);

        try {
            app(StudentComplaintService::class)->addMessage(
                $complaint,
                $request->user()->id,
                'student',
                $validated['replyMessage'],
                $request->file('replyAttachments', []) ?: []
            );
            return redirect()->route('student.student-services.complaints.show', ['id' => $complaint->id])
                ->with('success', 'Balasan berhasil dikirim.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function complaintsClose(Request $request, int $id): RedirectResponse
    {
        $profile = $request->user()->studentProfile()->first();
        abort_unless($profile !== null, 404);

        $complaint = StudentComplaint::where('student_profile_id', $profile->id)->findOrFail($id);

        app(StudentComplaintService::class)->setStatus($complaint, 'closed', 'Ditutup oleh mahasiswa.', $request->user()->id);

        return redirect()->route('student.student-services.complaints.show', ['id' => $complaint->id])
            ->with('success', 'Pengaduan ditutup.');
    }

    // ---------------------------------------------------------------- Labels
    private function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Diajukan',
            'under_review' => 'Sedang Ditinjau',
            'revision_requested' => 'Perlu Perbaikan',
            'in_approval' => 'Menunggu Persetujuan',
            'approved_pending_payment' => 'Menunggu Pembayaran',
            'approved' => 'Disetujui',
            'issued' => 'Diterbitkan',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            'activated' => 'Cuti Aktif',
            'returned' => 'Kembali Aktif',
            'applied' => 'Sudah Diterapkan',
            'finalized' => 'Selesai',
            'in_review' => 'Sedang Direview',
            'waiting_student' => 'Menunggu Kamu',
            'responded' => 'Dibalas',
            'resolved' => 'Selesai',
            'closed' => 'Ditutup',
            'reopened' => 'Dibuka Lagi',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'low' => 'Rendah',
            'high' => 'Tinggi',
            'urgent' => 'Mendesak',
            default => 'Normal',
        };
    }

    private function reasonCategoryLabel(string $reason): string
    {
        return match ($reason) {
            'personal' => 'Alasan Pribadi',
            'medical' => 'Alasan Kesehatan',
            'financial' => 'Alasan Finansial',
            'family' => 'Alasan Keluarga',
            'work' => 'Alasan Pekerjaan',
            default => 'Lainnya',
        };
    }

    private function transferTypeLabel(string $type): string
    {
        return match ($type) {
            'study_program' => 'Pindah Program Studi',
            'faculty' => 'Pindah Fakultas',
            'class_type' => 'Pindah Kelas',
            default => 'Lainnya',
        };
    }

    private function classTypeLabel(?string $type): string
    {
        return match ($type) {
            'regular' => 'Kelas Reguler',
            'evening' => 'Kelas Sore',
            'weekend' => 'Kelas Akhir Pekan',
            default => '-',
        };
    }

    private function invoiceSummary(?StudentInvoice $invoice): ?array
    {
        if ($invoice === null) {
            return null;
        }

        return [
            'id' => $invoice->id,
            'number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'statusLabel' => match ($invoice->status) {
                'paid' => 'Lunas',
                'partially_paid' => 'Dibayar Sebagian',
                'unpaid' => 'Belum Dibayar',
                'overdue' => 'Jatuh Tempo',
                'cancelled' => 'Dibatalkan',
                'draft' => 'Draf',
                default => str((string) $invoice->status)->replace('_', ' ')->title()->toString(),
            },
            'amount' => (float) $invoice->total_amount,
            'paidAmount' => (float) $invoice->paid_amount,
            'dueDate' => $invoice->due_date?->format('d M Y'),
            'url' => route('student.financial.invoices.show', ['id' => $invoice->id]),
        ];
    }
}
