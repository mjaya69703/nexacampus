<?php

namespace App\Support\Organization;

use App\Models\Organization\EmployeePositionAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

class PositionScopeResolver
{
    public function managedFacultyIds(?User $user): array
    {
        return $this->activeAssignments($user)
            ->pluck('faculty_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function managedStudyProgramIds(?User $user): array
    {
        return $this->activeAssignments($user)
            ->pluck('study_program_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function managedWorkUnitIds(?User $user): array
    {
        return $this->activeAssignments($user)
            ->pluck('work_unit_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function canAccessFaculty(?User $user, ?int $facultyId): bool
    {
        if (! $facultyId) {
            return false;
        }

        return in_array($facultyId, $this->managedFacultyIds($user), true);
    }

    public function canAccessStudyProgram(?User $user, ?int $studyProgramId): bool
    {
        if (! $studyProgramId) {
            return false;
        }

        return in_array($studyProgramId, $this->managedStudyProgramIds($user), true);
    }

    public function canAccessWorkUnit(?User $user, ?int $workUnitId): bool
    {
        if (! $workUnitId) {
            return false;
        }

        return in_array($workUnitId, $this->managedWorkUnitIds($user), true);
    }

    private function activeAssignments(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        return EmployeePositionAssignment::query()
            ->whereHas('employeeProfile', fn ($query) => $query->where('user_id', $user->id))
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', now()->toDateString());
            })
            ->get();
    }
}
