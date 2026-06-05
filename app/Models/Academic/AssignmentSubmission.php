<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssignmentSubmission extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'assignment_id',
        'student_profile_id',
        'content',
        'submission_text',
        'status',
        'submitted_at',
        'last_resubmitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'last_resubmitted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('assignment_submission')
            ->logOnly(['assignment_id', 'student_profile_id', 'status', 'submitted_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(AssignmentSubmissionFile::class);
    }

    public function grade(): HasOne
    {
        return $this->hasOne(AssignmentGrade::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(AssignmentStatusHistory::class);
    }
}
