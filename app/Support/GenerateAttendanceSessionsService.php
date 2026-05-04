<?php

namespace App\Support;

use App\Models\Academic\AttendanceSession;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateAttendanceSessionsService
{
    /**
     * Generate attendance sessions based on schedule pattern and total meetings.
     *
     * @return int Number of sessions generated
     */
    public function generate(CourseOffering $courseOffering): int
    {
        if (! $courseOffering->total_meetings || ! $courseOffering->class_start_date || ! $courseOffering->class_end_date) {
            throw new \InvalidArgumentException('Course offering must have total_meetings, class_start_date, and class_end_date set.');
        }

        return DB::transaction(function () use ($courseOffering) {
            AttendanceSession::where('course_offering_id', $courseOffering->id)
                ->forceDelete();

            $schedules = CourseSchedule::query()
                ->where('course_offering_id', $courseOffering->id)
                ->where('is_active', true)
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get();

            if ($schedules->isEmpty()) {
                throw new \InvalidArgumentException('Course offering has no schedules configured.');
            }

            $schedulesGroupedByDay = $schedules->groupBy('day_of_week');

            $currentDate = Carbon::parse($courseOffering->class_start_date);
            $endDate = Carbon::parse($courseOffering->class_end_date);
            $meetingNo = 1;
            $sessionsCreated = 0;

            while ($meetingNo <= $courseOffering->total_meetings && $currentDate->lte($endDate)) {
                $dayOfWeek = $currentDate->englishDayOfWeek;

                if ($schedulesGroupedByDay->has($dayOfWeek)) {
                    foreach ($schedulesGroupedByDay[$dayOfWeek] as $schedule) {
                        if ($meetingNo > $courseOffering->total_meetings) {
                            break;
                        }

                        AttendanceSession::create([
                            'course_offering_id' => $courseOffering->id,
                            'course_schedule_id' => null,
                            'lecturer_profile_id' => $schedule->lecturer_profile_id,
                            'meeting_no' => $meetingNo,
                            'meeting_date' => $currentDate,
                            'start_time' => $schedule->start_time?->format('H:i:s'),
                            'end_time' => $schedule->end_time?->format('H:i:s'),
                            'topic' => $schedule->session_type,
                            'notes' => $schedule->notes,
                            'status' => 'Draft',
                            'created_by' => auth()->id(),
                        ]);

                        $meetingNo++;
                        $sessionsCreated++;
                    }
                }

                $currentDate->addDay();
            }

            if ($sessionsCreated < $courseOffering->total_meetings) {
                throw new \InvalidArgumentException('Tanggal akhir kelas tidak cukup untuk menghasilkan total pertemuan yang diminta.');
            }

            return $sessionsCreated;
        });
    }
}
