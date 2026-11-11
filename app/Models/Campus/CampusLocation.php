<?php

namespace App\Models\Campus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CampusLocation extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('campus_location')
            ->logOnly(['name', 'code', 'is_main', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'email',
        'latitude',
        'longitude',
        'radius_meters',
        'is_main',
        'is_attendance_point',
        'is_active',
        'desc',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'is_attendance_point' => 'boolean',
            'is_active' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'integer',
        ];
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    public function scopeAttendancePoints($query)
    {
        return $query->where('is_attendance_point', true);
    }

    public function attendanceCheckIns(): HasMany
    {
        return $this->hasMany(\App\Models\Organization\EmployeeAttendanceRecord::class, 'check_in_location_id');
    }

    public function attendanceCheckOuts(): HasMany
    {
        return $this->hasMany(\App\Models\Organization\EmployeeAttendanceRecord::class, 'check_out_location_id');
    }
}
