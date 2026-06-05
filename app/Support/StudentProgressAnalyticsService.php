<?php

namespace App\Support;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\AttendanceRecord;
use App\Models\Academic\StudentAdvisorNote;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyResult;
use App\Models\Academic\TranscriptEntry;
use App\Models\StudentService\GraduationPolicy;
use Illuminate\Support\Collection;

class StudentProgressAnalyticsService
{
    public function __construct(
        private readonly AcademicAdvisorService $advisorService,
    ) {}

    public function summarize(StudentProfile $student): array
    {
        $student->loadMissing(['user', 'studyProgram.faculty']);

        $activeAcademicYear = AcademicYear::query()
            ->where('is_active', true)
            ->latest('start_date')
            ->first();

        $studyResults = StudyResult::query()
            ->with('academicYear')
            ->where('student_profile_id', $student->id)
            ->orderBy('semester_no')
            ->orderBy('id')
            ->get();

        $latestResult = $studyResults->last();
        $transcriptEntries = $this->transcriptEntries($student);
        $passedEntries = $transcriptEntries->where('result_status', 'Passed');
        $failedEntries = $transcriptEntries->where('result_status', 'Failed')->values();
        $targetCredits = $this->targetCredits($student);
        $passedCredits = (int) $passedEntries->sum('credits');
        $creditProgress = $targetCredits > 0 ? min(100, round(($passedCredits / $targetCredits) * 100, 1)) : 0;
        $currentStudyPlan = $this->currentStudyPlan($student, $activeAcademicYear);
        $attendance = $this->attendanceSummary($student);
        $financial = $this->financialSummary($student);
        $advisor = $this->advisorService->activeAdvisorForStudent($student->id, $activeAcademicYear?->id);

        $risks = $this->risks(
            student: $student,
            latestResult: $latestResult,
            failedEntries: $failedEntries,
            attendance: $attendance,
            financial: $financial,
            currentStudyPlan: $currentStudyPlan,
        );

        return [
            'student' => [
                'name' => $student->user?->name ?? '-',
                'nim' => $student->nim ?? '-',
                'study_program' => $student->studyProgram?->name ?? '-',
                'faculty' => $student->studyProgram?->faculty?->name ?? '-',
                'semester' => $student->current_semester,
                'status' => $student->academic_status ?? '-',
                'active_year' => $activeAcademicYear?->name ?? '-',
            ],
            'advisor' => [
                'name' => $advisor?->lecturerProfile?->user?->name ?? null,
                'identity' => $advisor?->lecturerProfile?->nidn ?? $advisor?->lecturerProfile?->nip ?? null,
                'academic_year' => $advisor?->academicYear?->name ?? 'Umum',
                'period' => trim(($advisor?->start_date?->format('d M Y') ?? 'Mulai awal').' - '.($advisor?->end_date?->format('d M Y') ?? 'Berjalan')),
            ],
            'credits' => [
                'target' => $targetCredits,
                'passed' => $passedCredits,
                'remaining' => max(0, $targetCredits - $passedCredits),
                'progress' => $creditProgress,
                'current' => (int) ($currentStudyPlan?->details?->sum('credits') ?? 0),
                'current_status' => $currentStudyPlan?->status ?? '-',
            ],
            'gpa' => [
                'semester' => $latestResult?->semester_gpa,
                'cumulative' => $latestResult?->cumulative_gpa ?? $this->computedGpa($transcriptEntries),
                'latest_semester' => $latestResult?->semester_no,
            ],
            'trend' => $studyResults
                ->map(fn (StudyResult $result) => [
                    'semester' => 'Smt '.($result->semester_no ?? '-'),
                    'semester_gpa' => (float) ($result->semester_gpa ?? 0),
                    'cumulative_gpa' => (float) ($result->cumulative_gpa ?? 0),
                    'credits_passed' => (int) ($result->total_credits_passed ?? 0),
                    'status' => $result->status ?? '-',
                ])
                ->values()
                ->all(),
            'attendance' => $attendance,
            'financial' => $financial,
            'failed_courses' => $failedEntries
                ->take(6)
                ->map(fn (TranscriptEntry $entry) => [
                    'code' => $entry->course?->code ?? '-',
                    'name' => $entry->course?->name ?? '-',
                    'semester' => $entry->semester_no,
                    'credits' => (int) ($entry->credits ?? 0),
                    'grade' => $entry->letter_grade ?? '-',
                ])
                ->values()
                ->all(),
            'risks' => $risks,
            'recommendations' => $this->recommendations($risks, $failedEntries, $financial, $advisor !== null),
            'advisor_notes' => $this->advisorNotes($student),
        ];
    }

