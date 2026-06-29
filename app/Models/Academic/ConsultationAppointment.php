<?php

namespace App\Models\Academic;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ConsultationAppointment extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const ACTIVE_STATUSES = ['requested', 'confirmed'];

    protected $fillable = [
        'consultation_slot_id',
        'lecturer_profile_id',
        'student_profile_id',
        'appointment_date',
        'starts_at',
        'ends_at',
        'status',
        'topic',
        'student_notes',
        'lecturer_notes',
        'cancellation_reason',
        'requested_at',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'requested_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('consultation_appointment')
            ->logOnly(['consultation_slot_id', 'student_profile_id', 'starts_at', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function consultationSlot(): BelongsTo
    {
        return $this->belongsTo(ConsultationSlot::class);
    }

    public function lecturerProfile(): BelongsTo
    {
        return $this->belongsTo(LecturerProfile::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'confirmed' => 'Dikonfirmasi',
            'waitlisted' => 'Waiting list',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            'rejected' => 'Ditolak',
            'no_show' => 'Tidak hadir',
            default => 'Menunggu',
        };
    }
}
