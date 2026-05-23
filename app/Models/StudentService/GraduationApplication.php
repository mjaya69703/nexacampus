<?php

namespace App\Models\StudentService;

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GraduationApplication extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'application_number',
        'student_profile_id',
        'academic_period_id',
        'graduation_batch_id',
        'graduation_period',
        'thesis_title',
        'reason',
        'attachment_path',
        'attachment_name',
        'status',
        'eligibility_snapshot',
        'admin_checklist',
        'student_notes',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'finalized_by',
        'finalized_at',
        'graduation_date',
    ];

    protected function casts(): array
    {
        return [
            'eligibility_snapshot' => 'array',
            'admin_checklist' => 'array',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'finalized_at' => 'datetime',
            'graduation_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('graduation_application')
            ->logOnly(['application_number', 'student_profile_id', 'academic_period_id', 'graduation_batch_id', 'graduation_period', 'status', 'graduation_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function graduationBatch(): BelongsTo
    {
        return $this->belongsTo(GraduationBatch::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(GraduationStatusHistory::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(GraduationDocument::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
