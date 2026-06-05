<?php

namespace App\Models\Academic;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentAdvisorNote extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'academic_advisor_assignment_id',
        'student_profile_id',
        'lecturer_profile_id',
        'topic',
        'notes',
        'recommendation',
        'follow_up_at',
        'status',
        'visible_to_student',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_at' => 'date',
            'visible_to_student' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('student_advisor_note')
            ->logOnly(['student_profile_id', 'lecturer_profile_id', 'topic', 'status', 'follow_up_at', 'visible_to_student'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(AcademicAdvisorAssignment::class, 'academic_advisor_assignment_id');
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function lecturerProfile(): BelongsTo
    {
        return $this->belongsTo(LecturerProfile::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
