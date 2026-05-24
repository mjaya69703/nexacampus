<?php

namespace App\Support;

use App\Models\Academic\Assignment;
use App\Models\Academic\AssignmentGrade;
use App\Models\Academic\AssignmentStatusHistory;
use App\Models\Academic\AssignmentSubmission;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudentGradeComponent;
use App\Models\Academic\StudyPlanDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentGradeBookSyncService
{
    public function sync(Assignment $assignment, float $weightPercentage, int $actorId): array
    {
        return DB::transaction(function () use ($assignment, $weightPercentage, $actorId) {
            $componentName = $this->componentName($assignment);
            $submissions = $assignment->submissions()
                ->with(['grade', 'studentProfile.user'])
                ->whereHas('grade', fn ($query) => $query->where('status', 'graded')->whereNotNull('score'))
                ->get();

            $syncRows = [];
            $skipped = 0;
            $lockedStudents = [];
            $overWeightStudents = [];

            foreach ($submissions as $submission) {
                $detail = $this->studyPlanDetail($assignment, $submission);

                if (! $detail || ! $submission->grade) {
                    $skipped++;

                    continue;
                }

                $studentGrade = StudentGrade::query()->firstOrCreate(
                    ['study_plan_detail_id' => $detail->id],
                    [
                        'grade_status' => 'Draft',
                        'graded_by' => $actorId,
                        'created_by' => $actorId,
                    ],
                );

                if (in_array($studentGrade->grade_status, ['Finalized', 'Published'], true)) {
                    $lockedStudents[] = $this->studentLabel($submission).' ('.$studentGrade->grade_status.')';

                    continue;
                }

                $currentWeight = (float) $studentGrade->components()
                    ->where('name', '!=', $componentName)
                    ->sum('weight_percentage');
                $nextWeight = round($currentWeight + $weightPercentage, 2);

                if ($nextWeight > 100) {
                    $overWeightStudents[] = $this->studentLabel($submission).' ('.$currentWeight.'% + '.$weightPercentage.'% = '.$nextWeight.'%)';

                    continue;
                }

                $syncRows[] = [$studentGrade, $submission];
            }

            if ($lockedStudents !== []) {
                throw ValidationException::withMessages([
                    'gradeBookWeight' => 'Sync gagal. Grade book sudah locked untuk: '.implode(', ', array_slice($lockedStudents, 0, 5)).(count($lockedStudents) > 5 ? ', dan lainnya.' : '.'),
                ]);
            }

            if ($overWeightStudents !== []) {
                throw ValidationException::withMessages([
                    'gradeBookWeight' => 'Sync gagal. Total bobot akan melebihi 100% untuk: '.implode(', ', array_slice($overWeightStudents, 0, 5)).(count($overWeightStudents) > 5 ? ', dan lainnya.' : '.'),
                ]);
            }

            $synced = 0;

            foreach ($syncRows as [$studentGrade, $submission]) {
                $componentScore = $this->normaliseScore($submission->grade, $assignment);

                StudentGradeComponent::query()->updateOrCreate(
                    [
                        'student_grade_id' => $studentGrade->id,
                        'name' => $componentName,
                    ],
                    [
                        'weight_percentage' => $weightPercentage,
                        'score' => $componentScore,
                        'notes' => $this->componentNotes($submission),
                        'sort_order' => 10_000 + $assignment->id,
                        'updated_by' => $actorId,
                        'created_by' => $actorId,
                    ],
                );

                $studentGrade->load('components');

                $calculator = new StudentGradeCalculator;
                $snapshot = $calculator->buildSnapshot($studentGrade);

                $studentGrade->update(array_merge($snapshot, [
                    'graded_by' => $actorId,
                    'graded_at' => now(),
                    'updated_by' => $actorId,
                ]));

                $synced++;
            }

            AssignmentStatusHistory::query()->create([
                'assignment_id' => $assignment->id,
                'actor_id' => $actorId,
                'status' => 'gradebook_synced',
                'note' => "Synced {$synced} graded submissions to grade book.",
                'meta' => [
                    'component_name' => $componentName,
                    'weight_percentage' => $weightPercentage,
                    'synced' => $synced,
                    'skipped' => $skipped,
                ],
            ]);

            return [
                'component_name' => $componentName,
                'synced' => $synced,
                'skipped' => $skipped,
            ];
        });
    }

    public function componentName(Assignment $assignment): string
    {
        return str($assignment->title)
            ->limit(170, '')
            ->prepend('Tugas: ')
            ->toString();
    }

    private function studyPlanDetail(Assignment $assignment, AssignmentSubmission $submission): ?StudyPlanDetail
    {
        return StudyPlanDetail::query()
            ->where('course_offering_id', $assignment->course_offering_id)
            ->whereHas('studyPlan', fn ($query) => $query->where('student_profile_id', $submission->student_profile_id))
            ->first();
    }

    private function normaliseScore(AssignmentGrade $grade, Assignment $assignment): float
    {
        $maxScore = max((float) $assignment->max_score, 1);

        return round(min(100, max(0, ((float) $grade->score / $maxScore) * 100)), 2);
    }

    private function componentNotes(AssignmentSubmission $submission): string
    {
        $submittedAt = $submission->submitted_at?->format('d M Y H:i') ?? '-';
        $status = $submission->status ?: 'submitted';

        return "Synced from assignment submission ({$status}) submitted at {$submittedAt}.";
    }

    private function studentLabel(AssignmentSubmission $submission): string
    {
        return $submission->studentProfile?->nim
            ? $submission->studentProfile->nim.' - '.($submission->studentProfile->user?->name ?? 'Mahasiswa')
            : ($submission->studentProfile?->user?->name ?? 'Mahasiswa');
    }
}
