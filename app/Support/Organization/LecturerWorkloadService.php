<?php

namespace App\Support\Organization;

use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\LecturerWorkloadPeriod;
use App\Models\Organization\LecturerWorkloadRule;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Models\Organization\TridharmaRecord;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LecturerWorkloadService
{
    public function __construct(private readonly ApprovalEngine $approvalEngine) {}

    public function generateFor(User $user, LecturerWorkloadPeriod $period): LecturerWorkloadSubmission
    {
        if (! $period->isOpen()) {
            throw ValidationException::withMessages(['period' => 'Periode BKD belum dibuka.']);
        }

        if (! $user->lecturerProfile) {
            throw ValidationException::withMessages(['lecturer' => 'Profil dosen belum tersedia.']);
        }

        return DB::transaction(function () use ($user, $period) {
            $submission = LecturerWorkloadSubmission::query()->firstOrCreate([
                'lecturer_workload_period_id' => $period->id,
                'user_id' => $user->id,
            ], [
                'lecturer_profile_id' => $user->lecturerProfile?->id,
                'employee_profile_id' => $user->employeeProfile?->id,
                'status' => 'draft',
                'created_by' => $user->id,
            ]);

            if (! in_array($submission->status, ['draft', 'revision', 'rejected', 'cancelled'], true)) {
                throw ValidationException::withMessages(['submission' => 'BKD yang sudah diajukan tidak bisa digenerate ulang.']);
            }

            $items = $this->calculatedItems($user, $period);
            $totals = $items->groupBy('category')->map(fn (Collection $rows) => round((float) $rows->sum('sks'), 2));

            $submission->update([
                'lecturer_profile_id' => $user->lecturerProfile?->id,
                'employee_profile_id' => $user->employeeProfile?->id,
                'status' => 'draft',
                'teaching_sks' => $totals->get('teaching', 0),
                'structural_sks' => $totals->get('structural', 0),
                'tridharma_sks' => $totals->get('tridharma', 0),
                'total_sks' => round((float) $items->sum('sks'), 2),
                'generated_at' => now(),
                'submitted_at' => null,
                'approved_at' => null,
                'approved_by' => null,
                'rejected_at' => null,
                'rejected_by' => null,
                'approval_request_id' => null,
                'updated_by' => $user->id,
            ]);

            $submission->items()->delete();

            foreach ($items as $item) {
                $source = $item['sourceable'] ?? null;

                $submission->items()->create([
                    'sourceable_type' => $source?->getMorphClass(),
                    'sourceable_id' => $source?->getKey(),
                    'category' => $item['category'],
                    'source_code' => $item['source_code'],
                    'title' => $item['title'],
                    'sks' => $item['sks'],
                    'snapshot' => $item['snapshot'],
                ]);
            }

            return $submission->fresh(['period', 'items.sourceable', 'approvalRequest']);
        });
    }

    public function submit(LecturerWorkloadSubmission $submission, User $user, ?string $notes = null): LecturerWorkloadSubmission
    {
        if ((int) $submission->user_id !== (int) $user->id) {
            throw ValidationException::withMessages(['submission' => 'Anda hanya bisa mengajukan BKD milik sendiri.']);
        }

        if (! in_array($submission->status, ['draft', 'revision', 'rejected', 'cancelled'], true)) {
            throw ValidationException::withMessages(['submission' => 'BKD ini sudah berada dalam proses review.']);
        }

        $template = ApprovalTemplate::query()->where('code', 'LECTURER_WORKLOAD_REVIEW')->first();

        if (! $template) {
            throw ValidationException::withMessages(['template' => 'Template approval BKD belum tersedia. Jalankan seeder Kepegawaian terlebih dahulu.']);
        }

        return DB::transaction(function () use ($submission, $user, $notes, $template) {
            $request = $this->approvalEngine->submitFromTemplate(
                template: $template,
                subject: 'Review BKD '.$user->name.' - '.$submission->period?->name,
                requester: $user,
                approvable: $submission,
                payload: [
                    'total_sks' => $submission->total_sks,
                    'teaching_sks' => $submission->teaching_sks,
                    'structural_sks' => $submission->structural_sks,
                    'tridharma_sks' => $submission->tridharma_sks,
                ],
                reference: 'BKD-'.$submission->id,
                notes: $notes,
                createdBy: $user->id,
            );

            $submission->update([
                'approval_request_id' => $request->id,
                'status' => 'in_approval',
                'submitted_at' => now(),
                'lecturer_notes' => $notes,
                'updated_by' => $user->id,
            ]);

            return $submission->fresh(['period', 'items', 'approvalRequest.steps']);
        });
    }

    public function calculatedItems(User $user, LecturerWorkloadPeriod $period): Collection
    {
        return collect()
            ->merge($this->teachingItems($user, $period))
            ->merge($this->structuralItems($user))
            ->merge($this->tridharmaItems($user, $period));
    }

    private function teachingItems(User $user, LecturerWorkloadPeriod $period): Collection
    {
        $lecturerProfileId = $user->lecturerProfile?->id;

        if (! $lecturerProfileId) {
            return collect();
        }

        return CourseOfferingLecturer::query()
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->where('is_active', true)
            ->whereHas('courseOffering', function ($query) use ($period) {
                if ($period->academic_year_id) {
                    $query->where('academic_year_id', $period->academic_year_id);
                }
            })
            ->with(['courseOffering.course', 'courseOffering.academicYear', 'courseOffering.studyProgram'])
            ->get()
            ->map(function (CourseOfferingLecturer $assignment) {
                $offering = $assignment->courseOffering;
                $credits = (float) ($offering?->credits ?: 0);

                return [
                    'category' => 'teaching',
                    'source_code' => 'TEACHING',
                    'title' => trim(($offering?->course?->code ? $offering->course->code.' - ' : '').($offering?->course?->name ?? 'Kelas mengajar')),
                    'sks' => $credits,
                    'sourceable' => $assignment,
                    'snapshot' => [
                        'role' => $assignment->role,
                        'course_offering_id' => $offering?->id,
                        'label' => $offering?->label,
                        'credits' => $credits,
                        'study_program' => $offering?->studyProgram?->name,
                        'academic_year' => $offering?->academicYear?->name,
                    ],
                ];
            });
    }

    private function structuralItems(User $user): Collection
    {
        $profile = $user->employeeProfile;

        if (! $profile) {
            return collect();
        }

        $rules = LecturerWorkloadRule::query()
            ->where('category', 'structural')
            ->where('is_active', true)
            ->get()
            ->keyBy('source_code');

        return $profile->activePositionAssignments()
            ->with(['position', 'faculty', 'studyProgram', 'workUnit'])
            ->get()
            ->map(function ($assignment) use ($rules) {
                $code = $assignment->position?->code ?: 'STRUCTURAL';
                $rule = $rules->get($code);
                $sks = (float) ($rule?->sks_value ?? 1);

                if ($rule?->maximum_sks) {
                    $sks = min($sks, (float) $rule->maximum_sks);
                }

                return [
                    'category' => 'structural',
                    'source_code' => $code,
                    'title' => $assignment->position?->name ?? 'Jabatan struktural',
                    'sks' => $sks,
                    'sourceable' => $assignment,
                    'snapshot' => [
                        'faculty' => $assignment->faculty?->name,
                        'study_program' => $assignment->studyProgram?->name,
                        'work_unit' => $assignment->workUnit?->name,
                        'starts_at' => $assignment->starts_at,
                        'ends_at' => $assignment->ends_at,
                    ],
                ];
            });
    }

    private function tridharmaItems(User $user, LecturerWorkloadPeriod $period): Collection
    {
        $rules = LecturerWorkloadRule::query()
            ->where('category', 'tridharma')
            ->where('is_active', true)
            ->get()
            ->keyBy('source_code');

        return TridharmaRecord::query()
            ->where('user_id', $user->id)
            ->where('is_verified', true)
            ->whereIn('status', ['approved', 'active', 'completed'])
            ->when($period->starts_at, fn ($query) => $query->where(function ($nested) use ($period) {
                $nested->whereNull('ends_at')->orWhereDate('ends_at', '>=', $period->starts_at);
            }))
            ->when($period->ends_at, fn ($query) => $query->where(function ($nested) use ($period) {
                $nested->whereNull('starts_at')->orWhereDate('starts_at', '<=', $period->ends_at);
            }))
            ->get()
            ->map(function (TridharmaRecord $record) use ($rules) {
                $sourceCode = strtoupper($record->type);
                $rule = $rules->get($sourceCode);
                $sks = (float) ($rule?->sks_value ?? match ($record->type) {
                    'research' => 3,
                    'community_service' => 2,
                    'publication' => 2,
                    default => 1,
                });

                if ($rule?->maximum_sks) {
                    $sks = min($sks, (float) $rule->maximum_sks);
                }

                return [
                    'category' => 'tridharma',
                    'source_code' => $sourceCode,
                    'title' => $record->title,
                    'sks' => $sks,
                    'sourceable' => $record,
                    'snapshot' => [
                        'type' => $record->type,
                        'scheme' => $record->scheme,
                        'status' => $record->status,
                        'verified_at' => $record->verified_at?->toDateTimeString(),
                    ],
                ];
            });
    }
}
