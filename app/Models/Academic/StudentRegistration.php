<?php

namespace App\Models\Academic;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentRegistration extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'student_registrations';

    protected $fillable = [
        'student_profile_id',
        'academic_year_id',
        'semester_no',
        'registration_status',
        'academic_status',
        'registration_date',
        'submitted_at',
        'approved_at',
        'approved_by',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'registration_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('student_registration')
            ->logOnly(['registration_status', 'academic_status', 'semester_no', 'approved_by', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
