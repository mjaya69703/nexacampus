<?php

namespace App\Models\Organization;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ApprovalTemplateStep extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'approval_template_id',
        'step_order',
        'name',
        'approver_type',
        'approver_user_id',
        'approver_role',
        'approver_permission',
        'organizational_position_id',
        'work_unit_id',
        'is_required',
        'can_reject',
        'sla_hours',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'can_reject' => 'boolean',
            'sla_hours' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('approval_template_step')
            ->logOnly(['approval_template_id', 'step_order', 'name', 'approver_type', 'is_required', 'can_reject'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ApprovalTemplate::class, 'approval_template_id');
    }

    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function organizationalPosition(): BelongsTo
    {
        return $this->belongsTo(OrganizationalPosition::class);
    }

    public function workUnit(): BelongsTo
    {
        return $this->belongsTo(WorkUnit::class);
    }
}
