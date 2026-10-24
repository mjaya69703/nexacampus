<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\GradeAppeal;
use App\Models\Academic\StudentGrade;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Keberatan nilai mahasiswa.
 *
 * Koreksi dari Blade lama:
 * - Nilai yang bisa diajukan hanya dari detail KRS yang benar-benar diambil
 *   (status Taken pada KRS Approved). Sebelumnya nilai dari kelas yang
 *   dibatalkan masih muncul.
 * - Dosen tujuan memakai urutan yang sama dengan tampilan: Primary lebih
 *   dulu, lalu sort_order, sehingga label dosen dan penerima pengajuan tidak
 *   berbeda.
 */
class StudentGradeAppealController extends Controller
{
    private const CATEGORIES = [
        'calculation' => 'Perhitungan nilai',
        'component' => 'Komponen penilaian',
        'input_error' => 'Kesalahan input',
        'feedback' => 'Butuh klarifikasi',
        'other' => 'Lainnya',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();

        $gradeOptions = [];
        $appealRows = [];
        $summary = ['availableGrades' => 0, 'activeAppeals' => 0, 'resolvedAppeals' => 0];

        if ($profile !== null) {
            $grades = StudentGrade::query()
                ->with([
                    'studyPlanDetail.studyPlan.academicYear',
                    'studyPlanDetail.courseOffering.course',
                    'studyPlanDetail.courseOffering.lecturers.lecturerProfile.user',
                ])
                ->where('grade_status', 'Published')
                ->whereHas('studyPlanDetail', function ($query) use ($profile) {
                    $query->officiallyEnrolled()
                        ->whereHas('studyPlan', fn ($plan) => $plan->where('student_profile_id', $profile->id));
                })
                ->orderByDesc('graded_at')
                ->orderByDesc('id')
                ->get();

            $activeGradeIds = GradeAppeal::query()
                ->where('student_profile_id', $profile->id)
                ->whereIn('status', ['submitted', 'under_review'])
                ->pluck('student_grade_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $gradeOptions = $grades->map(function (StudentGrade $grade) use ($activeGradeIds) {
                $detail = $grade->studyPlanDetail;
                $offering = $detail?->courseOffering;
                $course = $offering?->course;
                $lecturer = $this->primaryLecturerName($offering?->lecturers ?? collect());

                return [
                    'id' => $grade->id,
                    'course' => trim(($course?->code ? $course->code.' - ' : '').($course?->name ?? '-')),
                    'academicYear' => $detail?->studyPlan?->academicYear?->name ?? '-',
                    'lecturer' => $lecturer,
                    'score' => $grade->final_score !== null ? (float) $grade->final_score : null,
                    'letter' => $grade->letter_grade ?? '-',
                    'hasActiveAppeal' => in_array((int) $grade->id, $activeGradeIds, true),
                ];
            })->values()->all();

            $appeals = GradeAppeal::query()
                ->with([
                    'studentGrade.studyPlanDetail.courseOffering.course',
                    'courseOffering.academicYear',
                    'courseOffering.course',
                    'lecturerProfile.user',
                    'attachments',
                ])
                ->where('student_profile_id', $profile->id)
                ->latest('submitted_at')
                ->latest('id')
                ->get();

            $appealRows = $appeals->map(function (GradeAppeal $appeal) {
                $course = $appeal->courseOffering?->course
                    ?? $appeal->studentGrade?->studyPlanDetail?->courseOffering?->course;

                return [
                    'id' => $appeal->id,
                    'course' => trim(($course?->code ? $course->code.' - ' : '').($course?->name ?? '-')),
                    'academicYear' => $appeal->courseOffering?->academicYear?->name ?? '-',
                    'lecturer' => $appeal->lecturerProfile?->user?->name ?? '-',
                    'status' => $appeal->status,
                    'statusLabel' => $this->statusLabel($appeal->status),
                    'statusTone' => $this->statusTone($appeal->status),
                    'category' => self::CATEGORIES[$appeal->reason_category] ?? $appeal->reason_category,
                    'reason' => $appeal->reason,
                    'expectedOutcome' => $appeal->expected_outcome,
                    'originalScore' => $appeal->original_score !== null ? (float) $appeal->original_score : null,
                    'requestedScore' => $appeal->requested_score !== null ? (float) $appeal->requested_score : null,
                    'resolvedScore' => $appeal->resolved_score !== null ? (float) $appeal->resolved_score : null,
                    'submittedAt' => $appeal->submitted_at?->format('d M Y H:i') ?? '-',
                    'resolvedAt' => $appeal->resolved_at?->format('d M Y H:i'),
                    'response' => $appeal->lecturer_response,
                    'attachments' => $appeal->attachments->map(fn ($attachment) => [
                        'id' => $attachment->id,
                        'name' => $attachment->file_name,
                        'url' => route('student.grade-appeal-attachments.preview', ['attachment' => $attachment->id]),
                    ])->values()->all(),
                ];
            })->values()->all();

            $summary = [
                'availableGrades' => count($gradeOptions),
                'activeAppeals' => $appeals->whereIn('status', ['submitted', 'under_review'])->count(),
                'resolvedAppeals' => $appeals->whereIn('status', ['approved', 'rejected', 'closed'])->count(),
            ];
        }

