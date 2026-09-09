<?php

namespace App\Http\Controllers\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\AttendanceRecord;
use App\Models\Academic\AttendanceSession;
use App\Models\Academic\StudyPlanDetail;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sesi absensi — detail + koreksi manual + roster (paritas show Blade).
 * Buka/tutup QR tetap milik portal dosen; admin mengelola data sesi.
 */
class AttendanceSessionController extends Controller
{
    public const STATUSES = ['Present', 'Absent', 'Excused', 'Sick', 'Late'];

    public function show(Request $request, int $offeringId, int $id): Response
    {
        $session = AttendanceSession::query()
            ->where('course_offering_id', $offeringId)
            ->with([
                'courseOffering.course:id,code,name',
                'lecturerProfile.user:id,first_name,last_name',
                'records.studentProfile.user:id,first_name,last_name',
                'records.studentProfile:id,nim,user_id',
            ])
            ->withCount('records')
            ->findOrFail($id);

        $roster = $this->roster($offeringId);
        $recordsByStudent = $session->records->keyBy('student_profile_id');

        return Inertia::render('Admin/Academic/AttendanceSession/Show', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Detail Sesi Absensi'),
            'session' => [
                'id' => $session->id,
                'offeringId' => $offeringId,
                'meetingNo' => $session->meeting_no,
                'date' => $session->meeting_date?->format('Y-m-d'),
                'dateLabel' => $session->meeting_date?->format('d M Y'),
                'startTime' => $session->start_time?->format('H:i') ?? '',
                'endTime' => $session->end_time?->format('H:i') ?? '',
                'lecturerId' => $session->lecturer_profile_id,
                'lecturer' => $session->lecturerProfile
                    ? trim(($session->lecturerProfile->user?->first_name ?? '').' '.($session->lecturerProfile->user?->last_name ?? ''))
                    : '-',
                'status' => $session->status,
                'topic' => $session->topic ?? '',
                'notes' => $session->notes ?? '',
            ],
            'statuses' => ['Draft', 'Opened', 'Closed', 'Cancelled'],
            'recordStatuses' => self::STATUSES,
            'lecturers' => $session->courseOffering->lecturers()
                ->with('lecturerProfile.user:id,first_name,last_name')
                ->orderBy('sort_order')->get()
                ->map(fn ($l) => [
                    'id' => $l->lecturer_profile_id,
                    'label' => trim(($l->lecturerProfile?->user?->name ?? '-')." ({$l->role})"),
                ])->all(),
            'roster' => $roster->map(fn ($student) => [
                'studentId' => $student['id'],
                'nim' => $student['nim'],
                'name' => $student['name'],
                'status' => $recordsByStudent->get($student['id'])?->status,
            ])->all(),
            'summary' => [
                'students' => $roster->count(),
                'recorded' => (int) $session->records_count,
            ],
            'urls' => [
                'workspace' => route('admin.academic.course-offerings.show', $offeringId),
                'submit' => route('admin.academic.attendance-sessions.update', ['offeringId' => $offeringId, 'id' => $session->id]),
                'saveRecords' => route('admin.academic.attendance-sessions.save-records', ['offeringId' => $offeringId, 'id' => $session->id]),
            ],
        ]);
    }

    public function update(Request $request, int $offeringId, int $id)
    {
        $validated = $request->validate([
            'meeting_no' => 'nullable|integer|min:1|max:64',
            'meeting_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'lecturer_profile_id' => 'nullable|integer|exists:lecturer_profiles,id',
            'status' => 'required|in:Draft,Opened,Closed,Cancelled',
            'topic' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $session = AttendanceSession::where('course_offering_id', $offeringId)->findOrFail($id);
        $session->update(array_merge($validated, ['updated_by' => $request->user()->id]));

        return back()->with('success', 'Sesi pertemuan berhasil diperbarui.');
    }

    public function saveRecords(Request $request, int $offeringId, int $id)
    {
        $validated = $request->validate([
            'records' => 'required|array',
            'records.*.student_profile_id' => 'required|integer|exists:student_profiles,id',
            'records.*.status' => 'required|in:'.implode(',', self::STATUSES),
        ]);

        $session = AttendanceSession::where('course_offering_id', $offeringId)->findOrFail($id);

        foreach ($validated['records'] as $row) {
            AttendanceRecord::firstOrNew([
                'attendance_session_id' => $session->id,
                'student_profile_id' => $row['student_profile_id'],
            ])->fill([
                'status' => $row['status'],
                'recorded_at' => now(),
                'recorded_by' => $request->user()->id,
            ])->save();
        }

        return back()->with('success', count($validated['records']).' catatan absensi tersimpan.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, nim: ?string, name: string}>
     */
    private function roster(int $offeringId)
    {
        return StudyPlanDetail::query()
            ->where('course_offering_id', $offeringId)
            ->where('status', 'Taken')
            ->whereHas('studyPlan', fn ($q) => $q->where('status', 'Approved'))
            ->with(['studyPlan.studentProfile.user:id,first_name,last_name', 'studyPlan.studentProfile:id,nim,user_id'])
            ->get()
            ->map(fn ($detail) => $detail->studyPlan?->studentProfile)
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn ($student) => [
                'id' => $student->id,
                'nim' => $student->nim,
                'name' => $student->user?->name ?? '-',
            ]);
    }
}
