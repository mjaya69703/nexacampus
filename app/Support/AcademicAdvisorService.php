<?php

namespace App\Support;

use App\Models\Academic\AcademicAdvisorAssignment;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyPlan;
use App\Support\Notifications\NotificationDispatchService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicAdvisorService
{
    public function hasActiveAssignmentsForLecturer(?int $lecturerProfileId): bool
    {
        if (! $lecturerProfileId) {
            return false;
        }

        return $this->activeAssignmentQuery()
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->exists();
    }

    public function activeAdvisorForStudent(int $studentProfileId, ?int $academicYearId = null): ?AcademicAdvisorAssignment
    {
        return $this->activeAssignmentQuery()
            ->with(['lecturerProfile.user', 'academicYear'])
            ->where('student_profile_id', $studentProfileId)
            ->when(
                $academicYearId,
                fn (Builder $query) => $query->where(fn (Builder $nested) => $nested
                    ->where('academic_year_id', $academicYearId)
                    ->orWhereNull('academic_year_id')),
                fn (Builder $query) => $query->whereNull('academic_year_id')
            )
            ->orderByRaw('academic_year_id is null')
            ->latest('start_date')
            ->first();
    }

    public function assignedStudentsForLecturer(int $lecturerProfileId): Collection
    {
        return $this->activeAssignmentQuery()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'academicYear'])
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->orderByDesc('start_date')
            ->get();
    }

    public function createAssignment(array $payload): AcademicAdvisorAssignment
    {
        return DB::transaction(function () use ($payload) {
            if ((bool) ($payload['is_active'] ?? true)) {
                $this->ensureNoActiveConflict(
                    studentProfileId: (int) $payload['student_profile_id'],
                    academicYearId: $payload['academic_year_id'] ?? null,
                    startDate: $payload['start_date'] ?? null,
                    endDate: $payload['end_date'] ?? null,
                );
            }

            return AcademicAdvisorAssignment::query()->create($payload);
        });
    }

    public function updateAssignment(AcademicAdvisorAssignment $assignment, array $payload): AcademicAdvisorAssignment
    {
        return DB::transaction(function () use ($assignment, $payload) {
            if ((bool) ($payload['is_active'] ?? true)) {
                $this->ensureNoActiveConflict(
                    studentProfileId: (int) $payload['student_profile_id'],
                    academicYearId: $payload['academic_year_id'] ?? null,
                    startDate: $payload['start_date'] ?? null,
                    endDate: $payload['end_date'] ?? null,
                    ignoreId: $assignment->id,
                );
            }

            $assignment->update($payload);

            return $assignment->refresh();
        });
    }

    public function bulkAssign(array $studentProfileIds, array $payload): array
    {
        $studentProfileIds = collect($studentProfileIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $created = 0;
        $skipped = [];

        DB::transaction(function () use ($studentProfileIds, $payload, &$created, &$skipped): void {
            foreach ($studentProfileIds as $studentProfileId) {
                try {
                    if ((bool) ($payload['is_active'] ?? true)) {
                        $this->ensureNoActiveConflict(
                            studentProfileId: $studentProfileId,
                            academicYearId: $payload['academic_year_id'] ?? null,
                            startDate: $payload['start_date'] ?? null,
                            endDate: $payload['end_date'] ?? null,
                        );
                    }
                } catch (ValidationException) {
                    $skipped[] = $this->studentLabel($studentProfileId);

                    continue;
                }

                AcademicAdvisorAssignment::query()->create(array_merge($payload, [
                    'student_profile_id' => $studentProfileId,
                ]));

                $created++;
            }
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    public function ensureNoActiveConflict(
        int $studentProfileId,
        ?int $academicYearId,
        mixed $startDate,
        mixed $endDate,
        ?int $ignoreId = null,
    ): void {
        $start = $this->dateBoundary($startDate, false);
        $end = $this->dateBoundary($endDate, true);

        $conflict = AcademicAdvisorAssignment::query()
            ->with(['lecturerProfile.user', 'academicYear'])
            ->where('student_profile_id', $studentProfileId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->when($academicYearId !== null, function (Builder $query) use ($academicYearId) {
                $query->where(function (Builder $nested) use ($academicYearId) {
                    $nested->where('academic_year_id', $academicYearId)
                        ->orWhereNull('academic_year_id');
                });
            })
            ->where(function (Builder $query) use ($start, $end) {
                $query->where(function (Builder $nested) use ($end) {
                    $nested->whereNull('start_date')
                        ->orWhere('start_date', '<=', $end->toDateString());
                })->where(function (Builder $nested) use ($start) {
                    $nested->whereNull('end_date')
                        ->orWhere('end_date', '>=', $start->toDateString());
                });
            })
            ->first();

        if (! $conflict) {
            return;
        }

        $advisorName = $conflict->lecturerProfile?->user?->name ?? 'dosen lain';
        $year = $conflict->academicYear?->name ?? 'Umum';

        throw ValidationException::withMessages([
            'assignmentForm.student_profile_id' => "Mahasiswa ini sudah punya Dosen PA aktif ({$advisorName}) untuk {$year}.",
            'selectedStudentIds' => 'Sebagian mahasiswa sudah punya Dosen PA aktif untuk periode yang sama.',
        ]);
    }

    private function activeAssignmentQuery(): Builder
    {
        return AcademicAdvisorAssignment::query()
            ->where('is_active', true)
            ->where(function (Builder $query) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', now()->toDateString());
            })
            ->where(function (Builder $query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            });
    }

    private function dateBoundary(mixed $value, bool $end): CarbonInterface
    {
        if (filled($value)) {
            return Carbon::parse($value);
        }

        return $end ? now()->addYears(50) : now()->subYears(50);
    }

    private function studentLabel(int $studentProfileId): string
    {
        $student = StudentProfile::query()->with('user')->find($studentProfileId);

        return trim(($student?->nim ? $student->nim.' - ' : '').($student?->user?->name ?? "Mahasiswa #{$studentProfileId}"));
    }

    public function lecturerLabel(LecturerProfile $lecturer): string
    {
        return trim(($lecturer->nidn ?? $lecturer->nip ?? '-').' - '.($lecturer->user?->name ?? '-'));
    }

    /**
     * Apakah dosen adalah DPA aktif mahasiswa untuk tahun KRS tersebut.
     * Assignment umum (tanpa tahun) berlaku untuk semua tahun.
     */
    public function isAdvisorFor(int $lecturerProfileId, int $studentProfileId, ?int $academicYearId): bool
    {
        $assignment = $this->activeAdvisorForStudent($studentProfileId, $academicYearId);

        return $assignment !== null && (int) $assignment->lecturer_profile_id === (int) $lecturerProfileId;
    }

    /**
     * KRS Submitted milik mahasiswa bimbingan untuk diputuskan DPA.
     *
     * @return \Illuminate\Support\Collection<int, StudyPlan>
     */
    public function pendingPlansForStudent(int $studentProfileId): Collection
    {
        return StudyPlan::query()
            ->with(['academicYear:id,name'])
            ->withCount('details as details_count')
            ->withSum('details as total_credits', 'credits')
            ->where('student_profile_id', $studentProfileId)
            ->where('status', 'Submitted')
            ->orderByDesc('created_at')
            ->get();
    }

    public function approveStudyPlanAsAdvisor(int $lecturerProfileId, StudyPlan $plan, ?string $notes, int $byUserId): StudyPlan
    {
        return $this->decideStudyPlanAsAdvisor($lecturerProfileId, $plan, 'Approved', $notes, $byUserId);
    }

    public function rejectStudyPlanAsAdvisor(int $lecturerProfileId, StudyPlan $plan, ?string $notes, int $byUserId): StudyPlan
    {
        return $this->decideStudyPlanAsAdvisor($lecturerProfileId, $plan, 'Rejected', $notes, $byUserId);
    }

    private function decideStudyPlanAsAdvisor(int $lecturerProfileId, StudyPlan $plan, string $decision, ?string $notes, int $byUserId): StudyPlan
    {
        if ($plan->status !== 'Submitted') {
            throw ValidationException::withMessages([
                'status' => 'Hanya KRS berstatus Diajukan yang bisa diputuskan DPA.',
            ]);
        }

        if (! $this->isAdvisorFor($lecturerProfileId, (int) $plan->student_profile_id, $plan->academic_year_id)) {
            throw ValidationException::withMessages([
                'status' => 'Anda bukan Dosen PA aktif mahasiswa ini untuk tahun akademik tersebut.',
            ]);
        }

        return DB::transaction(function () use ($plan, $decision, $notes, $byUserId) {
            $payload = [
                'status' => $decision,
                'updated_by' => $byUserId,
            ];

            if ($decision === 'Approved') {
                $payload['approved_at'] = now();
                $payload['approved_by'] = $byUserId;
            } else {
                $payload['approved_at'] = null;
                $payload['approved_by'] = null;
            }

            if ($notes !== null && trim($notes) !== '') {
                $payload['notes'] = $notes;
            }

            $plan->update($payload);

            app(NotificationDispatchService::class)->studyPlanStatusUpdated($plan->fresh(), $plan->notes);

            return $plan->fresh();
        });
    }
}
