<?php

namespace App\Models\Alumni;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TracerStudyResponse extends Model
{
    protected $fillable = [
        'tracer_study_campaign_id',
        'alumni_profile_id',
        'answers',
        'employment_status',
        'employer_name',
        'job_title',
        'job_relevance',
        'time_to_employment_months',
        'salary_range',
        'further_study',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'further_study' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(TracerStudyCampaign::class, 'tracer_study_campaign_id');
    }

    public function alumniProfile(): BelongsTo
    {
        return $this->belongsTo(AlumniProfile::class);
    }
}
