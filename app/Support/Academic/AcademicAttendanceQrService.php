<?php

namespace App\Support\Academic;

use App\Models\Academic\AttendanceRecord;
use App\Models\Academic\AttendanceSession;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyPlanDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcademicAttendanceQrService
{
    private const TOKEN_GRACE_SLOTS = 6;

    public function openSession(AttendanceSession $session, int $userId): AttendanceSession
    {
        $session->forceFill([
            'status' => 'Opened',
            'attendance_method' => 'qr',
            'qr_secret' => $session->qr_secret ?: Str::random(48),
            'qr_interval_seconds' => max(2, (int) ($session->qr_interval_seconds ?: 2)),
            'opened_at' => now(),
            'closed_at' => null,
            'updated_by' => $userId,
        ])->save();

        return $session->fresh();
    }

    public function closeSession(AttendanceSession $session, int $userId): AttendanceSession
    {
        $session->forceFill([
            'status' => 'Closed',
            'closed_at' => now(),
            'updated_by' => $userId,
        ])->save();

        return $session->fresh();
    }

    public function qrPayload(AttendanceSession $session, ?int $timestamp = null): array
    {
        $session = $this->ensureSecret($session);
        $timestamp ??= now()->timestamp;
        $slot = $this->slot($session, $timestamp);
        $token = $this->tokenForSlot($session, $slot);

        return [
            'session_id' => $session->id,
            'slot' => $slot,
            'token' => $token,
            'interval' => $this->interval($session),
            'expires_at' => (($slot + 1) * $this->interval($session)),
            'url' => route('student.schedule.attendance.record', [
                'sessionId' => $session->id,
                'slot' => $slot,
                'token' => $token,
            ]),
        ];
    }

    public function recordScan(
        AttendanceSession $session,
        StudentProfile $studentProfile,
        string $token,
        int $slot,
        ?int $userId,
        array $metadata = []
    ): AttendanceRecord {
        $this->assertSessionAcceptsScan($session);
        $this->assertStudentCanAttend($session, $studentProfile);
        $this->assertTokenIsValid($session, $token, $slot);

        return DB::transaction(function () use ($session, $studentProfile, $slot, $userId, $metadata) {
            $record = AttendanceRecord::query()->firstOrNew([
                'attendance_session_id' => $session->id,
                'student_profile_id' => $studentProfile->id,
            ]);

            if ($record->exists && ! in_array($record->source, ['student_qr', 'student_qr_rescan'], true)) {
                throw ValidationException::withMessages([
                    'token' => 'Absensi sudah dicatat manual oleh dosen.',
                ]);
            }

            if (! $record->exists) {
                $record->created_by = $userId;
            }

            $record->forceFill([
                'status' => 'Present',
                'source' => $record->exists ? 'student_qr_rescan' : 'student_qr',
                'verification_status' => 'verified',
                'recorded_at' => now(),
                'recorded_by' => $userId,
                'scanned_at' => now(),
                'latitude' => $metadata['latitude'] ?? null,
                'longitude' => $metadata['longitude'] ?? null,
                'location_accuracy' => $metadata['accuracy'] ?? null,
                'device_fingerprint' => $metadata['device_fingerprint'] ?? null,
                'token_slot' => $slot,
                'ip_address' => $metadata['ip_address'] ?? request()?->ip(),
                'user_agent' => $metadata['user_agent'] ?? request()?->userAgent(),
                'updated_by' => $userId,
            ])->save();

            return $record->fresh(['attendanceSession.courseOffering.course']);
        });
    }

    public function isTokenCurrentlyValid(AttendanceSession $session, string $token, int $slot): bool
    {
        try {
            $this->assertSessionAcceptsScan($session);
            $this->assertTokenIsValid($session, $token, $slot);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    private function ensureSecret(AttendanceSession $session): AttendanceSession
    {
        if ($session->qr_secret) {
            return $session;
        }

        $session->forceFill([
            'qr_secret' => Str::random(48),
            'qr_interval_seconds' => max(2, (int) ($session->qr_interval_seconds ?: 2)),
        ])->save();

        return $session->fresh();
    }

    private function assertSessionAcceptsScan(AttendanceSession $session): void
    {
        if ($session->status !== 'Opened' || $session->closed_at !== null) {
            throw ValidationException::withMessages([
                'token' => 'Absensi sudah ditutup oleh dosen.',
            ]);
        }
    }

    private function assertStudentCanAttend(AttendanceSession $session, StudentProfile $studentProfile): void
    {
        $allowed = StudyPlanDetail::query()
            ->where('course_offering_id', $session->course_offering_id)
            ->whereHas('studyPlan', function ($query) use ($studentProfile) {
                $query->where('student_profile_id', $studentProfile->id)
                    ->where('status', 'Approved');
            })
            ->exists();

        if (! $allowed) {
            throw ValidationException::withMessages([
                'token' => 'Anda tidak terdaftar pada kelas ini.',
            ]);
        }
    }

    private function assertTokenIsValid(AttendanceSession $session, string $token, int $slot): void
    {
        $currentSlot = $this->slot($session, now()->timestamp);

        if ($slot > $currentSlot || $slot < ($currentSlot - self::TOKEN_GRACE_SLOTS)) {
            throw ValidationException::withMessages([
                'token' => 'Kode absensi sudah terlalu lama. Silakan scan ulang.',
            ]);
        }

        if (! hash_equals($this->tokenForSlot($session, $slot), $token)) {
            throw ValidationException::withMessages([
                'token' => 'Kode absensi tidak valid.',
            ]);
        }
    }

    private function tokenForSlot(AttendanceSession $session, int $slot): string
    {
        $secret = (string) $session->qr_secret;
        $payload = implode('|', [$session->id, $session->course_offering_id, $slot]);

        return strtoupper(substr(hash_hmac('sha256', $payload, $secret), 0, 20));
    }

    private function slot(AttendanceSession $session, int $timestamp): int
    {
        return (int) floor($timestamp / $this->interval($session));
    }

    private function interval(AttendanceSession $session): int
    {
        return max(2, (int) ($session->qr_interval_seconds ?: 2));
    }
}
