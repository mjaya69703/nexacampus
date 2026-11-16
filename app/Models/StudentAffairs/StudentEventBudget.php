<?php

namespace App\Models\StudentAffairs;

use App\Enums\EventBudgetCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentEventBudget extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'student_event_id',
        'item_name',
        'category',
        'planned_amount',
        'realized_amount',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => EventBudgetCategory::class,
            'planned_amount' => 'decimal:2',
            'realized_amount' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('student_event_budget')
            ->logOnly(['student_event_id', 'item_name', 'category', 'planned_amount', 'realized_amount'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(StudentEvent::class, 'student_event_id');
    }

    public function variance(): float
    {
        return (float) $this->planned_amount - (float) $this->realized_amount;
    }
}
