<?php

namespace App\Support\Organization;

use App\Models\Academic\CourseOffering;
use App\Models\Academic\LecturerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AcademicLeaderContext
{
    public function __construct(private readonly PositionScopeResolver $scopeResolver) {}

    public function facultyIds(?User $user = null): array
    {
        return $this->scopeResolver->managedFacultyIds($user ?: auth()->user());
    }

    public function studyProgramIds(?User $user = null): array
    {
        return $this->scopeResolver->managedStudyProgramIds($user ?: auth()->user());
    }

    public function hasScope(?User $user = null): bool
    {
        return ! empty($this->facultyIds($user)) || ! empty($this->studyProgramIds($user));
    }

    public function applyCourseOfferingScope(Builder $query, ?User $user = null): Builder
    {
        $facultyIds = $this->facultyIds($user);
        $studyProgramIds = $this->studyProgramIds($user);

        return $query->where(function (Builder $nested) use ($facultyIds, $studyProgramIds) {
            if (! empty($studyProgramIds)) {
                $nested->orWhereIn('study_program_id', $studyProgramIds);
            }

            if (! empty($facultyIds)) {
                $nested->orWhereHas('studyProgram', fn (Builder $program) => $program->whereIn('faculty_id', $facultyIds));
            }

            if (empty($facultyIds) && empty($studyProgramIds)) {
                $nested->whereRaw('1 = 0');
            }
        });
    }

    public function applyLecturerProfileScope(Builder $query, ?User $user = null): Builder
    {
        $facultyIds = $this->facultyIds($user);
        $studyProgramIds = $this->studyProgramIds($user);

        return $query->where(function (Builder $nested) use ($facultyIds, $studyProgramIds) {
            if (! empty($studyProgramIds)) {
                $nested->orWhereIn('study_program_id', $studyProgramIds);
            }

            if (! empty($facultyIds)) {
                $nested->orWhereIn('faculty_id', $facultyIds);
            }

            if (empty($facultyIds) && empty($studyProgramIds)) {
                $nested->whereRaw('1 = 0');
            }
        });
    }

    public function scopedLecturerProfileQuery(?User $user = null): Builder
    {
        return $this->applyLecturerProfileScope(LecturerProfile::query(), $user);
    }

    public function canAccessLecturerProfile(LecturerProfile|int $profile, ?User $user = null): bool
    {
        $id = $profile instanceof LecturerProfile ? $profile->id : $profile;

        return $this->scopedLecturerProfileQuery($user)->whereKey($id)->exists();
    }

    public function scopedCourseOfferingIds(?User $user = null): array
    {
        return $this->applyCourseOfferingScope(CourseOffering::query(), $user)
            ->pluck('id')
            ->all();
    }
}
