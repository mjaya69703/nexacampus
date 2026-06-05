<?php

namespace App\Support\Organization;

use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Organization\EdomPeriod;
use App\Models\Organization\EdomQuestion;
use App\Models\Organization\EdomResponse;
use App\Models\Organization\LecturerPerformanceRubric;
use App\Models\Organization\LecturerPerformanceReview;
use App\Models\Organization\LecturerWorkloadPeriod;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EdomService
{
    public function eligibleTargetsForStudent(User $user, ?EdomPeriod $period = null): Collection
    {
        $studentProfileId = $user->studentProfile?->id;

        if (! $studentProfileId) {
            return collect();
        }

        $period ??= EdomPeriod::query()->where('status', 'open')->latest('starts_at')->first();

        if (! $period || ! $period->isOpen()) {
            return collect();
        }

        $offeringIds = StudyPlan::query()
            ->where('student_profile_id', $studentProfileId)
            ->when($period->academic_year_id, fn ($query) => $query->where('academic_year_id', $period->academic_year_id))
            ->with('details')
            ->get()
            ->flatMap(fn (StudyPlan $plan) => $plan->details->pluck('course_offering_id'))
            ->unique()
            ->values();

        return CourseOfferingLecturer::query()
            ->whereIn('course_offering_id', $offeringIds)
            ->where('is_active', true)
            ->with(['courseOffering.course', 'courseOffering.academicYear', 'lecturerProfile.user'])
            ->get()
            ->map(function (CourseOfferingLecturer $assignment) use ($period, $studentProfileId) {
                $response = EdomResponse::query()
                    ->where('edom_period_id', $period->id)
                    ->where('course_offering_id', $assignment->course_offering_id)
                    ->where('lecturer_profile_id', $assignment->lecturer_profile_id)
                    ->where('student_profile_id', $studentProfileId)
                    ->first();

                return [
                    'period_id' => $period->id,
                    'course_offering_id' => $assignment->course_offering_id,
                    'lecturer_profile_id' => $assignment->lecturer_profile_id,
                    'course' => trim(($assignment->courseOffering?->course?->code ? $assignment->courseOffering->course->code.' - ' : '').($assignment->courseOffering?->course?->name ?? '-')),
                    'label' => $assignment->courseOffering?->label ?? '-',
                    'academic_year' => $assignment->courseOffering?->academicYear?->name ?? '-',
                    'lecturer' => $assignment->lecturerProfile?->user?->name ?? '-',
                    'submitted_at' => $response?->submitted_at?->format('d M Y H:i'),
                    'completed' => (bool) $response,
                ];
            });
    }

    public function submit(User $user, EdomPeriod $period, int $courseOfferingId, int $lecturerProfileId, array $answers): EdomResponse
    {
        if (! $period->isOpen()) {
            throw ValidationException::withMessages(['period' => 'Periode evaluasi belum dibuka.']);
        }

        $studentProfile = $user->studentProfile;

        if (! $studentProfile) {
            throw ValidationException::withMessages(['student' => 'Profil mahasiswa belum tersedia.']);
        }

        $eligibleOffering = StudyPlanDetail::query()
            ->where('course_offering_id', $courseOfferingId)
            ->whereHas('studyPlan', function ($query) use ($studentProfile, $period) {
                $query->where('student_profile_id', $studentProfile->id)
                    ->when($period->academic_year_id, fn ($nested) => $nested->where('academic_year_id', $period->academic_year_id));
            })
            ->exists();

        $eligibleLecturer = CourseOfferingLecturer::query()
            ->where('course_offering_id', $courseOfferingId)
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->where('is_active', true)
            ->exists();

        if (! $eligibleOffering || ! $eligibleLecturer) {
            throw ValidationException::withMessages(['edom' => 'Evaluasi ini tidak tersedia untuk kelas Anda.']);
        }

        if (EdomResponse::query()
            ->where('edom_period_id', $period->id)
            ->where('course_offering_id', $courseOfferingId)
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->where('student_profile_id', $studentProfile->id)
            ->exists()) {
            throw ValidationException::withMessages(['edom' => 'Evaluasi untuk kelas dan dosen ini sudah dikirim.']);
        }

        $questions = EdomQuestion::query()->where('is_active', true)->orderBy('sort_order')->get();

        foreach ($questions as $question) {
            if ($question->is_required && blank($answers[$question->id] ?? null)) {
                throw ValidationException::withMessages(["answers.{$question->id}" => 'Pertanyaan ini wajib diisi.']);
            }
        }

        return DB::transaction(function () use ($period, $courseOfferingId, $lecturerProfileId, $studentProfile, $questions, $answers) {
            $response = EdomResponse::create([
                'edom_period_id' => $period->id,
                'course_offering_id' => $courseOfferingId,
                'lecturer_profile_id' => $lecturerProfileId,
                'student_profile_id' => $studentProfile->id,
                'submitted_at' => now(),
            ]);

            foreach ($questions as $question) {
                $value = $answers[$question->id] ?? null;

                $response->answers()->create([
                    'edom_question_id' => $question->id,
                    'score' => $question->answer_type === 'scale' ? (int) $value : null,
                    'text_answer' => $question->answer_type === 'text' ? trim((string) $value) : null,
                ]);
            }

            return $response->fresh(['answers.question']);
        });
    }

    public function lecturerAggregate(int $lecturerProfileId, ?EdomPeriod $period = null, ?array $courseOfferingIds = null): array
    {
        $period ??= EdomPeriod::query()->latest('starts_at')->first();

        if (! $period) {
            return ['average' => null, 'responses' => 0, 'comments' => collect(), 'available' => false];
        }

        $query = EdomResponse::query()
            ->where('edom_period_id', $period->id)
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->with(['answers.question', 'courseOffering.course']);

        if ($courseOfferingIds !== null) {
            $query->whereIn('course_offering_id', $courseOfferingIds);
        }

        $responses = $query->get();
        $scores = $responses->flatMap(fn (EdomResponse $response) => $response->answers->pluck('score')->filter());

        return [
            'period' => $period,
            'average' => $scores->isNotEmpty() ? round((float) $scores->avg(), 2) : null,
            'responses' => $responses->count(),
            'available' => $period->status === 'closed' && $responses->count() >= $period->minimum_responses,
            'comments' => $responses
                ->flatMap(fn (EdomResponse $response) => $response->answers->filter(fn ($answer) => filled($answer->text_answer))->map(fn ($answer) => [
                    'course' => $response->courseOffering?->course?->name ?? '-',
                    'comment' => $answer->text_answer,
                ]))
                ->values(),
        ];
    }

    public function calculatePerformance(User $lecturerUser, ?EdomPeriod $edomPeriod = null, ?LecturerWorkloadPeriod $workloadPeriod = null): LecturerPerformanceReview
    {
        $edomPeriod ??= EdomPeriod::query()->latest('starts_at')->first();
        $workloadPeriod ??= LecturerWorkloadPeriod::query()->latest('starts_at')->first();

        if (! $lecturerUser->lecturerProfile || ! $edomPeriod) {
            throw ValidationException::withMessages(['review' => 'Data dosen atau periode evaluasi belum tersedia.']);
        }

        $aggregate = $this->lecturerAggregate($lecturerUser->lecturerProfile->id, $edomPeriod);
        $teaching = $this->teachingCompliance($lecturerUser, $edomPeriod);
        $attendance = $this->employeeAttendanceCompliance($lecturerUser, $edomPeriod);
        $rubric = LecturerPerformanceRubric::active();
        $workload = $workloadPeriod
            ? LecturerWorkloadSubmission::query()
                ->where('lecturer_workload_period_id', $workloadPeriod->id)
                ->where('user_id', $lecturerUser->id)
                ->where('status', 'approved')
                ->first()
            : null;

        $minimumResponses = max((int) $edomPeriod->minimum_responses, (int) $rubric->minimum_responses);
        $edomComponent = $aggregate['responses'] >= $minimumResponses && $aggregate['average']
            ? ($aggregate['average'] / 5) * 100
            : null;
        $workloadComponent = $workload && (float) $rubric->target_workload_sks > 0
            ? min(100, ((float) $workload->total_sks / (float) $rubric->target_workload_sks) * 100)
            : null;

        $weighted = collect([
            ['score' => $edomComponent, 'weight' => (float) $rubric->edom_weight],
            ['score' => $teaching, 'weight' => (float) $rubric->teaching_weight],
            ['score' => $attendance, 'weight' => (float) $rubric->attendance_weight],
            ['score' => $workloadComponent, 'weight' => (float) $rubric->workload_weight],
        ])->filter(fn (array $component) => $component['score'] !== null && $component['weight'] > 0);

        $weightTotal = (float) $weighted->sum('weight');
        $finalScore = $weightTotal > 0
            ? round((float) $weighted->sum(fn (array $component) => $component['score'] * $component['weight']) / $weightTotal, 2)
            : null;

        return LecturerPerformanceReview::query()->updateOrCreate([
            'edom_period_id' => $edomPeriod->id,
            'lecturer_workload_period_id' => $workloadPeriod?->id,
            'user_id' => $lecturerUser->id,
        ], [
            'lecturer_profile_id' => $lecturerUser->lecturerProfile?->id,
            'employee_profile_id' => $lecturerUser->employeeProfile?->id,
            'edom_score' => $aggregate['average'],
            'edom_response_count' => $aggregate['responses'],
            'teaching_compliance_score' => $teaching,
            'attendance_compliance_score' => $attendance,
            'workload_total_sks' => $workload?->total_sks,
            'final_score' => $finalScore,
            'status' => 'calculated',
            'snapshot' => [
                'edom_available' => $aggregate['available'],
                'edom_minimum_responses' => $minimumResponses,
                'edom_component_score' => $edomComponent ? round($edomComponent, 2) : null,
                'workload_component_score' => $workloadComponent ? round($workloadComponent, 2) : null,
                'workload_status' => $workload?->status,
                'rubric' => [
                    'code' => $rubric->code,
                    'name' => $rubric->name,
                    'edom_weight' => (float) $rubric->edom_weight,
                    'teaching_weight' => (float) $rubric->teaching_weight,
                    'attendance_weight' => (float) $rubric->attendance_weight,
                    'workload_weight' => (float) $rubric->workload_weight,
                    'target_workload_sks' => (float) $rubric->target_workload_sks,
                ],
            ],
            'calculated_at' => now(),
            'updated_by' => auth()->id(),
        ]);
    }

    private function teachingCompliance(User $user, EdomPeriod $period): ?float
    {
        $lecturerProfileId = $user->lecturerProfile?->id;

        if (! $lecturerProfileId) {
            return null;
        }

        $offerings = CourseOfferingLecturer::query()
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->where('is_active', true)
            ->whereHas('courseOffering', fn ($query) => $query->when($period->academic_year_id, fn ($nested) => $nested->where('academic_year_id', $period->academic_year_id)))
            ->with('courseOffering.attendanceSessions')
            ->get()
            ->pluck('courseOffering')
            ->filter();

        $expected = (int) $offerings->sum(fn ($offering) => max(1, (int) ($offering->total_meetings ?: 14)));
        $closed = (int) $offerings->sum(fn ($offering) => $offering->attendanceSessions->where('status', 'Closed')->count());

        return $expected > 0 ? round(min(100, ($closed / $expected) * 100), 2) : null;
    }

    private function employeeAttendanceCompliance(User $user, EdomPeriod $period): ?float
    {
        $profile = $user->employeeProfile;

        if (! $profile || ! $period->starts_at || ! $period->ends_at) {
            return null;
        }

        $records = $profile->attendanceRecords()
            ->whereBetween('attendance_date', [$period->starts_at, $period->ends_at])
            ->get();

        if ($records->isEmpty()) {
            return null;
        }

        $present = $records->whereIn('status', ['present', 'late', 'remote'])->count();

        return round(($present / $records->count()) * 100, 2);
    }
}
