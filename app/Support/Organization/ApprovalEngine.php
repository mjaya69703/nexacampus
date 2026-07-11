<?php

namespace App\Support\Organization;

use App\Models\Organization\ApprovalAction;
use App\Models\Organization\ApprovalRequest;
use App\Models\Organization\ApprovalStep;
use App\Models\Organization\ApprovalTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalEngine
{
    public function submitFromTemplate(
        ApprovalTemplate|string $template,
        string $subject,
        ?User $requester = null,
        ?Model $approvable = null,
        array $payload = [],
        ?string $reference = null,
        ?string $notes = null,
        ?int $createdBy = null,
    ): ApprovalRequest {
        $template = is_string($template)
            ? ApprovalTemplate::query()->where('code', $template)->firstOrFail()
            : $template;

        if (! $template->is_active) {
            throw ValidationException::withMessages([
                'template' => 'Template approval tidak aktif.',
            ]);
        }

        $template->load('steps');

        if ($template->steps->isEmpty()) {
            throw ValidationException::withMessages([
                'steps' => 'Template approval harus memiliki minimal satu step.',
            ]);
        }

        return DB::transaction(function () use ($template, $subject, $requester, $approvable, $payload, $reference, $notes, $createdBy) {
            $firstStepOrder = (int) $template->steps->min('step_order');

            $request = ApprovalRequest::create([
                'approval_template_id' => $template->id,
                'approvable_type' => $approvable?->getMorphClass(),
                'approvable_id' => $approvable?->getKey(),
                'requester_user_id' => $requester?->id,
                'subject' => $subject,
                'reference' => $reference,
                'status' => 'in_progress',
                'current_step_order' => $firstStepOrder,
                'submitted_at' => now(),
                'payload' => $payload ?: null,
                'notes' => $notes,
                'created_by' => $createdBy,
            ]);

            foreach ($template->steps as $step) {
                ApprovalStep::create([
                    'approval_request_id' => $request->id,
                    'approval_template_step_id' => $step->id,
                    'step_order' => $step->step_order,
                    'name' => $step->name,
                    'approver_type' => $step->approver_type,
                    'approver_user_id' => $step->approver_user_id,
                    'approver_role' => $step->approver_role,
                    'approver_permission' => $step->approver_permission,
                    'organizational_position_id' => $step->organizational_position_id,
                    'work_unit_id' => $step->work_unit_id,
                    'status' => (int) $step->step_order === $firstStepOrder ? 'current' : 'pending',
                    'is_required' => $step->is_required,
                    'can_reject' => $step->can_reject,
                    'due_at' => $step->sla_hours ? now()->addHours($step->sla_hours) : null,
                ]);
            }

            $this->recordAction($request, null, $createdBy, 'submitted', null, 'in_progress', $notes);

            return $request->fresh(['template', 'steps', 'actions']);
        });
    }

    public function approve(ApprovalRequest $request, User $actor, ?string $notes = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $notes) {
            $request = ApprovalRequest::query()->lockForUpdate()->with('steps')->findOrFail($request->id);
            $step = $request->steps->firstWhere('status', 'current');

            if (! $step) {
                throw ValidationException::withMessages(['approval' => 'Tidak ada step approval yang sedang berjalan.']);
            }

            if (! $this->canUserActOnStep($actor, $step)) {
                throw ValidationException::withMessages(['approval' => 'User ini tidak berwenang memproses step approval saat ini.']);
            }

            $approvable = $request->approvable;

            if ($approvable && method_exists($approvable, 'approvalReadinessError')) {
                $readinessError = $approvable->approvalReadinessError();

                if ($readinessError) {
                    throw ValidationException::withMessages(['approval' => $readinessError]);
                }
            }

            $fromStatus = $request->status;

            $step->update([
                'status' => 'approved',
                'acted_by' => $actor->id,
                'acted_at' => now(),
                'notes' => $notes,
            ]);

            $nextStep = $request->steps()
                ->where('status', 'pending')
                ->orderBy('step_order')
                ->first();

            if ($nextStep) {
                $nextStep->update(['status' => 'current']);
                $request->update([
                    'status' => 'in_progress',
                    'current_step_order' => $nextStep->step_order,
                    'updated_by' => $actor->id,
                ]);
                $toStatus = 'in_progress';
            } else {
                $request->update([
                    'status' => 'approved',
                    'current_step_order' => null,
                    'completed_at' => now(),
                    'updated_by' => $actor->id,
                ]);
                $toStatus = 'approved';
                $this->notifyApprovable($request->fresh('approvable'), 'approved', $actor->id, $notes);
            }

            $this->recordAction($request, $step, $actor->id, 'approved', $fromStatus, $toStatus, $notes);

            return $request->fresh(['template', 'steps', 'actions']);
        });
    }

    public function reject(ApprovalRequest $request, User $actor, ?string $notes = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $notes) {
            $request = ApprovalRequest::query()->lockForUpdate()->with('steps')->findOrFail($request->id);
            $step = $request->steps->firstWhere('status', 'current');

            if (! $step) {
                throw ValidationException::withMessages(['approval' => 'Tidak ada step approval yang sedang berjalan.']);
            }

            if (! $step->can_reject) {
                throw ValidationException::withMessages(['approval' => 'Step approval ini tidak mengizinkan reject.']);
            }

            if (! $this->canUserActOnStep($actor, $step)) {
                throw ValidationException::withMessages(['approval' => 'User ini tidak berwenang memproses step approval saat ini.']);
            }

            $fromStatus = $request->status;

            $step->update([
                'status' => 'rejected',
                'acted_by' => $actor->id,
                'acted_at' => now(),
                'notes' => $notes,
            ]);

            $request->steps()
                ->where('status', 'pending')
                ->update(['status' => 'skipped']);

            $request->update([
                'status' => 'rejected',
                'current_step_order' => null,
                'completed_at' => now(),
                'updated_by' => $actor->id,
            ]);
            $this->notifyApprovable($request->fresh('approvable'), 'rejected', $actor->id, $notes);

            $this->recordAction($request, $step, $actor->id, 'rejected', $fromStatus, 'rejected', $notes);

            return $request->fresh(['template', 'steps', 'actions']);
        });
    }

    public function cancel(ApprovalRequest $request, ?User $actor = null, ?string $notes = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $notes) {
            $request = ApprovalRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (in_array($request->status, ['approved', 'rejected', 'cancelled'], true)) {
                throw ValidationException::withMessages(['approval' => 'Request approval sudah selesai.']);
            }

            $fromStatus = $request->status;

            $request->steps()->whereIn('status', ['pending', 'current'])->update(['status' => 'skipped']);
            $request->update([
                'status' => 'cancelled',
                'current_step_order' => null,
                'completed_at' => now(),
                'updated_by' => $actor?->id,
            ]);
            $this->notifyApprovable($request->fresh('approvable'), 'cancelled', $actor?->id, $notes);

            $this->recordAction($request, null, $actor?->id, 'cancelled', $fromStatus, 'cancelled', $notes);

            return $request->fresh(['template', 'steps', 'actions']);
        });
    }

    public function canUserActOnStep(User $user, ApprovalStep $step): bool
    {
        return match ($step->approver_type) {
            'user' => (int) $step->approver_user_id === (int) $user->id,
            'role' => filled($step->approver_role) && $user->hasRole($step->approver_role),
            'permission' => filled($step->approver_permission) && $user->hasPermissionTo($step->approver_permission),
            'position' => $this->userMatchesPosition($user, $step),
            'work_unit' => filled($step->work_unit_id) && $user->workUnits()->where('work_units.id', $step->work_unit_id)->exists(),
            default => false,
        };
    }

    private function userMatchesPosition(User $user, ApprovalStep $step): bool
    {
        if (! $user->employeeProfile || ! $step->organizational_position_id) {
            return false;
        }

        $query = $user->employeeProfile
            ->activePositionAssignments()
            ->where('organizational_position_id', $step->organizational_position_id)
            ->when($step->work_unit_id, fn ($query) => $query->where('work_unit_id', $step->work_unit_id));

        $approvable = $step->request?->approvable;
        $profile = $approvable?->lecturerProfile;

        if ($profile) {
            $query->where(function ($nested) use ($profile) {
                $nested
                    ->where(function ($scope) use ($profile) {
                        $scope->whereNotNull('study_program_id')
                            ->where('study_program_id', $profile->study_program_id);
                    })
                    ->orWhere(function ($scope) use ($profile) {
                        $scope->whereNotNull('faculty_id')
                            ->where('faculty_id', $profile->faculty_id);
                    })
                    ->orWhere(function ($scope) {
                        $scope->whereNull('study_program_id')->whereNull('faculty_id');
                    });
            });
        }

        return $query->exists();
    }

    private function recordAction(
        ApprovalRequest $request,
        ?ApprovalStep $step,
        ?int $userId,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $notes = null,
    ): void {
        ApprovalAction::create([
            'approval_request_id' => $request->id,
            'approval_step_id' => $step?->id,
            'user_id' => $userId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'notes' => $notes,
        ]);
    }

    private function notifyApprovable(ApprovalRequest $request, string $status, ?int $userId, ?string $notes = null): void
    {
        $approvable = $request->approvable;

        if (! $approvable) {
            return;
        }

        $method = match ($status) {
            'approved' => 'markApprovalApproved',
            'rejected' => 'markApprovalRejected',
            'cancelled' => 'markApprovalCancelled',
            default => null,
        };

        if ($method && method_exists($approvable, $method)) {
            $approvable->{$method}($userId, $notes);
        }
    }
}
