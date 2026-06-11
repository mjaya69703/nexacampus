<?php

namespace App\Models\Alumni;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class JobPosting extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'employer_partner_id',
        'title',
        'company_name',
        'industry',
        'description',
        'requirements',
        'location',
        'job_type',
        'salary_range',
        'apply_url',
        'contact_email',
        'posted_date',
        'deadline_date',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'posted_date' => 'date',
            'deadline_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('job_posting')
            ->logOnly(['title', 'company_name', 'job_type', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function employerPartner(): BelongsTo
    {
        return $this->belongsTo(EmployerPartner::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
