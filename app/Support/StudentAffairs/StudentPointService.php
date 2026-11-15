<?php

namespace App\Support\StudentAffairs;

use App\Enums\EventRegistrationStatus;
use App\Enums\PointEntryCategory;
use App\Models\StudentAffairs\StudentEventRegistration;
use App\Models\StudentAffairs\StudentPointEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class StudentPointService
{
    /**
     * Beri poin ke mahasiswa. Bila source diberikan, pencatatan bersifat
     * idempoten: satu source hanya menghasilkan satu entri poin.
     */
    public function award(
        User $user,
        int $points,
        PointEntryCategory $category,
        string $description,
        ?Model $source = null,
        ?User $awarder = null,
        ?string $awardedAt = null,
    ): StudentPointEntry {
        $payload = [
            'user_id' => $user->id,
            'points' => $points,
            'category' => $category->value,
            'description' => $description,
            'awarded_at' => $awardedAt ?? now()->toDateString(),
            'awarded_by' => $awarder?->id,
            'updated_by' => $awarder?->id,
        ];

        if ($source !== null) {
            $existing = StudentPointEntry::withTrashed()
                ->where('source_type', $source::class)
                ->where('source_id', $source->getKey())
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->fill($payload);
                $existing->save();

                return $existing;
            }

            $payload['source_type'] = $source::class;
            $payload['source_id'] = $source->getKey();
            $payload['created_by'] = $awarder?->id;
        } else {
            $payload['created_by'] = $awarder?->id;
        }

        return StudentPointEntry::create($payload);
    }

    public function awardEventAttendance(StudentEventRegistration $registration): ?StudentPointEntry
    {
        $registration->loadMissing('event');

        if ($registration->status !== EventRegistrationStatus::ATTENDED) {
            return null;
        }

        $points = (int) ($registration->event?->points ?? 0);

        if ($points <= 0) {
            return null;
        }

        $entry = $this->award(
            $registration->user,
            $points,
            PointEntryCategory::EVENT,
            'Kehadiran pada event "'.$registration->event->title.'"',
            $registration,
            null,
            $registration->attended_at?->toDateString() ?? now()->toDateString(),
        );

        $registration->forceFill(['points_awarded' => $points])->save();

        return $entry;
    }

    public function revokeEventAttendance(StudentEventRegistration $registration): void
    {
        StudentPointEntry::withTrashed()
            ->where('source_type', $registration::class)
            ->where('source_id', $registration->getKey())
            ->get()
            ->each(fn (StudentPointEntry $entry) => $entry->trashed() ?: $entry->delete());

        $registration->forceFill(['points_awarded' => 0])->save();
    }

    public function totalFor(User $user): int
    {
        return (int) StudentPointEntry::query()
            ->where('user_id', $user->id)
            ->sum('points');
    }

    /**
     * @return Collection<int, array{rank: int, user: User, total: int}>
     */
    public function leaderboard(?int $facultyId = null, ?int $programId = null, int $limit = 20): Collection
    {
        $rows = $this->leaderboardQuery($facultyId, $programId)
            ->limit($limit)
            ->with([
                'user:id,first_name,last_name,username',
                'user.studentProfile:id,user_id,study_program_id,nim,current_semester',
                'user.studentProfile.studyProgram:id,name',
            ])
            ->get();

        return $rows->values()->map(fn (StudentPointEntry $row, int $index) => [
            'rank' => $index + 1,
            'user' => $row->user,
            'total' => (int) $row->total_points,
        ]);
    }

    public function rankFor(User $user, ?int $facultyId = null, ?int $programId = null): int
    {
        $totals = $this->leaderboardQuery($facultyId, $programId)->pluck('total_points', 'user_id');
        $mine = (int) ($totals[$user->id] ?? 0);

        return $totals->filter(fn ($total) => (int) $total > $mine)->count() + 1;
    }

    private function leaderboardQuery(?int $facultyId, ?int $programId)
    {
        return StudentPointEntry::query()
            ->selectRaw('user_id, SUM(points) as total_points')
            ->whereHas('user.studentProfile', function ($query) use ($facultyId, $programId) {
                if ($programId) {
                    $query->where('study_program_id', $programId);
                }

                if ($facultyId) {
                    $query->whereHas('studyProgram', fn ($sub) => $sub->where('faculty_id', $facultyId));
                }
            })
            ->groupBy('user_id')
            ->orderByDesc('total_points')
            ->orderBy('user_id');
    }
}
