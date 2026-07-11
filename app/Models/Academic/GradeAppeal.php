<?php

namespace App\Models\Academic;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GradeAppeal extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'grade_appeals';

    protected $fillable = [
        'student_grade_id',
        'student_profile_id',
        'course_offering_id',
        'lecturer_profile_id',
        'student_user_id',
        'reviewed_by',
        'status',
        'reason_category',
        'reason',
        'expected_outcome',
        'lecturer_response',
        'original_score',
        'requested_score',
        'resolved_score',
        'submitted_at',
        'reviewed_at',
        'resolved_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'original_score' => 'decimal:2',
            'requested_score' => 'decimal:2',
            'resolved_score' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('grade_appeal')
            ->logOnly(['student_grade_id', 'status', 'reason_category', 'requested_score', 'resolved_score'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function studentGrade(): BelongsTo
    {
        return $this->belongsTo(StudentGrade::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function lecturerProfile(): BelongsTo
    {
        return $this->belongsTo(LecturerProfile::class);
    }

    public function studentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(GradeAppealAttachment::class);
    }
}
