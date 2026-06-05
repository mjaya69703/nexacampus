<?php

namespace App\Support\Organization;

use App\Models\Academic\CourseOffering;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Organization\LecturerPerformanceReview;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AcademicLeaderOversightService
{
    public function __construct(private readonly AcademicLeaderContext $context) {}

    public function lecturerQuery(?User $user = null): Builder
    {
        return $this->context
            ->scopedLecturerProfileQuery($user)
            ->with(['user', 'studyProgram.faculty']);
    }

    public function classQuery(?User $user = null): Builder
    {
        return $this->context
            ->applyCourseOfferingScope(CourseOffering::query(), $user)
            ->with([
                'academicYear',
                'studyProgram.faculty',
                'course',
                'lecturers.lecturerProfile.user',
                'courseSchedules',
                'attendanceSessions.records',
            ]);
    }

    public function lecturerRows(?User $user = null): Collection
    {
        return $this->lecturerQuery($user)
            ->orderBy('study_program_id')
            ->get()
            ->map(function (LecturerProfile $profile) {
                $latestWorkload = LecturerWorkloadSubmission::query()
                    ->with('period')
                    ->where('lecturer_profile_id', $profile->id)
                    ->latest('updated_at')
                    ->first();

                $latestReview = LecturerPerformanceReview::query()
                    ->with('edomPeriod')
                    ->where('lecturer_profile_id', $profile->id)
                    ->latest('calculated_at')
                    ->first();

                $activeClasses = CourseOffering::query()
                    ->whereHas('lecturers', fn (Builder $query) => $query
                        ->where('lecturer_profile_id', $profile->id)
                        ->where('is_active', true))
                    ->whereIn('status', ['Open', 'Published'])
                    ->count();

                return [
                    'id' => $profile->id,
                    'name' => $profile->user?->name ?? '-',
                    'nidn' => $profile->nidn ?? '-',
                    'program' => $profile->studyProgram?->name ?? '-',
                    'faculty' => $profile->studyProgram?->faculty?->name ?? '-',
                    'active_classes' => $activeClasses,
                    'workload_status' => $latestWorkload?->status ?? 'none',
                    'workload_status_label' => $this->workloadStatusLabel($latestWorkload?->status),
                    'workload_sks' => $latestWorkload?->total_sks ?: 0,
                    'workload_period' => $latestWorkload?->period?->name ?? '-',
                    'performance_score' => $latestReview?->final_score ?: null,
                    'edom_score' => $latestReview?->edom_score ?: null,
                    'edom_responses' => $latestReview?->edom_response_count ?: 0,
                    'edom_period' => $latestReview?->edomPeriod?->name ?? '-',
                ];
            });
    }

    public function classRows(?User $user = null): Collection
    {
        return $this->classQuery($user)
            ->latest('id')
            ->get()
            ->map(function (CourseOffering $offering) {
                $studentCount = $this->studentCount($offering->id);
                $sessions = $offering->attendanceSessions;
                $validRecords = $sessions
                    ->flatMap->records
                    ->whereIn('status', ['Present', 'Late', 'Excused', 'Sick'])
                    ->count();
                $possibleRecords = max(1, $studentCount * max(1, $sessions->count()));
                $attendanceRate = $sessions->isEmpty() || $studentCount === 0 ? null : round(($validRecords / $possibleRecords) * 100, 2);
                $openedTooLong = $sessions
                    ->where('status', 'Opened')
                    ->filter(fn ($session) => $session->opened_at && $session->opened_at->lt(now()->subHours(3)))
                    ->count();
                $missingMeetings = max(0, (int) ($offering->total_meetings ?: 0) - $sessions->count());
                $lecturers = $offering->lecturers
                    ->where('is_active', true)
                    ->map(fn ($assignment) => $assignment->lecturerProfile?->user?->name)
                    ->filter()
                    ->values();

                return [
                    'id' => $offering->id,
                    'course' => trim(($offering->course?->code ?? '-') . ' - ' . ($offering->course?->name ?? '-')),
                    'label' => $offering->label ?? '-',
                    'code' => $offering->code ?? '-',
                    'program' => $offering->studyProgram?->name ?? '-',
                    'faculty' => $offering->studyProgram?->faculty?->name ?? '-',
                    'academic_year' => $offering->academicYear?->name ?? '-',
                    'status' => $offering->status ?? '-',
                    'lecturers' => $lecturers->all(),
                    'lecturer_count' => $lecturers->count(),
                    'schedule_count' => $offering->courseSchedules->where('is_active', true)->count(),
                    'student_count' => $studentCount,
                    'target_meetings' => (int) ($offering->total_meetings ?: 0),
                    'session_count' => $sessions->count(),
                    'opened_sessions' => $sessions->where('status', 'Opened')->count(),
                    'closed_sessions' => $sessions->where('status', 'Closed')->count(),
                    'cancelled_sessions' => $sessions->where('status', 'Cancelled')->count(),
                    'missing_meetings' => $missingMeetings,
                    'opened_too_long' => $openedTooLong,
                    'attendance_rate' => $attendanceRate,
                    'risk_level' => $this->classRiskLevel($lecturers->count(), $offering->courseSchedules->where('is_active', true)->count(), $missingMeetings, $openedTooLong, $attendanceRate),
                ];
            });
    }

    public function alerts(?User $user = null): Collection
    {
        $classRows = $this->classRows($user);
        $lecturerRows = $this->lecturerRows($user);
        $alerts = collect();

        foreach ($classRows as $row) {
            if ($row['lecturer_count'] === 0) {
                $alerts->push($this->alert('danger', 'Kelas tanpa dosen', $row['course'].' '.$row['label'], 'classes'));
            }
            if ($row['schedule_count'] === 0) {
                $alerts->push($this->alert('warning', 'Kelas tanpa jadwal aktif', $row['course'].' '.$row['label'], 'classes'));
            }
            if ($row['opened_too_long'] > 0) {
                $alerts->push($this->alert('warning', 'Sesi belum ditutup', $row['course'].' '.$row['label'].' memiliki '.$row['opened_too_long'].' sesi berjalan lama', 'classes'));
            }
            if ($row['missing_meetings'] > 0) {
                $alerts->push($this->alert('info', 'Pertemuan belum lengkap', $row['course'].' kurang '.$row['missing_meetings'].' sesi dari target', 'classes'));
            }
            if ($row['attendance_rate'] !== null && $row['attendance_rate'] < 70) {
                $alerts->push($this->alert('danger', 'Kehadiran rendah', $row['course'].' '.$row['attendance_rate'].'%', 'classes'));
            }
        }

        foreach ($lecturerRows as $row) {
            if (in_array($row['workload_status'], ['none', 'draft', 'revision'], true)) {
                $alerts->push($this->alert('warning', 'BKD perlu tindak lanjut', $row['name'].' - '.$row['workload_status_label'], 'lecturers'));
            }
            if ($row['performance_score'] !== null && (float) $row['performance_score'] < 70) {
                $alerts->push($this->alert('danger', 'Performa perlu perhatian', $row['name'].' skor '.number_format((float) $row['performance_score'], 2), 'lecturers'));
            }
        }

        return $alerts->values();
    }

    public function dashboardStats(?User $user = null): array
    {
        $classes = $this->classRows($user);
        $lecturers = $this->lecturerRows($user);
        $alerts = $this->alerts($user);

        return [
            'lecturers' => $lecturers->count(),
            'classes' => $classes->count(),
            'opened_sessions' => $classes->sum('opened_sessions'),
            'problem_classes' => $classes->whereIn('risk_level', ['warning', 'danger'])->count(),
            'low_attendance' => $classes->filter(fn ($row) => $row['attendance_rate'] !== null && $row['attendance_rate'] < 70)->count(),
            'pending_workloads' => $lecturers->whereIn('workload_status', ['none', 'draft', 'revision', 'in_approval'])->count(),
            'avg_performance' => $lecturers->filter(fn ($row) => $row['performance_score'] !== null)->avg('performance_score') ?: 0,
            'alerts' => $alerts->count(),
        ];
    }

    public function studentCount(int $courseOfferingId): int
    {
        return StudyPlanDetail::query()
            ->where('course_offering_id', $courseOfferingId)
            ->whereHas('studyPlan', fn (Builder $query) => $query->where('status', 'Approved'))
            ->with('studyPlan.studentProfile')
            ->get()
            ->pluck('studyPlan.studentProfile.id')
            ->filter()
            ->unique()
            ->count();
    }

    public function workloadStatusLabel(?string $status): string
    {
        return match ($status) {
            'in_approval' => 'Menunggu Approval',
            'approved' => 'Disetujui',
            'revision' => 'Perlu Revisi',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            'draft' => 'Draf',
            default => 'Belum Ada',
        };
    }

    public function classRiskLabel(string $risk): string
    {
        return match ($risk) {
            'danger' => 'Kritis',
            'warning' => 'Perlu Perhatian',
            default => 'Terkendali',
        };
    }

    private function classRiskLevel(int $lecturers, int $schedules, int $missingMeetings, int $openedTooLong, ?float $attendanceRate): string
    {
        if ($lecturers === 0 || ($attendanceRate !== null && $attendanceRate < 70)) {
            return 'danger';
        }

        if ($schedules === 0 || $missingMeetings > 0 || $openedTooLong > 0) {
            return 'warning';
        }

        return 'success';
    }

    private function alert(string $level, string $title, string $description, string $target): array
    {
        return compact('level', 'title', 'description', 'target') + ['created_at' => now()];
    }
}
