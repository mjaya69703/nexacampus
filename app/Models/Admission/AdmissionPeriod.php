<?php

namespace App\Models\Admission;

use App\Models\Academic\AcademicYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AdmissionPeriod extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'academic_year_id',
        'academic_year',
        'wave',
        'opens_at',
        'closes_at',
        'is_active',
        'is_published',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'academic_year' => 'integer',
            'wave' => 'integer',
            'opens_at' => 'date',
            'closes_at' => 'date',
            'is_active' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('admission_period')
            ->logOnly(['name', 'code', 'academic_year_id', 'academic_year', 'wave', 'opens_at', 'closes_at', 'is_active', 'is_published'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(AdmissionApplication::class);
    }

    public function documentRequirements(): HasMany
    {
        return $this->hasMany(AdmissionDocumentRequirement::class);
    }

    public function examSchedules(): HasMany
    {
        return $this->hasMany(AdmissionExamSchedule::class);
    }

    public function quotas(): HasMany
    {
        return $this->hasMany(AdmissionQuota::class);
    }
}
