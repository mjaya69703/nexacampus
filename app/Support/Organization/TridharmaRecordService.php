<?php

namespace App\Support\Organization;

use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\TridharmaRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TridharmaRecordService
{
    public function submitForApproval(TridharmaRecord $record, User $actor): TridharmaRecord
    {
        return DB::transaction(function () use ($record, $actor) {
            $record = TridharmaRecord::query()->lockForUpdate()->findOrFail($record->id);

            if ($record->approval_request_id && in_array($record->status, ['submitted', 'in_approval'], true)) {
                return $record;
            }

            $approval = app(ApprovalEngine::class)->submitFromTemplate(
                'TRIDHARMA_PROPOSAL',
                'Proposal Tridharma: '.$record->title,
                requester: $actor,
                approvable: $record,
                payload: [
                    'type' => $record->type,
                    'funding_amount' => $record->funding_amount,
                    'funding_source' => $record->funding_source,
                ],
                reference: 'TRI-'.$record->id,
                notes: 'Pengajuan proposal Tridharma.',
                createdBy: $actor->id,
            );

            $record->update([
                'approval_request_id' => $approval->id,
                'status' => 'in_approval',
                'submitted_at' => now(),
                'updated_by' => $actor->id,
            ]);

            return $record->fresh(['approvalRequest', 'owner']);
        });
    }

    public function storeAttachment(TridharmaRecord $record, UploadedFile $file, string $documentType, ?User $uploader = null, ?Model $attachable = null): void
    {
        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $mimeType = $file->getMimeType();
        $filename = 'tridharma_'.$record->id.'_'.time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('private/tridharma', $filename);

        $record->attachments()->create([
            'document_type' => $documentType,
            'file_path' => $path,
            'file_name' => $originalName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'uploaded_by' => $uploader?->id,
            'attachable_type' => $attachable?->getMorphClass(),
            'attachable_id' => $attachable?->getKey(),
        ]);
    }

    public function ensureDefaultApprovalTemplate(User $admin): ApprovalTemplate
    {
        $template = ApprovalTemplate::updateOrCreate(
            ['code' => 'TRIDHARMA_PROPOSAL'],
            [
                'name' => 'Approval Proposal Tridharma',
                'module' => 'organization',
                'description' => 'Approval proposal penelitian, pengabdian, dan publikasi Tridharma.',
                'is_active' => true,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        $template->steps()->updateOrCreate(
            ['step_order' => 1],
            [
                'name' => 'Review Kepegawaian/Akademik',
                'approver_type' => 'permission',
                'approver_permission' => 'tridharma-record.approve',
                'is_required' => true,
                'can_reject' => true,
                'sla_hours' => 72,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        return $template;
    }
}
