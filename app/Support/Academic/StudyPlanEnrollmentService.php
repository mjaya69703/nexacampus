<?php

namespace App\Support\Academic;

use App\Models\Academic\CourseOffering;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\TranscriptEntry;
use Illuminate\Support\Facades\DB;

class StudyPlanEnrollmentService
{
    /**
     * Batas maksimum SKS yang boleh diambil dalam satu semester. Nilai ini
     * dipakai juga oleh halaman KRS mahasiswa agar aturan yang ditampilkan
     * sama dengan aturan yang ditegakkan di server.
     */
    public const MAX_CREDITS = 24;

    /**
     * Add an offering to a student's KRS while enforcing enrollment rules in
     * one transaction. The offering row is locked so the last seat cannot be
     * claimed concurrently by two requests.
     *
     * @return array{success: bool, message: string}
     */
    public function addOffering(
        StudyPlan $studyPlan,
        StudentProfile $student,
        int $offeringId,
        int $registrationSemesterNo,
        ?int $userId = null,
    ): array {
        return DB::transaction(function () use ($studyPlan, $student, $offeringId, $registrationSemesterNo, $userId): array {
            if ((int) $studyPlan->student_profile_id !== (int) $student->id) {
                return ['success' => false, 'message' => 'KRS tidak sesuai dengan mahasiswa yang dipilih.'];
            }

            if (! in_array($studyPlan->status, ['Draft', 'Rejected'], true)) {
                return ['success' => false, 'message' => 'KRS sudah tidak bisa diubah pada status saat ini.'];
            }

            $offering = CourseOffering::query()
                ->with([
                    'course',
                    'courseSchedules' => fn ($query) => $query->where('is_active', true),
                ])
                ->whereKey($offeringId)
                ->lockForUpdate()
                ->first();

            if (! $offering || $offering->academic_year_id !== $studyPlan->academic_year_id || $offering->status !== 'Open') {
                return ['success' => false, 'message' => 'Course offering tidak tersedia atau sudah ditutup.'];
            }

            if ($offering->study_program_id !== $student->study_program_id) {
                return ['success' => false, 'message' => 'Mata kuliah ini bukan untuk program studi Anda.'];
            }

            if (($offering->semester_no ?? 0) > $registrationSemesterNo) {
                return ['success' => false, 'message' => 'Tidak boleh mengambil mata kuliah semester di atas semester registrasi.'];
            }

            // Baris lama bisa berasal dari detail yang dihapus (soft delete)
            // atau yang berstatus Dropped. Karena unique constraint
            // (study_plan_id, course_offering_id) mencakup baris terhapus,
            // baris tersebut harus dipulihkan alih-alih dibuat ulang.
            $existing = StudyPlanDetail::query()
                ->withTrashed()
                ->where('study_plan_id', $studyPlan->id)
                ->where('course_offering_id', $offering->id)
                ->first();

            if ($existing && ! $existing->trashed() && in_array($existing->status, ['Draft', 'Taken'], true)) {
                return ['success' => false, 'message' => 'Mata kuliah sudah ada di KRS.'];
            }

            if (StudyPlanDetail::query()
                ->where('study_plan_id', $studyPlan->id)
                ->whereIn('status', ['Draft', 'Taken'])
                ->whereHas('courseOffering', fn ($query) => $query->where('course_id', $offering->course_id))
                ->exists()) {
                return ['success' => false, 'message' => 'Mata kuliah yang sama sudah dipilih di KRS ini.'];
            }

            $isRetake = false;

            if (($offering->semester_no ?? 0) < $registrationSemesterNo) {
                $retakeAllowed = TranscriptEntry::query()
                    ->where('student_profile_id', $student->id)
                    ->where('course_id', $offering->course_id)
                    ->whereIn('result_status', ['Failed', 'Incomplete'])
                    ->exists();

                if (! $retakeAllowed) {
                    return ['success' => false, 'message' => 'Mata kuliah semester sebelumnya hanya bisa diambil ulang jika pernah Failed atau Incomplete.'];
                }

                $isRetake = true;
            }

            $conflict = $this->scheduleConflict($studyPlan, $offering);

            if ($conflict !== null) {
                return ['success' => false, 'message' => $conflict];
            }

            $reservedSeats = StudyPlanDetail::query()
                ->where('course_offering_id', $offering->id)
                ->activeEnrollment()
                ->count();

            if ($offering->capacity !== null && $reservedSeats >= (int) $offering->capacity) {
                return ['success' => false, 'message' => 'Kelas sudah penuh. Silakan pilih offering kelas lain.'];
            }

            $credits = (int) ($offering->credits ?? $offering->course?->credits ?? 0);
            $selectedCredits = (int) StudyPlanDetail::query()
                ->where('study_plan_id', $studyPlan->id)
                ->whereIn('status', ['Draft', 'Taken'])
                ->sum('credits');

            if ($selectedCredits + $credits > self::MAX_CREDITS) {
                return [
                    'success' => false,
                    'message' => 'Beban studi melebihi batas '.self::MAX_CREDITS.' SKS. Sisa kuota '.max(0, self::MAX_CREDITS - $selectedCredits).' SKS.',
                ];
            }

            $attributes = [
                'credits' => $credits,
                'status' => 'Draft',
                'is_repeat' => $isRetake,
                'updated_by' => $userId,
                'deleted_by' => null,
            ];

            if ($existing) {
                $existing->restore();
                $existing->update($attributes);
            } else {
                StudyPlanDetail::create($attributes + [
                    'study_plan_id' => $studyPlan->id,
                    'course_offering_id' => $offering->id,
                    'created_by' => $userId,
                ]);
            }

            return ['success' => true, 'message' => 'Mata kuliah berhasil ditambahkan ke KRS.'];
        });
    }

    private function scheduleConflict(StudyPlan $studyPlan, CourseOffering $candidate): ?string
    {
        $candidateSchedules = $candidate->courseSchedules;

        if ($candidateSchedules->isEmpty()) {
            return null;
        }

        $selectedDetails = StudyPlanDetail::query()
            ->where('study_plan_id', $studyPlan->id)
            ->whereIn('status', ['Draft', 'Taken'])
            ->with([
                'courseOffering.course',
                'courseOffering.courseSchedules' => fn ($query) => $query->where('is_active', true),
            ])
            ->get();

        foreach ($selectedDetails as $detail) {
            foreach ($detail->courseOffering?->courseSchedules ?? [] as $selectedSchedule) {
                foreach ($candidateSchedules as $candidateSchedule) {
                    if ($selectedSchedule->day_of_week !== $candidateSchedule->day_of_week) {
                        continue;
                    }

                    if ($this->overlaps($selectedSchedule, $candidateSchedule)) {
                        $course = $detail->courseOffering?->course;
                        $label = trim(($course?->code ? $course->code.' - ' : '').($course?->name ?? $detail->courseOffering?->label ?? 'Mata kuliah'));

                        return 'Jadwal bentrok dengan '.$label.' ('.$this->time($selectedSchedule->start_time).'–'.$this->time($selectedSchedule->end_time).').';
                    }
                }
            }
        }

        return null;
    }

    private function overlaps($left, $right): bool
    {
        $leftStart = $this->time($left->start_time);
        $leftEnd = $this->time($left->end_time);
        $rightStart = $this->time($right->start_time);
        $rightEnd = $this->time($right->end_time);

        return $leftStart < $rightEnd && $leftEnd > $rightStart;
    }

    private function time(mixed $value): string
    {
        return $value instanceof \DateTimeInterface
            ? $value->format('H:i:s')
            : substr((string) $value, 0, 8);
    }
}
