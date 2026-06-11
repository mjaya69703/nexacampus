<?php

namespace App\Models\Alumni;

use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Academic\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AlumniProfile extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'user_id',
        'student_profile_id',
        'nim',
        'full_name',
        'graduation_date',
        'graduation_year',
        'study_program_id',
        'faculty_id',
        'gpa',
        'birth_date',
        'gender',
        'email',
        'phone',
        'address',
        'current_city',
        'current_province',
        'employment_status',
        'employer_name',
        'job_title',
        'job_industry',
        'linkedin_url',
        'photo_path',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'graduation_date' => 'date',
            'birth_date' => 'date',
            'gpa' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('alumni_profile')
            ->logOnly(['nim', 'full_name', 'employment_status', 'is_active', 'graduation_year'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function tracerStudyResponses(): HasMany
    {
        return $this->hasMany(TracerStudyResponse::class);
    }

    public function eventParticipants(): HasMany
    {
        return $this->hasMany(AlumniEventParticipant::class);
    }

    public function profileCompleteness(): int
    {
        $fields = ['phone', 'current_city', 'employment_status', 'linkedin_url'];
        $filled = collect($fields)->filter(fn ($f) => filled($this->$f))->count();

        return (int) round(($filled / count($fields)) * 100);
    }
}