    private function transcriptEntries(StudentProfile $student): Collection
    {
        return TranscriptEntry::query()
            ->with(['course', 'academicYear'])
            ->where('student_profile_id', $student->id)
            ->where(function ($query) {
                $query->where('is_best_grade', true)
                    ->orWhereNull('is_best_grade');
            })
            ->orderBy('semester_no')
            ->orderBy('course_id')
            ->get();
    }

    private function targetCredits(StudentProfile $student): int
    {
        $policy = GraduationPolicy::query()
            ->where('is_active', true)
            ->where(function ($query) use ($student) {
                $query->where('study_program_id', $student->study_program_id)
                    ->orWhereNull('study_program_id');
            })
            ->orderByRaw('study_program_id is null')
            ->latest('id')
            ->first();

        return (int) ($policy?->minimum_passed_credits ?? 144);
    }

    private function currentStudyPlan(StudentProfile $student, ?AcademicYear $activeAcademicYear): ?StudyPlan
    {
        return StudyPlan::query()
            ->with(['details.courseOffering.course', 'academicYear'])
            ->where('student_profile_id', $student->id)
            ->when($activeAcademicYear, fn ($query) => $query->where('academic_year_id', $activeAcademicYear->id))
            ->latest('id')
            ->first()
            ?? StudyPlan::query()
                ->with(['details.courseOffering.course', 'academicYear'])
                ->where('student_profile_id', $student->id)
                ->latest('id')
                ->first();
    }

    private function attendanceSummary(StudentProfile $student): array
    {
        $records = AttendanceRecord::query()
            ->where('student_profile_id', $student->id)
            ->get();

        $total = $records->count();
        $attended = $records->whereIn('status', ['Present', 'Late'])->count();
        $excused = $records->whereIn('status', ['Excused', 'Sick'])->count();
        $absent = $records->where('status', 'Absent')->count();
        $rate = $total > 0 ? round((($attended + $excused) / $total) * 100, 1) : null;

        return [
            'total' => $total,
            'attended' => $attended,
            'excused' => $excused,
            'absent' => $absent,
            'rate' => $rate,
            'level' => $rate === null ? 'neutral' : ($rate < 75 ? 'danger' : ($rate < 85 ? 'warning' : 'success')),
        ];
    }

    private function financialSummary(StudentProfile $student): array
    {
        $visibleInvoices = $student->invoices()
            ->whereNotIn('status', ['draft', 'cancelled', 'paid'])
            ->get();

        $overdue = $visibleInvoices
            ->filter(fn ($invoice) => (float) ($invoice->outstanding_amount ?? 0) > 0 && $invoice->due_date?->isPast())
            ->count();

        $activeHolds = $student->financialHolds()
            ->where('status', 'active')
            ->count();

        return [
            'outstanding' => (float) $visibleInvoices->sum('outstanding_amount'),
            'overdue_count' => $overdue,
            'active_holds' => $activeHolds,
            'level' => $activeHolds > 0 || $overdue > 0 ? 'danger' : ($visibleInvoices->isNotEmpty() ? 'warning' : 'success'),
        ];
    }

    private function computedGpa(Collection $entries): ?float
    {
        $counted = $entries
            ->where('is_counted_in_gpa', true)
            ->filter(fn (TranscriptEntry $entry) => $entry->grade_point !== null && (int) ($entry->credits ?? 0) > 0);

        $credits = (int) $counted->sum('credits');

        if ($credits === 0) {
            return null;
        }

        $points = $counted->sum(fn (TranscriptEntry $entry) => ((float) $entry->grade_point) * ((int) $entry->credits));

        return round($points / $credits, 2);
    }

