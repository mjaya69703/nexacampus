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

    public function currentApproverLabel(): ?string
    {
        $step = $this->relationLoaded('steps')
            ? $this->steps->firstWhere('status', 'current')
            : $this->currentStep();

        if (! $step) {
            return null;
        }

        $step->loadMissing(['approverUser', 'organizationalPosition', 'workUnit']);

        return match ($step->approver_type) {
            'user' => $step->approverUser?->name,
            'position' => $step->organizationalPosition?->name,
            'work_unit' => $step->workUnit?->name,
            'role' => filled($step->approver_role)
                ? str($step->approver_role)->replace(['-', '_'], ' ')->title()->toString()
                : null,
            'permission' => $this->stepAudienceLabel($step->name),
            default => $this->stepAudienceLabel($step->name),
        };
    }

    public function waitingMessage(): string
    {
        $label = $this->currentApproverLabel();

        return $label
            ? 'Menunggu persetujuan '.$label.'.'
            : 'Menunggu proses persetujuan berikutnya.';
    }

    private function stepAudienceLabel(string $name): string
    {
        $label = preg_replace('/^(review|approval|persetujuan|finalisasi)\s+/i', '', trim($name));

        return filled($label) ? $label : trim($name);
    }
}
