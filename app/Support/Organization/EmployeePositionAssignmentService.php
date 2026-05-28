<?php

namespace App\Support\Organization;

use App\Models\Organization\EmployeePositionAssignment;
use App\Models\Organization\OrganizationalPosition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeePositionAssignmentService
{
    public function create(array $payload): EmployeePositionAssignment
    {
        return DB::transaction(function () use ($payload): EmployeePositionAssignment {
            $this->validateScope($payload);

            $assignment = EmployeePositionAssignment::create($payload);
            $this->syncWorkUnitMembership($assignment);

            return $assignment->refresh();
        });
    }

    public function update(EmployeePositionAssignment $assignment, array $payload): EmployeePositionAssignment
    {
        return DB::transaction(function () use ($assignment, $payload): EmployeePositionAssignment {
            $this->validateScope($payload);

            $assignment->update($payload);
            $this->syncWorkUnitMembership($assignment->refresh());

            return $assignment->refresh();
        });
    }

    public function syncWorkUnitMembership(EmployeePositionAssignment $assignment): void
    {
        if (! $assignment->work_unit_id || ! $assignment->isCurrentlyActive()) {
            return;
        }

        $assignment->loadMissing(['employeeProfile.user', 'position']);
        $user = $assignment->employeeProfile?->user;

        if (! $user) {
            return;
        }

        $position = match ($assignment->position?->code) {
            'KEPALA_UNIT' => 'head',
            'STAFF_UNIT', 'TENDIK' => 'member',
            default => 'member',
        };

        $user->workUnits()->syncWithoutDetaching([
            $assignment->work_unit_id => [
                'position' => $position,
                'is_active' => true,
            ],
        ]);
    }

    private function validateScope(array $payload): void
    {
        $position = OrganizationalPosition::find($payload['organizational_position_id'] ?? null);

        if (! $position) {
            return;
        }

        $errors = [];

        if ($position->scope_type === 'faculty' && blank($payload['faculty_id'] ?? null)) {
            $errors['faculty_id'] = 'Scope fakultas wajib dipilih untuk jabatan ini.';
        }

        if ($position->scope_type === 'study_program' && blank($payload['study_program_id'] ?? null)) {
            $errors['study_program_id'] = 'Scope program studi wajib dipilih untuk jabatan ini.';
        }

        if ($position->scope_type === 'work_unit' && blank($payload['work_unit_id'] ?? null)) {
            $errors['work_unit_id'] = 'Scope unit kerja wajib dipilih untuk jabatan ini.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
