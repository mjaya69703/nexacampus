<?php

namespace App\Models\Campus;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RoomReservation extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('room_reservation')
            ->logOnly(['reservation_number', 'title', 'status', 'reservation_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'reservation_number',
        'room_id',
        'user_id',
        'title',
        'purpose',
        'reservation_date',
        'start_time',
        'end_time',
        'attendee_count',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'approved_at' => 'datetime',
            'attendee_count' => 'integer',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function getConflict(?int $roomId, ?string $date, ?string $startTime, ?string $endTime, ?int $ignoreId = null): ?string
    {
        if (! $roomId || ! $date || ! $startTime || ! $endTime) {
            return null;
        }

        $start = strlen($startTime) === 5 ? $startTime.':00' : $startTime;
        $end = strlen($endTime) === 5 ? $endTime.':00' : $endTime;

        if ($start >= $end) {
            return null;
        }

        // 1. Check overlapping approved room reservations
        $existingReservation = static::query()
            ->where('room_id', $roomId)
            ->where('reservation_date', $date)
            ->where('status', 'approved')
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);
            })
            ->first();

        if ($existingReservation) {
            $existingStart = substr($existingReservation->start_time, 0, 5);
            $existingEnd = substr($existingReservation->end_time, 0, 5);

            return "Bentrok dengan reservasi yang disetujui: '{$existingReservation->title}' ({$existingStart} - {$existingEnd} WIB).";
        }

        // 2. Check overlapping course schedules
        $dayName = \Carbon\Carbon::parse($date)->format('l');
        $existingSchedule = \App\Models\Academic\CourseSchedule::query()
            ->where('room_id', $roomId)
            ->where('day_of_week', $dayName)
            ->where('is_active', true)
            ->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);
            })
            ->first();

        if ($existingSchedule) {
            $schStart = substr($existingSchedule->start_time, 0, 5);
            $schEnd = substr($existingSchedule->end_time, 0, 5);

            return "Bentrok dengan Jadwal Kuliah rutin pada hari {$dayName} ({$schStart} - {$schEnd} WIB).";
        }

        return null;
    }
}
