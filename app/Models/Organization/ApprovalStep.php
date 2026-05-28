<?php

namespace App\Models\Organization;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_request_id',
        'approval_template_step_id',
        'step_order',
        'name',
        'approver_type',
        'approver_user_id',
        'approver_role',
        'approver_permission',
        'organizational_position_id',
        'work_unit_id',
        'status',
        'is_required',
        'can_reject',
        'due_at',
        'acted_by',
        'acted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'can_reject' => 'boolean',
            'due_at' => 'datetime',
            'acted_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function templateStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalTemplateStep::class, 'approval_template_step_id');
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

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
