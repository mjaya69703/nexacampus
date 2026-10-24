<?php

namespace App\Http\Controllers\AcademicLeader;

use App\Http\Controllers\Controller;
use App\Support\Inertia\ShellProps;
use App\Support\Organization\AcademicLeaderOversightService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monitoring kelas dan kehadiran dalam scope pimpinan akademik.
 *
 * Pencarian mencakup nama mata kuliah, kode kelas, program studi, dan
 * seluruh dosen pengampu; filter risiko memvalidasi nilai agar URL
 * manual tidak menghasilkan halaman kosong tanpa penjelasan.
 */
class ClassOversightController extends Controller
{
    public function index(Request $request, AcademicLeaderOversightService $oversight): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'risk' => ['nullable', Rule::in(['all', 'success', 'warning', 'danger'])],
        ]);

        $risk = $validated['risk'] ?? 'all';
        $needle = mb_strtolower(trim((string) ($validated['q'] ?? '')));
        $allRows = $oversight->classRows($request->user());

        $rows = $allRows
            ->filter(function (array $row) use ($needle, $risk): bool {
                $haystack = mb_strtolower(implode(' ', [
                    $row['course'],
                    $row['code'],
                    $row['program'],
                    implode(' ', $row['lecturers']),
                ]));

                return ($needle === '' || str_contains($haystack, $needle))
                    && ($risk === 'all' || $row['risk_level'] === $risk);
            })
            ->values()
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'course' => $row['course'],
                'label' => $row['label'],
                'code' => $row['code'],
                'program' => $row['program'],
                'faculty' => $row['faculty'],
                'academicYear' => $row['academic_year'],
                'status' => $row['status'],
                'lecturers' => $row['lecturers'],
                'lecturerCount' => $row['lecturer_count'],
                'scheduleCount' => $row['schedule_count'],
                'studentCount' => $row['student_count'],
                'targetMeetings' => $row['target_meetings'],
                'sessionCount' => $row['session_count'],
                'openedSessions' => $row['opened_sessions'],
                'closedSessions' => $row['closed_sessions'],
                'cancelledSessions' => $row['cancelled_sessions'],
                'missingMeetings' => $row['missing_meetings'],
                'openedTooLong' => $row['opened_too_long'],
                'attendanceRate' => $row['attendance_rate'],
                'riskLevel' => $row['risk_level'],
                'riskLabel' => $oversight->classRiskLabel($row['risk_level']),
            ])
            ->all();

        return Inertia::render('AcademicLeader/Classes/Index', [
            'shell' => ShellProps::make($request->user(), 'Pemantauan Akademik', 'Kelas dan Kehadiran'),
            'rows' => $rows,
            'filters' => [
                'q' => (string) ($validated['q'] ?? ''),
                'risk' => $risk,
            ],
            'riskOptions' => [
                'all' => 'Semua status',
                'success' => 'Terkendali',
                'warning' => 'Perlu perhatian',
                'danger' => 'Kritis',
            ],
            'stats' => [
                'classes' => $allRows->count(),
                'opened' => $allRows->sum('opened_sessions'),
                'problem' => $allRows->whereIn('risk_level', ['warning', 'danger'])->count(),
                'lowAttendance' => $allRows->filter(fn ($row) => $row['attendance_rate'] !== null && $row['attendance_rate'] < 70)->count(),
            ],
            'urls' => [
                'index' => route('academic-leader.classes.index'),
                'export' => [
                    'csv' => route('academic-leader.reports.export', ['type' => 'classes', 'format' => 'csv']),
                    'xlsx' => route('academic-leader.reports.export', ['type' => 'classes', 'format' => 'xlsx']),
                    'pdf' => route('academic-leader.reports.export', ['type' => 'classes', 'format' => 'pdf']),
                ],
            ],
        ]);
    }
}
