<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EmployeeAttendanceLocation extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'address',
        'latitude',
        'longitude',
        'radius_meters',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_meters' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('employee_attendance_location')
            ->logOnly(['name', 'code', 'latitude', 'longitude', 'radius_meters', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function checkInRecords()
    {
        return $this->hasMany(EmployeeAttendanceRecord::class, 'check_in_location_id');
    }

    public function checkOutRecords()
    {
        return $this->hasMany(EmployeeAttendanceRecord::class, 'check_out_location_id');
    }
}
