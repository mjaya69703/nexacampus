<?php

namespace App\Support;

use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyResult;
use App\Models\Academic\TranscriptEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TranscriptSyncService
{
    public function syncStudent(int $studentProfileId, ?int $academicYearId = null): array
    {
        return DB::transaction(function () use ($studentProfileId, $academicYearId) {
            $studyResultsCount = $this->syncStudyResults($studentProfileId, $academicYearId);
            $this->syncStudyPlanReferences($studentProfileId, $academicYearId);
            $transcriptEntriesCount = $this->syncTranscriptEntries($studentProfileId);
            $this->syncCumulativeGpaSnapshot($studentProfileId);

            return [
                'study_results' => $studyResultsCount,
                'transcript_entries' => $transcriptEntriesCount,
            ];
        });
    }

    public function syncStudyResults(int $studentProfileId, ?int $academicYearId = null): int
    {
        $grades = $this->finalizedGradesQuery($studentProfileId, $academicYearId)
            ->get();

        $groups = $grades->groupBy(function (StudentGrade $grade) {
            return $grade->studyPlanDetail?->studyPlan?->academic_year_id;
        })->filter(fn (Collection $group, $yearId) => ! empty($yearId));

        $count = 0;

        foreach ($groups as $yearId => $groupedGrades) {
            $studyPlan = $groupedGrades
                ->map(fn (StudentGrade $grade) => $grade->studyPlanDetail?->studyPlan)
                ->filter()
                ->sortByDesc('updated_at')
                ->first();

            if (! $studyPlan) {
                $studyPlan = StudyPlan::query()
                    ->where('student_profile_id', $studentProfileId)
                    ->where('academic_year_id', (int) $yearId)
                    ->latest('updated_at')
                    ->first();
            }

            $totalCourses = $groupedGrades->count();
            $totalCreditsTaken = (int) $groupedGrades->sum(function (StudentGrade $grade) {
                return $this->resolveCredits($grade);
            });

            $totalCreditsPassed = (int) $groupedGrades
                ->filter(fn (StudentGrade $grade) => $grade->result_status === 'Passed')
                ->sum(fn (StudentGrade $grade) => $this->resolveCredits($grade));

            $gpaGrades = $groupedGrades->filter(function (StudentGrade $grade) {
                return in_array($grade->result_status, ['Passed', 'Failed'], true)
                    && $grade->grade_point !== null;
            });

            $gpaCredits = (float) $gpaGrades->sum(fn (StudentGrade $grade) => $this->resolveCredits($grade));
            $weightedPoints = (float) $gpaGrades->sum(function (StudentGrade $grade) {
                return ((float) $grade->grade_point) * $this->resolveCredits($grade);
            });

            $semesterGpa = $gpaCredits > 0 ? round($weightedPoints / $gpaCredits, 2) : null;

            $existing = StudyResult::query()
                ->where('student_profile_id', $studentProfileId)
                ->where('academic_year_id', $yearId)
                ->first();

            $payload = [
                'student_profile_id' => $studentProfileId,
                'academic_year_id' => (int) $yearId,
                'study_plan_id' => $studyPlan?->id,
                'semester_no' => $studyPlan?->semester_no,
                'total_courses' => $totalCourses,
                'total_credits_taken' => $totalCreditsTaken,
                'total_credits_passed' => $totalCreditsPassed,
                'semester_gpa' => $semesterGpa,
                'status' => $existing?->status === 'Published' ? 'Published' : 'Finalized',
                'finalized_at' => $existing?->finalized_at ?? now(),
                'finalized_by' => $existing?->finalized_by ?? auth()->id(),
                'updated_by' => auth()->id(),
            ];

            if ($existing) {
                $existing->update($payload);
            } else {
                $payload['created_by'] = auth()->id();
                StudyResult::create($payload);
            }

            $count++;
        }

        return $count;
    }

    protected function syncStudyPlanReferences(int $studentProfileId, ?int $academicYearId = null): int
    {
        $query = StudyResult::query()
            ->where('student_profile_id', $studentProfileId)
            ->whereNull('study_plan_id');

        if ($academicYearId !== null) {
            $query->where('academic_year_id', $academicYearId);
        }

        $results = $query->get();
        $updatedCount = 0;

        foreach ($results as $result) {
            $studyPlanQuery = StudyPlan::query()
                ->where('student_profile_id', $studentProfileId)
                ->where('academic_year_id', $result->academic_year_id);

            if ($result->semester_no !== null) {
                $studyPlanQuery->where('semester_no', $result->semester_no);
            }

            $studyPlan = $studyPlanQuery
                ->latest('updated_at')
                ->first();

            if (! $studyPlan) {
                continue;
            }

            $result->update([
                'study_plan_id' => $studyPlan->id,
                'semester_no' => $result->semester_no ?? $studyPlan->semester_no,
                'updated_by' => auth()->id(),
            ]);

            $updatedCount++;
        }

        return $updatedCount;
    }

    public function syncTranscriptEntries(int $studentProfileId): int
    {
        $grades = $this->finalizedGradesQuery($studentProfileId)
            ->get()
            ->filter(fn (StudentGrade $grade) => $grade->studyPlanDetail?->courseOffering?->course_id)
            ->values();

        $bestGrades = $grades
            ->sortByDesc(function (StudentGrade $grade) {
                return [
                    (float) ($grade->grade_point ?? -1),
                    (float) ($grade->final_score ?? -1),
                    optional($grade->graded_at)->timestamp ?? 0,
                    $grade->id,
                ];
            })
            ->groupBy(fn (StudentGrade $grade) => $grade->studyPlanDetail->courseOffering->course_id)
            ->map(fn (Collection $group) => $group->first())
            ->values();

        TranscriptEntry::withTrashed()
            ->where('student_profile_id', $studentProfileId)
            ->forceDelete();

        $count = 0;

        foreach ($bestGrades as $grade) {
            $studyPlan = $grade->studyPlanDetail?->studyPlan;
            $courseOffering = $grade->studyPlanDetail?->courseOffering;
            $courseId = $courseOffering?->course_id;

            if (! $courseId) {
                continue;
            }

            $credits = $this->resolveCredits($grade);
            $isCountedInGpa = in_array($grade->result_status, ['Passed', 'Failed'], true)
                && $credits > 0
                && $grade->grade_point !== null;

            TranscriptEntry::create([
                'student_profile_id' => $studentProfileId,
                'course_id' => $courseId,
                'student_grade_id' => $grade->id,
                'academic_year_id' => $studyPlan?->academic_year_id,
                'semester_no' => $studyPlan?->semester_no,
                'credits' => $credits,
                'final_score' => $grade->final_score,
                'letter_grade' => $grade->letter_grade,
                'grade_point' => $grade->grade_point,
                'result_status' => $grade->result_status,
                'is_counted_in_gpa' => $isCountedInGpa,
                'is_best_grade' => true,
                'notes' => $grade->notes,
                'created_by' => auth()->id(),
            ]);

            $count++;
        }

        return $count;
    }

    public function syncCumulativeGpaSnapshot(int $studentProfileId): void
    {
        $entries = TranscriptEntry::query()
            ->where('student_profile_id', $studentProfileId)
            ->where('is_counted_in_gpa', true)
            ->whereNotNull('grade_point')
            ->get();

        $credits = (float) $entries->sum(fn (TranscriptEntry $entry) => (int) ($entry->credits ?? 0));
        $weightedPoints = (float) $entries->sum(function (TranscriptEntry $entry) {
            return ((float) $entry->grade_point) * ((int) ($entry->credits ?? 0));
        });

        $cumulativeGpa = $credits > 0 ? round($weightedPoints / $credits, 2) : null;

        StudyResult::query()
            ->where('student_profile_id', $studentProfileId)
            ->update([
                'cumulative_gpa' => $cumulativeGpa,
                'updated_by' => auth()->id(),
            ]);
    }

    protected function finalizedGradesQuery(int $studentProfileId, ?int $academicYearId = null)
    {
        return StudentGrade::query()
            ->whereIn('grade_status', ['Finalized', 'Published'])
            ->whereHas('studyPlanDetail.studyPlan', function ($query) use ($studentProfileId, $academicYearId) {
                $query->where('student_profile_id', $studentProfileId);

                if ($academicYearId !== null) {
                    $query->where('academic_year_id', $academicYearId);
                }
            })
            ->with([
                'studyPlanDetail.studyPlan',
                'studyPlanDetail.courseOffering',
            ]);
    }

    protected function resolveCredits(StudentGrade $grade): int
    {
        return (int) (
            $grade->studyPlanDetail?->credits
            ?? $grade->studyPlanDetail?->courseOffering?->credits
            ?? 0
        );
    }
}
