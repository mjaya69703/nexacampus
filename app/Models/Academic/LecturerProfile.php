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

class LecturerProfile extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'lecturer_profiles';

    protected $fillable = [
        'user_id',
        'faculty_id',
        'study_program_id',
        'nidn',
        'nidk',
        'nip',
        'employment_status',
        'join_date',
        'is_active',
        'desc',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('lecturer_profile')
            ->logOnly(['nidn', 'nidk', 'nip', 'employment_status', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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

    public function advisorAssignments(): HasMany
    {
        return $this->hasMany(AcademicAdvisorAssignment::class);
    }

    public function gradeAppeals(): HasMany
    {
        return $this->hasMany(GradeAppeal::class);
    }
}
