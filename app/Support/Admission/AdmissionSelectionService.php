<?php

namespace App\Support\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionQuota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdmissionSelectionService
{
    public function recalculateFinalScore(AdmissionApplication $application): float
    {
        $scores = $application->scores()->get(['score', 'weight']);

        if ($scores->isEmpty()) {
            $application->update(['final_score' => null]);

            return 0.0;
        }

        $weightTotal = max(1, (float) $scores->sum('weight'));
        $finalScore = round((float) $scores->sum(fn ($score) => ((float) $score->score) * ((float) $score->weight)) / $weightTotal, 2);

        $application->update(['final_score' => $finalScore]);

        return $finalScore;
    }

    public function rankingQuery(array $filters = []): Builder
    {
        return AdmissionApplication::query()
            ->with(['period', 'faculty', 'studyProgram'])
            ->withCount(['documents', 'scores'])
            ->when($filters['period_id'] ?? null, fn (Builder $query, $periodId) => $query->where('admission_period_id', $periodId))
            ->when($filters['study_program_id'] ?? null, fn (Builder $query, $programId) => $query->where('study_program_id', $programId))
            ->when($filters['class_type'] ?? null, fn (Builder $query, $classType) => $query->where('class_type', $classType))
            ->whereIn('status', ['submitted', 'under_review', 'accepted', 'waitlisted'])
            ->orderByDesc(DB::raw('COALESCE(final_score, 0)'))
            ->orderBy('submitted_at');
    }

    public function quotaFor(AdmissionApplication $application): ?AdmissionQuota
    {
        return AdmissionQuota::query()
            ->where('admission_period_id', $application->admission_period_id)
            ->where(function (Builder $query) use ($application) {
                $query->whereNull('study_program_id')
                    ->orWhere('study_program_id', $application->study_program_id);
            })
            ->where(function (Builder $query) use ($application) {
                $query->whereNull('class_type')
                    ->orWhere('class_type', $application->class_type);
            })
            ->orderByRaw('CASE WHEN study_program_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN class_type IS NULL THEN 1 ELSE 0 END')
            ->first();
    }

    public function refreshAcceptedCounts(?int $periodId = null): void
    {
        AdmissionQuota::query()
            ->when($periodId, fn (Builder $query) => $query->where('admission_period_id', $periodId))
            ->get()
            ->each(function (AdmissionQuota $quota) {
                $accepted = AdmissionApplication::query()
                    ->where('admission_period_id', $quota->admission_period_id)
                    ->where('status', 'accepted')
                    ->when($quota->faculty_id, fn (Builder $query) => $query->where('faculty_id', $quota->faculty_id))
                    ->when($quota->study_program_id, fn (Builder $query) => $query->where('study_program_id', $quota->study_program_id))
                    ->when($quota->class_type, fn (Builder $query) => $query->where('class_type', $quota->class_type))
                    ->count();

                $quota->update(['accepted_count' => $accepted]);
            });
    }

    public function rankedRows(array $filters = []): Collection
    {
        return $this->rankingQuery($filters)
            ->get()
            ->values()
            ->map(function (AdmissionApplication $application, int $index) {
                $quota = $this->quotaFor($application);
                $application->setAttribute('rank_position', $index + 1);
                $application->setAttribute('quota_limit', $quota?->quota);
                $application->setAttribute('quota_used', $quota?->accepted_count);

                return $application;
            });
    }
}
