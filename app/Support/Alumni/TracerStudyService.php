<?php

namespace App\Support\Alumni;

use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\TracerStudyCampaign;
use App\Models\Alumni\TracerStudyResponse;
use Illuminate\Support\Facades\DB;

class TracerStudyService
{
    /**
     * Activate a campaign: count target alumni, set status to active, update total_sent.
     */
    public function sendCampaign(TracerStudyCampaign $campaign): void
    {
        if ($campaign->status !== 'draft') {
            throw new \RuntimeException('Only draft campaigns can be sent.');
        }

        $targetCount = $this->countTargetAlumni($campaign);

        $campaign->update([
            'status' => 'active',
            'total_sent' => $targetCount,
            'updated_by' => auth()->id(),
        ]);
    }

    /**
     * Close all campaigns that have passed their end_date.
     * Returns the count of closed campaigns.
     */
    public function closeExpired(): int
    {
        $count = TracerStudyCampaign::query()
            ->where('status', 'active')
            ->where('end_date', '<', now()->toDateString())
            ->update([
                'status' => 'closed',
                'updated_by' => auth()->id(),
            ]);

        return $count;
    }

    /**
     * Submit a tracer study response from an alumni.
     */
    public function submitResponse(
        TracerStudyCampaign $campaign,
        AlumniProfile $alumniProfile,
        array $answers,
        array $employmentSnapshot
    ): TracerStudyResponse {
        if ($campaign->status !== 'active') {
            throw new \RuntimeException('Campaign is not active.');
        }

        // Check if already responded
        $existing = TracerStudyResponse::where('tracer_study_campaign_id', $campaign->id)
            ->where('alumni_profile_id', $alumniProfile->id)
            ->first();

        if ($existing) {
            throw new \RuntimeException('Alumni has already responded to this campaign.');
        }

        return DB::transaction(function () use ($campaign, $alumniProfile, $answers, $employmentSnapshot): TracerStudyResponse {
            $response = TracerStudyResponse::create([
                'tracer_study_campaign_id' => $campaign->id,
                'alumni_profile_id' => $alumniProfile->id,
                'answers' => $answers,
                'employment_status' => $employmentSnapshot['employment_status'] ?? null,
                'employer_name' => $employmentSnapshot['employer_name'] ?? null,
                'job_title' => $employmentSnapshot['job_title'] ?? null,
                'job_relevance' => $employmentSnapshot['job_relevance'] ?? null,
                'time_to_employment_months' => $employmentSnapshot['time_to_employment_months'] ?? null,
                'salary_range' => $employmentSnapshot['salary_range'] ?? null,
                'further_study' => $employmentSnapshot['further_study'] ?? false,
                'submitted_at' => now(),
            ]);

            $campaign->increment('total_responded');

            return $response;
        });
    }

    /**
     * Generate analytics for a campaign.
     */
    public function analytics(TracerStudyCampaign $campaign): array
    {
        $responses = TracerStudyResponse::query()
            ->where('tracer_study_campaign_id', $campaign->id)
            ->with(['alumniProfile.studyProgram'])
            ->get();

        $totalResponses = $responses->count();

        if ($totalResponses === 0) {
            return [
                'response_rate' => $campaign->responseRate(),
                'total_responses' => 0,
                'employment_distribution' => [],
                'job_relevance_breakdown' => [],
                'avg_time_to_employment' => null,
                'by_study_program' => [],
                'by_graduation_year' => [],
                'further_study_count' => 0,
                'salary_distribution' => [],
            ];
        }

        // Employment distribution
        $employmentDistribution = $responses
            ->groupBy('employment_status')
            ->map->count()
            ->sortDesc()
            ->toArray();

        // Job relevance breakdown
        $jobRelevanceBreakdown = $responses
            ->filter(fn ($r) => filled($r->job_relevance))
            ->groupBy('job_relevance')
            ->map->count()
            ->sortDesc()
            ->toArray();

        // Average time to employment (only for those who have it)
        $employedResponses = $responses->filter(fn ($r) => $r->time_to_employment_months !== null);
        $avgTimeToEmployment = $employedResponses->isNotEmpty()
            ? round($employedResponses->avg('time_to_employment_months'), 1)
            : null;

        // By study program
        $byStudyProgram = $responses
            ->filter(fn ($r) => $r->alumniProfile?->studyProgram)
            ->groupBy(fn ($r) => $r->alumniProfile->studyProgram->name)
            ->map(function ($group) {
                return [
                    'total' => $group->count(),
                    'working' => $group->where('employment_status', 'working')->count(),
                    'entrepreneur' => $group->where('employment_status', 'entrepreneur')->count(),
                    'studying' => $group->where('employment_status', 'studying')->count(),
                    'unemployed' => $group->where('employment_status', 'unemployed')->count(),
                ];
            })
            ->toArray();

        // By graduation year
        $byGraduationYear = $responses
            ->filter(fn ($r) => $r->alumniProfile?->graduation_year)
            ->groupBy(fn ($r) => $r->alumniProfile->graduation_year)
            ->map(function ($group) {
                return [
                    'total' => $group->count(),
                    'working' => $group->where('employment_status', 'working')->count(),
                    'entrepreneur' => $group->where('employment_status', 'entrepreneur')->count(),
                    'studying' => $group->where('employment_status', 'studying')->count(),
                    'unemployed' => $group->where('employment_status', 'unemployed')->count(),
                ];
            })
            ->toArray();

        // Further study count
        $furtherStudyCount = $responses->where('further_study', true)->count();

        // Salary distribution
        $salaryDistribution = $responses
            ->filter(fn ($r) => filled($r->salary_range))
            ->groupBy('salary_range')
            ->map->count()
            ->sortDesc()
            ->toArray();

        return [
            'response_rate' => $campaign->responseRate(),
            'total_responses' => $totalResponses,
            'employment_distribution' => $employmentDistribution,
            'job_relevance_breakdown' => $jobRelevanceBreakdown,
            'avg_time_to_employment' => $avgTimeToEmployment,
            'by_study_program' => $byStudyProgram,
            'by_graduation_year' => $byGraduationYear,
            'further_study_count' => $furtherStudyCount,
            'salary_distribution' => $salaryDistribution,
        ];
    }

    /**
     * Count how many alumni profiles match the campaign's target criteria.
     */
    private function countTargetAlumni(TracerStudyCampaign $campaign): int
    {
        $query = AlumniProfile::where('is_active', true);

        if (! empty($campaign->target_graduation_years)) {
            $query->whereIn('graduation_year', $campaign->target_graduation_years);
        }

        if (! empty($campaign->target_study_program_ids)) {
            $query->whereIn('study_program_id', $campaign->target_study_program_ids);
        }

        return $query->count();
    }
}