        return Inertia::render('Student/GradeAppeals/Index', [
            'shell' => ShellProps::make($user, 'Academic', 'Keberatan Nilai'),
            'hasProfile' => $profile !== null,
            'gradeOptions' => $gradeOptions,
            'appeals' => $appealRows,
            'categories' => collect(self::CATEGORIES)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'summary' => $summary,
            'urls' => [
                'index' => route('student.grade-appeals.index'),
                'store' => route('student.grade-appeals.store'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = $request->user()->studentProfile()->first();

        abort_unless($profile !== null, 403, 'Profil mahasiswa tidak ditemukan.');

        $validGradeIds = StudentGrade::query()
            ->where('grade_status', 'Published')
            ->whereHas('studyPlanDetail', function ($query) use ($profile) {
                $query->officiallyEnrolled()
                    ->whereHas('studyPlan', fn ($plan) => $plan->where('student_profile_id', $profile->id));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $validated = $request->validate([
            'gradeId' => ['required', 'integer', Rule::in($validGradeIds)],
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'reason' => ['required', 'string', 'min:20', 'max:4000'],
            'expectedOutcome' => ['nullable', 'string', 'max:4000'],
            'requestedScore' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'attachments' => ['nullable', 'array', 'max:4'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt', 'max:5120'],
        ], [
            'gradeId.required' => 'Pilih mata kuliah yang ingin diajukan.',
            'reason.min' => 'Tuliskan alasan minimal 20 karakter agar dosen punya konteks yang cukup.',
        ]);

        $grade = StudentGrade::query()
            ->with(['studyPlanDetail.courseOffering.lecturers'])
            ->findOrFail((int) $validated['gradeId']);

        $hasActiveAppeal = GradeAppeal::query()
            ->where('student_grade_id', $grade->id)
            ->where('student_profile_id', $profile->id)
            ->whereIn('status', ['submitted', 'under_review'])
            ->exists();

        if ($hasActiveAppeal) {
            return back()->withErrors(['gradeId' => 'Masih ada pengajuan aktif untuk nilai ini.']);
        }

        $offering = $grade->studyPlanDetail?->courseOffering;
        $lecturerId = $this->orderedLecturers($offering?->lecturers ?? collect())
            ->first()?->lecturer_profile_id;

        $appeal = GradeAppeal::query()->create([
            'student_grade_id' => $grade->id,
            'student_profile_id' => $profile->id,
            'course_offering_id' => $offering?->id,
            'lecturer_profile_id' => $lecturerId,
            'student_user_id' => $request->user()->id,
            'status' => 'submitted',
            'reason_category' => $validated['category'],
            'reason' => $validated['reason'],
            'expected_outcome' => $validated['expectedOutcome'] ?? null,
            'original_score' => $grade->final_score,
            'requested_score' => $validated['requestedScore'] ?? null,
            'submitted_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        foreach ($request->file('attachments', []) as $file) {
            $extension = $file->getClientOriginalExtension() ?: 'bin';

            $appeal->attachments()->create([
                'file_path' => $file->storeAs(
                    'private/grade-appeals/'.$appeal->id,
                    Str::uuid().'.'.$extension
                ),
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }

        return back()->with('success', 'Pengajuan keberatan nilai berhasil dikirim.');
    }

    private function orderedLecturers(Collection $lecturers): Collection
    {
        return $lecturers
            ->filter(fn (CourseOfferingLecturer $row) => (bool) $row->is_active)
            ->sortBy(fn (CourseOfferingLecturer $row) => sprintf(
                '%d-%04d',
                $row->role === 'Primary' ? 0 : 1,
                (int) ($row->sort_order ?? 0)
            ))
            ->values();
    }

    private function primaryLecturerName(Collection $lecturers): string
    {
        return $this->orderedLecturers($lecturers)
            ->first()?->lecturerProfile?->user?->name ?? '-';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Terkirim',
            'under_review' => 'Direview',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'closed' => 'Ditutup',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'approved' => 'green',
            'rejected' => 'red',
            'under_review' => 'blue',
            'closed' => 'gray',
            default => 'amber',
        };
    }
}
