<?php

namespace App\Support\Financial;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentProfile;
use App\Models\Financial\InvoiceSchedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceScheduleService
{
    public function runDueSchedules(bool $dryRun = false, ?int $limit = null): array
    {
        $query = InvoiceSchedule::query()
            ->with('academicYear')
            ->where('is_active', true)
            ->where('status', 'pending')
            ->where('publish_at', '<=', now())
            ->orderBy('publish_at');

        if ($limit) {
            $query->limit($limit);
        }

        $summary = ['processed' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0];

        $query->get()->each(function (InvoiceSchedule $schedule) use (&$summary, $dryRun): void {
            $result = $this->run($schedule, $dryRun);

            $summary['processed']++;
            $summary['created'] += $result['created'];
            $summary['skipped'] += $result['skipped'];
            $summary['failed'] += $result['failed'];
        });

        return $summary;
    }

    public function run(InvoiceSchedule $schedule, bool $dryRun = false): array
    {
        if (! $schedule->is_active || $schedule->status !== 'pending') {
            throw new RuntimeException('Schedule tidak aktif atau sudah diproses.');
        }

        $students = $this->studentsForSchedule($schedule);
        $result = [
            'dry_run' => $dryRun,
            'created' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        if ($students->isEmpty()) {
            $result['failed'] = 1;
            $result['errors'][] = 'Tidak ada student profile yang cocok dengan schedule.';

            if (! $dryRun) {
                $schedule->update([
                    'status' => 'failed',
                    'failed_count' => 1,
                    'last_result' => $result,
                    'last_run_at' => now(),
                ]);
            }

            return $result;
        }

        if ($dryRun) {
            $result['created'] = $students->count();

            return $result;
        }

        $schedule->update([
            'status' => 'running',
            'last_run_at' => now(),
        ]);

        foreach ($students as $student) {
            try {
                if ($schedule->invoice_kind === 'tuition') {
                    $this->generateTuition($schedule, $student);
                } else {
                    $this->generateCustom($schedule, $student);
                }

                $result['created']++;
            } catch (\Throwable $exception) {
                $result['skipped']++;
                $result['errors'][] = ($student->nim ?? $student->id).': '.$exception->getMessage();
            }
        }

        $result['errors'] = array_slice($result['errors'], 0, 20);

        $schedule->update([
            'status' => $result['created'] > 0 ? 'completed' : 'failed',
            'created_count' => $result['created'],
            'skipped_count' => $result['skipped'],
            'failed_count' => $result['failed'],
            'last_result' => $result,
            'completed_at' => now(),
        ]);

        return $result;
    }

    public function studentsForSchedule(InvoiceSchedule $schedule): Collection
    {
        if ($schedule->generation_mode === 'single') {
            return StudentProfile::query()
                ->with(['user', 'studyProgram'])
                ->whereKey($schedule->student_profile_id)
                ->get();
        }

        if ($schedule->generation_mode === 'selected_students') {
            return StudentProfile::query()
                ->with(['user', 'studyProgram'])
                ->where('is_active', true)
                ->whereKey($schedule->student_profile_ids ?? [])
                ->get();
        }

        return StudentProfile::query()
            ->with(['user', 'studyProgram'])
            ->where('is_active', true)
            ->when($schedule->semester, fn ($query, $semester) => $query->where('current_semester', (int) $semester))
            ->when($schedule->academic_year_id, function ($query, $academicYearId) use ($schedule) {
                $query->whereHas('registrations', function ($query) use ($academicYearId, $schedule) {
                    $query->where('academic_year_id', $academicYearId)
                        ->when($schedule->semester, fn ($query, $semester) => $query->where('semester_no', $semester))
                        ->where('registration_status', 'Approved')
                        ->where('is_active', true);
                });
            })
            ->get();
    }

    private function generateTuition(InvoiceSchedule $schedule, StudentProfile $student): void
    {
        if (! $schedule->academicYear || ! $schedule->semester) {
            throw new RuntimeException('Tuition schedule wajib punya academic year dan semester.');
        }

        DB::transaction(function () use ($schedule, $student): void {
            app(InvoiceGenerationService::class)->generateForStudent(
                studentProfile: $student,
                academicYear: $schedule->academicYear,
                semester: (int) $schedule->semester,
                dueDate: $schedule->due_date?->toDateString(),
                createdBy: $schedule->created_by,
                issueImmediately: (bool) $schedule->issue_immediately,
                schedule: $schedule,
            );
        });
    }

    private function generateCustom(InvoiceSchedule $schedule, StudentProfile $student): void
    {
        $items = collect($schedule->items ?? [])
            ->filter(fn (array $item) => filled($item['description'] ?? null) && (float) ($item['amount'] ?? 0) > 0)
            ->values()
            ->all();

        if (empty($items)) {
            throw new RuntimeException('Custom schedule wajib punya item invoice.');
        }

        DB::transaction(function () use ($schedule, $student, $items): void {
            app(InvoiceGenerationService::class)->createCustomInvoice(
                studentProfile: $student,
                items: $items,
                invoiceType: $schedule->invoice_type,
                dueDate: $schedule->due_date->toDateString(),
                academicYear: $schedule->academicYear instanceof AcademicYear ? $schedule->academicYear : null,
                semester: $schedule->semester,
                notes: $schedule->notes,
                createdBy: $schedule->created_by,
                issueImmediately: (bool) $schedule->issue_immediately,
                schedule: $schedule,
            );
        });
    }
}
