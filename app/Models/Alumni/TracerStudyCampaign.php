<?php

namespace App\Models\Alumni;

use App\Models\Academic\AcademicYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TracerStudyCampaign extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'academic_year_id',
        'target_graduation_years',
        'target_study_program_ids',
        'start_date',
        'end_date',
        'questions',
        'status',
        'total_sent',
        'total_responded',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'target_graduation_years' => 'array',
            'target_study_program_ids' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('tracer_study_campaign')
            ->logOnly(['title', 'status', 'start_date', 'end_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function responses(): HasMany
    {
        return $this->hasMany(TracerStudyResponse::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function responseRate(): float
    {
        if ($this->total_sent === 0) {
            return 0;
        }

        return round(($this->total_responded / $this->total_sent) * 100, 1);
    }
}
