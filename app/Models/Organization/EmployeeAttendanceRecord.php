<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EmployeeAttendanceRecord extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'employee_profile_id',
        'work_unit_id',
        'employee_attendance_source_id',
        'sourceable_type',
        'sourceable_id',
        'attendance_date',
        'status',
        'check_in_at',
        'check_out_at',
        'work_minutes',
        'check_in_location_id',
        'check_out_location_id',
        'check_in_latitude',
        'check_in_longitude',
        'check_out_latitude',
        'check_out_longitude',
        'check_in_accuracy_meters',
        'check_out_accuracy_meters',
        'check_in_distance_meters',
        'check_out_distance_meters',
        'location_status',
        'check_in_photo_path',
        'check_out_photo_path',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'work_minutes' => 'integer',
            'check_in_latitude' => 'decimal:7',
            'check_in_longitude' => 'decimal:7',
            'check_out_latitude' => 'decimal:7',
            'check_out_longitude' => 'decimal:7',
            'check_in_accuracy_meters' => 'integer',
            'check_out_accuracy_meters' => 'integer',
            'check_in_distance_meters' => 'integer',
            'check_out_distance_meters' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('employee_attendance_record')
            ->logOnly(['employee_profile_id', 'attendance_date', 'status', 'check_in_at', 'check_out_at', 'work_minutes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function workUnit(): BelongsTo
    {
        return $this->belongsTo(WorkUnit::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(EmployeeAttendanceSource::class, 'employee_attendance_source_id');
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function checkInLocation(): BelongsTo
    {
        return $this->belongsTo(EmployeeAttendanceLocation::class, 'check_in_location_id');
    }

    public function checkOutLocation(): BelongsTo
    {
        return $this->belongsTo(EmployeeAttendanceLocation::class, 'check_out_location_id');
    }

    public function getCheckInPhotoUrlAttribute(): ?string
    {
        return $this->photoUrl($this->check_in_photo_path);
    }

    public function getCheckOutPhotoUrlAttribute(): ?string
    {
        return $this->photoUrl($this->check_out_photo_path);
    }

    private function photoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str($path)->startsWith(['http://', 'https://'])) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
