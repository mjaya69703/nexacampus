<?php

namespace App\Models\Organization;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ApprovalRequest extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'approval_template_id',
        'approvable_type',
        'approvable_id',
        'requester_user_id',
        'subject',
        'reference',
        'status',
        'current_step_order',
        'submitted_at',
        'completed_at',
        'payload',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'payload' => 'array',
            'current_step_order' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('approval_request')
            ->logOnly(['approval_template_id', 'requester_user_id', 'subject', 'reference', 'status', 'current_step_order'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ApprovalTemplate::class, 'approval_template_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('step_order');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class)->latest();
    }

    public function currentStep()
    {
        return $this->steps()
            ->where('status', 'current')
            ->orderBy('step_order')
            ->first();
    }
}
