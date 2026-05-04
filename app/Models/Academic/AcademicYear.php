<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AcademicYear extends Model
{
    use LogsActivity, SoftDeletes;

    /**
     * Get the options for activity logging.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('academic-year')
            ->logOnly(['name', 'code', 'semester', 'start_date', 'end_date', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'name',
        'code',
        'semester',
        'start_date',
        'end_date',
        'is_active',
        'desc',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function academicPeriods(): HasMany
    {
        return $this->hasMany(AcademicPeriod::class);
    }

    public function advisorAssignments(): HasMany
    {
        return $this->hasMany(AcademicAdvisorAssignment::class);
    }
}
