<?php

namespace App\Console\Commands;

use App\Mail\Academic\AssignmentDueReminderMail;
use App\Models\Academic\Assignment;
use App\Models\Academic\AssignmentStatusHistory;
use App\Models\Academic\StudyPlanDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAssignmentDueReminders extends Command
{
    protected $signature = 'academic:send-assignment-reminders {--hours=24 : Deadline window in hours} {--dry-run : Count reminders without sending email}';

    protected $description = 'Send due-date reminders for published assignments to students who have not submitted yet.';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;
        $skipped = 0;

        Assignment::query()
            ->published()
            ->with(['courseOffering.course'])
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now(), now()->addHours($hours)])
            ->chunkById(50, function ($assignments) use (&$sent, &$skipped, $dryRun): void {
                foreach ($assignments as $assignment) {
                    $students = StudyPlanDetail::query()
                        ->where('course_offering_id', $assignment->course_offering_id)
                        ->whereHas('studyPlan.studentProfile.user')
                        ->with(['studyPlan.studentProfile.user'])
                        ->get()
                        ->map(fn (StudyPlanDetail $detail) => $detail->studyPlan?->studentProfile)
                        ->filter()
                        ->unique('id')
                        ->values();

                    foreach ($students as $student) {
                        $user = $student->user;

                        if (! $user?->email || $assignment->submissions()->where('student_profile_id', $student->id)->exists()) {
                            $skipped++;

                            continue;
                        }

                        $alreadySent = AssignmentStatusHistory::query()
                            ->where('assignment_id', $assignment->id)
                            ->where('status', 'due_reminder_sent')
                            ->where('meta->student_profile_id', $student->id)
                            ->exists();

                        if ($alreadySent) {
                            $skipped++;

                            continue;
                        }

                        if (! $dryRun) {
                            Mail::to($user->email)->send(new AssignmentDueReminderMail(
                                $assignment,
                                $student,
                                route('student.assignments.show', ['id' => $assignment->id]),
                            ));

                            AssignmentStatusHistory::query()->create([
                                'assignment_id' => $assignment->id,
                                'actor_id' => null,
                                'status' => 'due_reminder_sent',
                                'note' => 'Assignment due reminder email sent.',
                                'meta' => [
                                    'student_profile_id' => $student->id,
                                    'email' => $user->email,
                                    'due_at' => $assignment->due_at?->toDateTimeString(),
                                ],
                            ]);
                        }

                        $sent++;
                    }
                }
            });

        $mode = $dryRun ? 'Would send' : 'Sent';
        $this->info("{$mode} {$sent} assignment reminders. Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
