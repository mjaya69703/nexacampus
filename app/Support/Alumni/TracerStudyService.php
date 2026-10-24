<?php

namespace App\Support\Alumni;

use App\Enums\EmploymentStatus;
use App\Enums\JobRelevance;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\TracerStudyCampaign;
use App\Models\Alumni\TracerStudyResponse;
use Illuminate\Support\Facades\DB;

class TracerStudyService
{
    /**
     * Activate a campaign: count target alumni, set status to active, update
     * the delivery counter and reconcile the response counter with the
     * responses that actually exist.
     */
    public function sendCampaign(TracerStudyCampaign $campaign): void
    {
        if ($campaign->status !== 'draft') {
            throw new \RuntimeException('Only draft campaigns can be sent.');
        }

        $responded = $campaign->responses()->count();
        $targetCount = $this->countTargetAlumni($campaign);

        $campaign->update([
            'status' => 'active',
            // Counter tidak boleh lebih kecil dari jumlah respon yang sudah masuk.
            'total_sent' => max($targetCount, $responded),
            'total_responded' => $responded,
            'updated_by' => auth()->id(),
        ]);
    }

    /**
     * Keep total_sent / total_responded aligned with the real data.
     *
     * Dipakai setelah create/update kampanye: status "active" menghitung ulang
     * sasaran alumni, sedangkan total_responded selalu disamakan dengan jumlah
     * respon yang tersimpan agar response rate tidak melenceng.
     */
    public function syncCounters(TracerStudyCampaign $campaign): void
    {
        $campaign->refresh();

        $responded = $campaign->responses()->count();
        $target = $this->countTargetAlumni($campaign);
        $sent = (int) $campaign->total_sent;
        $status = $campaign->status;

        if ($status === 'active') {
            $sent = max($target, $responded);
        } elseif ($sent < $responded) {
            $sent = $responded;
        }

        if ($sent !== (int) $campaign->total_sent || $responded !== (int) $campaign->total_responded) {
            $campaign->update([
                'total_sent' => $sent,
                'total_responded' => $responded,
            ]);
        }
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
     *
     * Struktur data dikunci agar cocok dengan tabel laporan:
     * - employment_distribution / job_relevance_breakdown: [value => ['label','count','percentage']]
     * - salary_distribution: [range => ['label','count']]
     * - by_study_program / by_graduation_year: [name => ['responses','working',...]]
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

        $employmentDistribution = $responses
            ->groupBy('employment_status')
            ->map(function ($group, $status) use ($totalResponses) {
                $count = $group->count();

                return [
                    'label' => EmploymentStatus::tryFrom((string) $status)?->label()
                        ?? (filled($status) ? str((string) $status)->replace('_', ' ')->title()->toString() : 'Tidak diisi'),
                    'count' => $count,
                    'percentage' => round(($count / $totalResponses) * 100, 1),
                ];
            })
            ->sortByDesc('count')
            ->toArray();

        $jobRelevanceBreakdown = $responses
            ->filter(fn ($response) => filled($response->job_relevance))
            ->groupBy('job_relevance')
            ->map(function ($group, $relevance) use ($totalResponses) {
                $count = $group->count();

                return [
                    'label' => JobRelevance::tryFrom((string) $relevance)?->label()
                        ?? str((string) $relevance)->replace('_', ' ')->title()->toString(),
                    'count' => $count,
                    'percentage' => round(($count / $totalResponses) * 100, 1),
                ];
            })
            ->sortByDesc('count')
            ->toArray();

        $employedResponses = $responses->filter(fn ($response) => $response->time_to_employment_months !== null);
        $avgTimeToEmployment = $employedResponses->isNotEmpty()
            ? round($employedResponses->avg('time_to_employment_months'), 1)
            : null;

        $byStudyProgram = $responses
            ->filter(fn ($response) => $response->alumniProfile?->studyProgram)
            ->groupBy(fn ($response) => $response->alumniProfile->studyProgram->name)
            ->map(fn ($group) => $this->employmentSummary($group))
            ->sortByDesc('responses')
            ->toArray();

        $byGraduationYear = $responses
            ->filter(fn ($response) => $response->alumniProfile?->graduation_year)
            ->groupBy(fn ($response) => $response->alumniProfile->graduation_year)
            ->map(fn ($group) => $this->employmentSummary($group))
            ->sortKeysDesc()
            ->toArray();

        $furtherStudyCount = $responses->where('further_study', true)->count();

        $salaryDistribution = $responses
            ->filter(fn ($response) => filled($response->salary_range))
            ->groupBy('salary_range')
            ->map(fn ($group, $range) => [
                'label' => filled($range) ? (string) $range : '(Tidak diisi)',
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
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
     * @param  \Illuminate\Support\Collection<int, TracerStudyResponse>  $group
     * @return array<string, int>
     */
    private function employmentSummary($group): array
    {
        return [
            'responses' => $group->count(),
            'working' => $group->where('employment_status', EmploymentStatus::Working->value)->count(),
            'entrepreneur' => $group->where('employment_status', EmploymentStatus::Entrepreneur->value)->count(),
            'studying' => $group->where('employment_status', EmploymentStatus::Studying->value)->count(),
            'unemployed' => $group->where('employment_status', EmploymentStatus::Unemployed->value)->count(),
            'other' => $group->where('employment_status', EmploymentStatus::Other->value)->count(),
        ];
    }

    /**
     * Count how many alumni profiles match the campaign's target criteria.
     */
    public function countTargetAlumni(TracerStudyCampaign $campaign): int
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
