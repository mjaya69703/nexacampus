<?php

namespace App\Models\Academic;

use App\Enums\ThesisMilestoneStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ThesisMilestone extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'thesis_id',
        'title',
        'description',
        'sequence',
        'status',
        'due_date',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'feedback',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_date' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('thesis_milestone')
            ->logOnly(['thesis_id', 'title', 'sequence', 'status', 'due_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusEnum(): ThesisMilestoneStatus
    {
        return ThesisMilestoneStatus::tryFrom((string) $this->status) ?? ThesisMilestoneStatus::PENDING;
    }

    public function statusLabel(): string
    {
        return $this->statusEnum()->label();
    }

    public function statusTone(): string
    {
        return $this->statusEnum()->tone();
    }
}
