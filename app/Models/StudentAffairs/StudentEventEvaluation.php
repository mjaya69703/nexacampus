<?php

namespace App\Models\StudentAffairs;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentEventEvaluation extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'student_event_id',
        'user_id',
        'rating',
        'organization_rating',
        'benefit_rating',
        'feedback',
        'suggestions',
        'is_anonymous',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'organization_rating' => 'integer',
            'benefit_rating' => 'integer',
            'is_anonymous' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('student_event_evaluation')
            ->logOnly(['student_event_id', 'user_id', 'rating', 'organization_rating', 'benefit_rating'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(StudentEvent::class, 'student_event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
