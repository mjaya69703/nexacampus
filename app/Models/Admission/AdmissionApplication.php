<?php

namespace App\Models\Admission;

use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AdmissionApplication extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'admission_period_id',
        'application_number',
        'access_token',
        'user_id',
        'full_name',
        'email',
        'phone',
        'birth_date',
        'gender',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'high_school_name',
        'high_school_major',
        'high_school_graduation_year',
        'faculty_id',
        'study_program_id',
        'class_type',
        'status',
        'final_score',
        'review_notes',
        'reviewed_by',
        'submitted_at',
        'reviewed_at',
        'accepted_at',
        'converted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'high_school_graduation_year' => 'integer',
            'final_score' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('admission_application')
            ->logOnly(['application_number', 'full_name', 'email', 'study_program_id', 'status', 'final_score'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AdmissionPeriod::class, 'admission_period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AdmissionDocument::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AdmissionStatusHistory::class);
    }
}