    private function risks(
        StudentProfile $student,
        ?StudyResult $latestResult,
        Collection $failedEntries,
        array $attendance,
        array $financial,
        ?StudyPlan $currentStudyPlan,
    ): array {
        $gpa = $latestResult?->cumulative_gpa !== null ? (float) $latestResult->cumulative_gpa : null;

        return [
            [
                'key' => 'academic',
                'label' => 'Akademik',
                'level' => $gpa === null ? 'neutral' : ($gpa < 2.5 || $failedEntries->count() >= 3 ? 'danger' : ($gpa < 3.0 || $failedEntries->isNotEmpty() ? 'warning' : 'success')),
                'reason' => $gpa === null
                    ? 'Belum ada IPK final yang bisa dibaca.'
                    : 'IPK '.number_format($gpa, 2).' dengan '.$failedEntries->count().' mata kuliah perlu ulang.',
            ],
            [
                'key' => 'attendance',
                'label' => 'Kehadiran',
                'level' => $attendance['level'],
                'reason' => $attendance['rate'] === null
                    ? 'Belum ada rekam kehadiran.'
                    : 'Kehadiran terbaca '.$attendance['rate'].'% dari '.$attendance['total'].' pertemuan.',
            ],
            [
                'key' => 'financial',
                'label' => 'Keuangan',
                'level' => $financial['level'],
                'reason' => $financial['active_holds'] > 0
                    ? $financial['active_holds'].' hold aktif perlu diselesaikan.'
                    : ($financial['overdue_count'] > 0 ? $financial['overdue_count'].' tagihan jatuh tempo belum selesai.' : 'Tidak ada hold aktif yang terbaca.'),
            ],
            [
                'key' => 'study_plan',
                'label' => 'KRS',
                'level' => in_array($currentStudyPlan?->status, ['Approved', 'Finalized'], true) ? 'success' : ($student->academic_status === 'Aktif' ? 'warning' : 'neutral'),
                'reason' => $currentStudyPlan
                    ? 'KRS terakhir berstatus '.($currentStudyPlan->status ?? '-').'.'
                    : 'Belum ada KRS yang terbaca.',
            ],
        ];
    }

    private function recommendations(array $risks, Collection $failedEntries, array $financial, bool $hasAdvisor): array
    {
        $items = [];

        foreach ($risks as $risk) {
            if ($risk['level'] === 'danger') {
                $items[] = 'Prioritaskan '.$risk['label'].': '.$risk['reason'];
            }
        }

        if ($failedEntries->isNotEmpty()) {
            $items[] = 'Diskusikan rencana mengulang mata kuliah yang belum lulus sebelum KRS berikutnya.';
        }

        if ($financial['outstanding'] > 0) {
            $items[] = 'Cek tagihan aktif agar akses akademik tidak tertahan di periode berjalan.';
        }

        if ($hasAdvisor) {
            $items[] = 'Jadwalkan konsultasi singkat dengan Dosen PA untuk validasi target semester ini.';
        } else {
            $items[] = 'Hubungi admin akademik jika Dosen PA belum muncul di sistem.';
        }

        return collect($items)->unique()->take(5)->values()->all();
    }

    private function advisorNotes(StudentProfile $student): array
    {
        return StudentAdvisorNote::query()
            ->with('lecturerProfile.user')
            ->where('student_profile_id', $student->id)
            ->where('visible_to_student', true)
            ->latest('created_at')
            ->take(3)
            ->get()
            ->map(fn (StudentAdvisorNote $note) => [
                'topic' => $note->topic,
                'notes' => str($note->notes)->limit(140)->toString(),
                'recommendation' => $note->recommendation ? str($note->recommendation)->limit(140)->toString() : null,
                'follow_up_at' => $note->follow_up_at?->format('d M Y'),
                'status' => $note->status,
                'lecturer' => $note->lecturerProfile?->user?->name ?? '-',
            ])
            ->values()
            ->all();
    }
}
