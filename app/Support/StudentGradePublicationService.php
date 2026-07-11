<?php

namespace App\Support;

use App\Models\Academic\StudentGrade;
use App\Support\Notifications\NotificationDispatchService;

class StudentGradePublicationService
{
    public function publish(StudentGrade $grade, ?int $actorId = null): bool
    {
        $grade->loadMissing('studyPlanDetail.studyPlan');

        if ($grade->grade_status !== 'Finalized') {
            return false;
        }

        $grade->update([
            'grade_status' => 'Published',
            'graded_at' => $grade->graded_at ?? now(),
            'updated_by' => $actorId,
        ]);

        $studentProfileId = (int) ($grade->studyPlanDetail?->studyPlan?->student_profile_id ?? 0);
        $academicYearId = (int) ($grade->studyPlanDetail?->studyPlan?->academic_year_id ?? 0);

        if ($studentProfileId > 0) {
            app(TranscriptSyncService::class)->syncStudent($studentProfileId, $academicYearId > 0 ? $academicYearId : null);
        }

        app(NotificationDispatchService::class)->gradePublished($grade->refresh());

        return true;
    }
}
