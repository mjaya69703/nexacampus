<?php

namespace App\Models\StudentAffairs;

use App\Enums\OrganizationType;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentOrganization extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'faculty_id',
        'study_program_id',
        'advisor_id',
        'description',
        'logo_path',
        'founded_at',
        'contact_email',
        'contact_phone',
        'instagram_url',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
            'founded_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('student_organization')
            ->logOnly(['name', 'type', 'is_active', 'faculty_id', 'study_program_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(StudentOrganizationMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('is_active', true);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(StudentAchievement::class);
    }
}
