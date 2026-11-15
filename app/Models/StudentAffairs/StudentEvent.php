<?php

namespace App\Models\StudentAffairs;

use App\Enums\StudentEventCategory;
use App\Enums\StudentEventStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentEvent extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'category',
        'description',
        'student_organization_id',
        'organizer',
        'location',
        'starts_at',
        'ends_at',
        'registration_deadline',
        'quota',
        'points',
        'status',
        'is_featured',
        'cover_path',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => StudentEventCategory::class,
            'status' => StudentEventStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_deadline' => 'datetime',
            'quota' => 'integer',
            'points' => 'integer',
            'is_featured' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('student_event')
            ->logOnly(['title', 'category', 'starts_at', 'status', 'quota', 'points', 'is_featured'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(StudentOrganization::class, 'student_organization_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(StudentEventRegistration::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleToStudents($query)
    {
        return $query->whereIn('status', [
            StudentEventStatus::PUBLISHED->value,
            StudentEventStatus::ONGOING->value,
        ]);
    }

    public function isRegistrationOpen(): bool
    {
        if ($this->status !== StudentEventStatus::PUBLISHED) {
            return false;
        }

        if ($this->registration_deadline && $this->registration_deadline->isPast()) {
            return false;
        }

        if ($this->quota !== null && $this->registrations()->where('status', '!=', 'cancelled')->count() >= $this->quota) {
            return false;
        }

        return true;
    }
}
