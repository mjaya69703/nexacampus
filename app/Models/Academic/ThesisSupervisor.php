<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ThesisSupervisor extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'thesis_id',
        'lecturer_profile_id',
        'role',
        'assigned_at',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('thesis_supervisor')
            ->logOnly(['thesis_id', 'lecturer_profile_id', 'role', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class);
    }

    public function lecturerProfile(): BelongsTo
    {
        return $this->belongsTo(LecturerProfile::class);
    }

    public function roleLabel(): string
    {
        return Thesis::SUPERVISOR_ROLE_LABELS[$this->role] ?? ucfirst((string) $this->role);
    }
}
