<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Academic\ConsultationAppointment;
use App\Models\Academic\ConsultationSlot;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyPlanDetail;
use App\Support\Inertia\ShellProps;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Konsultasi dosen mahasiswa.
 *
 * Koreksi dari Blade lama:
 * - Daftar dosen diambil dari kelas KRS aktif dan pembimbing akademik yang
 *   masih aktif. Sebelumnya kelas yang sudah dibatalkan tetap membuka akses
 *   konsultasi ke dosennya.
 * - `capacity` kini berarti kuota appointment per hari, bukan sekaligus
 *   pembatas jumlah pilihan jam. Semua jam dalam rentang slot tetap
 *   dihasilkan selama kuota harian belum penuh.
 * - Mengajukan ulang pada jam yang sama tidak lagi menimpa appointment yang
 *   masih aktif; mahasiswa diminta memilih sesi lain.
 * - Jam yang dikirim dipastikan selaras dengan kelipatan `slot_minutes` agar
 *   tidak bisa memesan di luar rentang atau di tengah-tengah sesi.
 * - Appointment yang di-soft-delete dipulihkan sebelum dipakai ulang supaya
 *   unique (slot, mahasiswa, starts_at) tidak dilanggar.
 */
class StudentConsultationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $this->studentProfile($request);

        abort_unless($profile !== null, 403, 'Profil mahasiswa tidak ditemukan.');

        $lecturerIds = $this->accessibleLecturerIds($profile);
        $slots = $this->availableSlots($profile, $lecturerIds);
        $appointments = $this->appointmentRows($profile);

        return Inertia::render('Student/Consultations/Index', [
            'shell' => ShellProps::make($user, 'Academic', 'Consultations'),
            'student' => [
                'name' => $user->name,
                'nim' => $profile->nim,
                'program' => $profile->studyProgram?->name ?? '-',
                'semester' => $profile->current_semester,
            ],
            'slots' => $slots,
            'appointments' => $appointments,
            'stats' => [
                'availableSlots' => count($slots),
                'requested' => collect($appointments)->where('status', 'requested')->count(),
                'confirmed' => collect($appointments)->where('status', 'confirmed')->count(),
                'completed' => collect($appointments)->where('status', 'completed')->count(),
            ],
            'urls' => [
                'index' => route('student.consultations.index'),
                'book' => route('student.consultations.book', ['slot' => '__ID__']),
                'cancel' => route('student.consultations.cancel', ['appointment' => '__ID__']),
            ],
        ]);
    }

    public function book(Request $request, ConsultationSlot $slot): RedirectResponse
    {
        $profile = $this->studentProfile($request);

        abort_unless($profile !== null, 403, 'Profil mahasiswa tidak ditemukan.');

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'topic' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless((int) $slot->is_active === 1, 404, 'Slot konsultasi tidak tersedia.');

        $date = Carbon::createFromFormat('Y-m-d', $validated['date'])->startOfDay();

        abort_unless($slot->coversDate($date), 422, 'Slot tidak tersedia pada tanggal tersebut.');

        $startsAt = Carbon::createFromFormat('Y-m-d H:i', $date->format('Y-m-d').' '.$validated['time']);

        if (! $startsAt->isFuture()) {
            throw ValidationException::withMessages([
                'time' => 'Pilih waktu konsultasi yang belum lewat.',
            ]);
        }

        $this->assertSlotAlignment($slot, $date, $startsAt);

        abort_unless(
            in_array((int) $slot->lecturer_profile_id, $this->accessibleLecturerIds($profile), true),
            403,
            'Dosen ini bukan pengampu atau pembimbing Anda.',
        );

        $own = ConsultationAppointment::query()
            ->withTrashed()
            ->where('consultation_slot_id', $slot->id)
            ->where('student_profile_id', $profile->id)
            ->where('starts_at', $startsAt)
            ->first();

        if ($own && ! $own->trashed() && ! in_array($own->status, ['cancelled', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'topic' => 'Kamu sudah mengajukan konsultasi pada sesi ini.',
            ]);
        }

        $takenByOther = ConsultationAppointment::query()
            ->where('consultation_slot_id', $slot->id)
            ->where('starts_at', $startsAt)
            ->whereIn('status', ConsultationAppointment::ACTIVE_STATUSES)
            ->when($own, fn ($query) => $query->whereKeyNot($own->id))
            ->exists();

        if ($takenByOther) {
            throw ValidationException::withMessages([
                'topic' => 'Sesi ini sudah dibooking mahasiswa lain. Pilih sesi lain yang masih tersedia.',
            ]);
        }

        $dailyActive = ConsultationAppointment::query()
            ->where('consultation_slot_id', $slot->id)
            ->whereDate('appointment_date', $date->toDateString())
            ->whereIn('status', ConsultationAppointment::ACTIVE_STATUSES)
            ->when($own, fn ($query) => $query->whereKeyNot($own->id))
            ->count();

        if ($dailyActive >= (int) $slot->capacity) {
            throw ValidationException::withMessages([
                'topic' => 'Kuota konsultasi pada tanggal ini sudah penuh.',
            ]);
        }

        if ($own?->trashed()) {
            $own->restore();
        }

        $appointment = $own ?? new ConsultationAppointment([
            'consultation_slot_id' => $slot->id,
            'student_profile_id' => $profile->id,
            'starts_at' => $startsAt,
        ]);

        $appointment->fill([
            'lecturer_profile_id' => $slot->lecturer_profile_id,
            'appointment_date' => $date->toDateString(),
            'ends_at' => $startsAt->copy()->addMinutes((int) $slot->slot_minutes),
            'status' => 'requested',
            'topic' => $validated['topic'],
            'student_notes' => $validated['notes'] ?? null,
            'cancellation_reason' => null,
            'cancelled_at' => null,
            'requested_at' => now(),
            'created_by' => $appointment->created_by ?? $request->user()->id,
            'updated_by' => $request->user()->id,
        ])->save();

        return back()->with('success', 'Request konsultasi terkirim.');
    }

    public function cancel(Request $request, ConsultationAppointment $appointment): RedirectResponse
    {
        $profile = $this->studentProfile($request);

        abort_unless($profile !== null, 403, 'Profil mahasiswa tidak ditemukan.');
        abort_if((int) $appointment->student_profile_id !== (int) $profile->id, 403);

        if (! in_array($appointment->status, ['requested', 'confirmed', 'waitlisted'], true)) {
            return back()->with('error', 'Appointment ini tidak bisa dibatalkan.');
        }

        $appointment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Dibatalkan oleh mahasiswa.',
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Appointment dibatalkan.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function availableSlots(StudentProfile $profile, array $lecturerIds): array
    {
        if ($lecturerIds === []) {
            return [];
        }

        $consultationSlots = ConsultationSlot::query()
            ->with(['lecturerProfile.user', 'lecturerProfile.studyProgram', 'appointments'])
            ->whereIn('lecturer_profile_id', $lecturerIds)
            ->where('is_active', true)
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get();

        $rows = [];
        $today = now()->startOfDay();
        $end = $today->copy()->addDays(21);

        foreach ($consultationSlots as $consultationSlot) {
            $slotMinutes = max(1, (int) $consultationSlot->slot_minutes);
            $capacity = max(1, (int) $consultationSlot->capacity);

            for ($date = $today->copy(); $date->lte($end); $date->addDay()) {
                if (! $consultationSlot->coversDate($date)) {
                    continue;
                }

                $dailyActive = $consultationSlot->appointments
                    ->filter(fn (ConsultationAppointment $appointment) => $appointment->appointment_date?->toDateString() === $date->toDateString())
                    ->whereIn('status', ConsultationAppointment::ACTIVE_STATUSES)
                    ->count();

                if ($dailyActive >= $capacity) {
                    continue;
                }

                $cursor = Carbon::parse($date->toDateString().' '.substr((string) $consultationSlot->start_time, 0, 5));
                $slotEnd = Carbon::parse($date->toDateString().' '.substr((string) $consultationSlot->end_time, 0, 5));

                while ($cursor->copy()->addMinutes($slotMinutes)->lte($slotEnd)) {
                    if ($cursor->isFuture()) {
                        $slotAppointments = $consultationSlot->appointments
                            ->filter(fn (ConsultationAppointment $appointment) => $appointment->starts_at?->equalTo($cursor));

                        $hasOwnBooking = $slotAppointments
                            ->where('student_profile_id', $profile->id)
                            ->whereNotIn('status', ['cancelled', 'rejected'])
                            ->isNotEmpty();

                        $hasOtherBooking = $slotAppointments
                            ->whereIn('status', ConsultationAppointment::ACTIVE_STATUSES)
                            ->where('student_profile_id', '!=', $profile->id)
                            ->isNotEmpty();

                        if (! $hasOtherBooking || $hasOwnBooking) {
                            $rows[] = [
                                'key' => $consultationSlot->id.'_'.$date->format('Ymd').'_'.$cursor->format('Hi'),
                                'slotId' => $consultationSlot->id,
                                'dateValue' => $date->toDateString(),
                                'timeValue' => $cursor->format('H:i'),
                                'startsAt' => $cursor->format('Y-m-d H:i:s'),
                                'lecturer' => $consultationSlot->lecturerProfile?->user?->name ?? '-',
                                'studyProgram' => $consultationSlot->lecturerProfile?->studyProgram?->name ?? '-',
                                'title' => $consultationSlot->title,
                                'date' => $date->translatedFormat('D, d M Y'),
                                'time' => $cursor->format('H:i').' - '.$cursor->copy()->addMinutes($slotMinutes)->format('H:i'),
                                'mode' => $consultationSlot->modeLabel(),
                                'place' => $consultationSlot->location ?: $consultationSlot->meeting_link ?: '-',
                                'notes' => $consultationSlot->notes,
                                'remaining' => $hasOwnBooking ? 0 : min(1, $capacity - $dailyActive),
                                'capacity' => $capacity,
                                'hasOwnBooking' => $hasOwnBooking,
                            ];
                        }
                    }

                    $cursor->addMinutes($slotMinutes);
                }
            }
        }

        return collect($rows)
            ->sortBy('startsAt')
            ->take(24)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function appointmentRows(StudentProfile $profile): array
    {
        return ConsultationAppointment::query()
            ->with(['lecturerProfile.user', 'consultationSlot'])
            ->where('student_profile_id', $profile->id)
            ->orderByDesc('starts_at')
            ->take(20)
            ->get()
            ->map(fn (ConsultationAppointment $appointment) => [
                'id' => $appointment->id,
                'lecturer' => $appointment->lecturerProfile?->user?->name ?? '-',
                'schedule' => ($appointment->starts_at?->format('d M Y H:i') ?? '-')
                    .' - '.($appointment->ends_at?->format('H:i') ?? '-'),
                'topic' => $appointment->topic,
                'status' => $appointment->status,
                'statusLabel' => $appointment->statusLabel(),
                'statusTone' => $this->statusTone($appointment->status),
                'place' => $appointment->consultationSlot?->location
                    ?: $appointment->consultationSlot?->meeting_link
                    ?: '-',
                'studentNotes' => $appointment->student_notes,
                'lecturerNotes' => $appointment->lecturer_notes,
                'canCancel' => in_array($appointment->status, ['requested', 'confirmed', 'waitlisted'], true),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function accessibleLecturerIds(StudentProfile $profile): array
    {
        $offeringIds = StudyPlanDetail::query()
            ->activeEnrollment()
            ->whereHas('studyPlan', fn ($query) => $query->where('student_profile_id', $profile->id))
            ->pluck('course_offering_id')
            ->filter()
            ->unique()
            ->values();

        $courseLecturers = CourseOfferingLecturer::query()
            ->whereIn('course_offering_id', $offeringIds)
            ->where('is_active', true)
            ->pluck('lecturer_profile_id');

        $advisorLecturers = $profile->advisorAssignments()
            ->where('is_active', true)
            ->pluck('lecturer_profile_id');

        return $courseLecturers
            ->merge($advisorLecturers)
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function assertSlotAlignment(ConsultationSlot $slot, Carbon $date, Carbon $startsAt): void
    {
        $slotStart = Carbon::parse($date->toDateString().' '.substr((string) $slot->start_time, 0, 5));
        $slotEnd = Carbon::parse($date->toDateString().' '.substr((string) $slot->end_time, 0, 5));

        $offset = $slotStart->diffInMinutes($startsAt, false);
        $aligned = $offset >= 0
            && $offset % max(1, (int) $slot->slot_minutes) === 0
            && $startsAt->copy()->addMinutes((int) $slot->slot_minutes)->lte($slotEnd);

        if (! $aligned) {
            throw ValidationException::withMessages([
                'time' => 'Waktu konsultasi tidak sesuai dengan jadwal slot.',
            ]);
        }
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'confirmed' => 'brand',
            'waitlisted' => 'amber',
            'completed' => 'green',
            'cancelled', 'rejected', 'no_show' => 'red',
            default => 'gray',
        };
    }

    private function studentProfile(Request $request): ?StudentProfile
    {
        return $request->user()?->studentProfile()->with('studyProgram')->first();
    }
}
