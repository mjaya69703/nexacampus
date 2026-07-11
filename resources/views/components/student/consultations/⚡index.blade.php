<?php

use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\ConsultationSlot;
use App\Models\Academic\ConsultationAppointment;
use App\Models\Academic\StudyPlan;
use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public array $availableSlots = [];
    public array $appointments = [];
    public array $bookingTopics = [];
    public array $bookingNotes = [];
    public array $stats = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->studentProfile?->id, 403);
        $this->loadPage();
    }

    public function book(string $slotKey): void
    {
        $validated = $this->validate([
            "bookingTopics.{$slotKey}" => ['required', 'string', 'max:160'],
            "bookingNotes.{$slotKey}" => ['nullable', 'string', 'max:1000'],
        ]);

        [$consultationSlotId, $date, $time] = explode('_', $slotKey);
        $consultationSlot = ConsultationSlot::query()
            ->whereKey((int) $consultationSlotId)
            ->where('is_active', true)
            ->firstOrFail();

        $appointmentDate = Carbon::createFromFormat('Ymd', $date)->startOfDay();
        abort_unless($consultationSlot->coversDate($appointmentDate), 403);

        $startsAt = Carbon::createFromFormat('Ymd Hi', $date.' '.$time);
        $endsAt = $startsAt->copy()->addMinutes($consultationSlot->slot_minutes);
        abort_unless($startsAt->isFuture(), 422);

        $studentProfileId = auth()->user()->studentProfile->id;
        abort_unless(in_array($consultationSlot->lecturer_profile_id, $this->accessibleLecturerIds(), true), 403);

        $activeCount = ConsultationAppointment::query()
            ->where('consultation_slot_id', $consultationSlot->id)
            ->where('starts_at', $startsAt)
            ->whereIn('status', ConsultationAppointment::ACTIVE_STATUSES)
            ->count();

        if ($activeCount > 0) {
            $this->addError("bookingTopics.{$slotKey}", 'Sesi ini sudah dibooking mahasiswa lain. Pilih sesi lain yang masih tersedia.');
            $this->loadPage();

            return;
        }

        $dailyActiveCount = ConsultationAppointment::query()
            ->where('consultation_slot_id', $consultationSlot->id)
            ->whereDate('appointment_date', $appointmentDate->toDateString())
            ->whereIn('status', ConsultationAppointment::ACTIVE_STATUSES)
            ->count();

        if ($dailyActiveCount >= $consultationSlot->capacity) {
            $this->addError("bookingTopics.{$slotKey}", 'Kuota konsultasi pada tanggal ini sudah penuh.');
            $this->loadPage();

            return;
        }

        $status = 'requested';

        ConsultationAppointment::query()->updateOrCreate(
            [
                'consultation_slot_id' => $consultationSlot->id,
                'student_profile_id' => $studentProfileId,
                'starts_at' => $startsAt,
            ],
            [
                'lecturer_profile_id' => $consultationSlot->lecturer_profile_id,
                'appointment_date' => $appointmentDate->toDateString(),
                'ends_at' => $endsAt,
                'status' => $status,
                'topic' => $validated['bookingTopics'][$slotKey],
                'student_notes' => $validated['bookingNotes'][$slotKey] ?? null,
                'requested_at' => now(),
                'updated_by' => auth()->id(),
                'created_by' => auth()->id(),
            ],
        );

        $this->bookingTopics[$slotKey] = '';
        $this->bookingNotes[$slotKey] = '';
        $this->dispatch('success', message: 'Request konsultasi terkirim.');
        $this->loadPage();
    }

    public function cancel(int $id): void
    {
        ConsultationAppointment::query()
            ->whereKey($id)
            ->where('student_profile_id', auth()->user()->studentProfile->id)
            ->whereIn('status', ['requested', 'confirmed', 'waitlisted'])
            ->firstOrFail()
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => 'Dibatalkan oleh mahasiswa.',
                'updated_by' => auth()->id(),
            ]);

        $this->dispatch('success', message: 'Appointment dibatalkan.');
        $this->loadPage();
    }

    private function loadPage(): void
    {
        $this->loadSlots();
        $this->loadAppointments();
        $this->loadStats();
    }

    private function loadStats(): void
    {
        $appointments = collect($this->appointments);

        $this->stats = [
            'available_slots' => count($this->availableSlots),
            'requested' => $appointments->where('status', 'requested')->count(),
            'confirmed' => $appointments->where('status', 'confirmed')->count(),
            'completed' => $appointments->where('status', 'completed')->count(),
        ];
    }

    private function loadSlots(): void
    {
        $lecturerIds = $this->accessibleLecturerIds();

        if ($lecturerIds === []) {
            $this->availableSlots = [];

            return;
        }

        $consultationSlots = ConsultationSlot::query()
            ->with(['lecturerProfile.user', 'lecturerProfile.studyProgram', 'appointments'])
            ->whereIn('lecturer_profile_id', $lecturerIds)
            ->where('is_active', true)
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get();

        $slots = [];
        $today = now()->startOfDay();
        $end = $today->copy()->addDays(21);
        $studentProfileId = auth()->user()->studentProfile->id;

        foreach ($consultationSlots as $consultationSlot) {
            for ($date = $today->copy(); $date->lte($end); $date->addDay()) {
                if (! $consultationSlot->coversDate($date)) {
                    continue;
                }

                $cursor = Carbon::parse($date->toDateString().' '.substr((string) $consultationSlot->start_time, 0, 5));
                $consultationSlotEnd = Carbon::parse($date->toDateString().' '.substr((string) $consultationSlot->end_time, 0, 5));
                $generatedSlots = 0;

                while ($cursor->copy()->addMinutes($consultationSlot->slot_minutes)->lte($consultationSlotEnd)) {
                    if ($generatedSlots >= $consultationSlot->capacity) {
                        break;
                    }

                    if ($cursor->isFuture()) {
                        $key = $consultationSlot->id.'_'.$date->format('Ymd').'_'.$cursor->format('Hi');
                        $slotAppointments = $consultationSlot->appointments
                            ->filter(fn (ConsultationAppointment $appointment) => $appointment->starts_at?->equalTo($cursor));
                        $hasActiveBooking = $slotAppointments
                            ->whereIn('status', ConsultationAppointment::ACTIVE_STATUSES)
                            ->isNotEmpty();
                        $hasOwnBooking = $slotAppointments
                            ->where('student_profile_id', $studentProfileId)
                            ->whereNotIn('status', ['cancelled', 'rejected'])
                            ->isNotEmpty();

                        if ($hasActiveBooking && ! $hasOwnBooking) {
                            $generatedSlots++;
                            $cursor->addMinutes($consultationSlot->slot_minutes);

                            continue;
                        }

                        $slots[] = [
                            'key' => $key,
                            'starts_at' => $cursor->format('Y-m-d H:i:s'),
                            'lecturer' => $consultationSlot->lecturerProfile?->user?->name ?? '-',
                            'study_program' => $consultationSlot->lecturerProfile?->studyProgram?->name ?? '-',
                            'title' => $consultationSlot->title,
                            'date' => $date->translatedFormat('D, d M Y'),
                            'time' => $cursor->format('H:i').' - '.$cursor->copy()->addMinutes($consultationSlot->slot_minutes)->format('H:i'),
                            'mode' => $consultationSlot->modeLabel(),
                            'place' => $consultationSlot->location ?: $consultationSlot->meeting_link ?: '-',
                            'notes' => $consultationSlot->notes,
                            'remaining' => $hasOwnBooking ? 0 : 1,
                            'capacity' => $consultationSlot->capacity,
                            'has_own_booking' => $hasOwnBooking,
                        ];

                        $generatedSlots++;
                    }

                    $cursor->addMinutes($consultationSlot->slot_minutes);
                }
            }
        }

        $this->availableSlots = collect($slots)
            ->sortBy('starts_at')
            ->take(24)
            ->values()
            ->all();
    }

    private function loadAppointments(): void
    {
        $this->appointments = ConsultationAppointment::query()
            ->with(['lecturerProfile.user', 'consultationSlot'])
            ->where('student_profile_id', auth()->user()->studentProfile->id)
            ->latest('starts_at')
            ->take(20)
            ->get()
            ->map(fn (ConsultationAppointment $appointment) => [
                'id' => $appointment->id,
                'lecturer' => $appointment->lecturerProfile?->user?->name ?? '-',
                'schedule' => $appointment->starts_at?->format('d M Y H:i').' - '.$appointment->ends_at?->format('H:i'),
                'topic' => $appointment->topic,
                'status' => $appointment->status,
                'status_label' => $appointment->statusLabel(),
                'place' => $appointment->consultationSlot?->location ?: $appointment->consultationSlot?->meeting_link ?: '-',
                'lecturer_notes' => $appointment->lecturer_notes,
            ])
            ->values()
            ->all();
    }

    private function accessibleLecturerIds(): array
    {
        $studentProfile = auth()->user()->studentProfile;

        $offeringIds = StudyPlan::query()
            ->where('student_profile_id', $studentProfile->id)
            ->with('details:id,study_plan_id,course_offering_id')
            ->get()
            ->flatMap(fn (StudyPlan $plan) => $plan->details->pluck('course_offering_id'))
            ->filter()
            ->unique()
            ->values();

        $courseLecturers = CourseOfferingLecturer::query()
            ->whereIn('course_offering_id', $offeringIds)
            ->where('is_active', true)
            ->pluck('lecturer_profile_id');

        $advisorLecturers = $studentProfile->advisorAssignments()
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

    public function statusStyle(string $status): string
    {
        return match ($status) {
            'confirmed' => 'background:#dbeafe;color:#2563eb;',
            'waitlisted' => 'background:#fef3c7;color:#b45309;',
            'completed' => 'background:#dcfce7;color:#15803d;',
            'cancelled', 'rejected', 'no_show' => 'background:#fee2e2;color:#dc2626;',
            default => 'background:#f1f5f9;color:#64748b;',
        };
    }

    public function render(): View
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Konsultasi',
        ]);
    }
};
?>

@include('components.lecturer.assignments.assignment-styles')

<div>
    <x-alert />

    <div class="card assignment-card assignment-hero mb-4">
        <div class="card-body p-4 p-lg-5" style="position:relative;">
            <div class="d-flex justify-content-between gap-3 flex-wrap">
                <div class="d-flex gap-3">
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.75rem;"><i class="fas fa-calendar-check"></i></span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Bimbingan Akademik</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">Konsultasi Dosen</h1>
                        <div style="opacity:.9;">Pilih slot konsultasi dari dosen pengampu atau dosen pembimbing kamu.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-clock"></i>{{ $stats['available_slots'] ?? 0 }} slot tersedia</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-inbox"></i>{{ $stats['requested'] ?? 0 }} menunggu</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-check-circle"></i>{{ $stats['confirmed'] ?? 0 }} terkonfirmasi</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-flag-checkered"></i>{{ $stats['completed'] ?? 0 }} selesai</span>
                        </div>
                    </div>
                </div>
                <div class="assignment-panel" style="min-width:min(100%, 260px);background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.28);color:white;">
                    <div class="text-white fw-bold">Request Saya</div>
                    <div style="opacity:.9;">{{ count($appointments) }} riwayat konsultasi</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card assignment-card">
                <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-calendar-days me-2 text-primary"></i>Slot Konsultasi</h3>
                    <span class="assignment-pill">{{ count($availableSlots) }} slot</span>
                </div>
                <div class="card-body p-4">
                    <div class="assignment-shell">
                        @forelse ($availableSlots as $slot)
                            <div class="assignment-list-item">
                                <div class="row g-3 align-items-start">
                                    <div class="col-xl-7">
                                        <div class="d-flex gap-3">
                                            <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-user-tie"></i></span>
                                            <div>
                                                <div class="d-flex flex-wrap gap-2 mb-2">
                                                    <span class="assignment-pill" style="{{ $slot['has_own_booking'] ? 'background:#dbeafe;color:#2563eb;' : ($slot['remaining'] > 0 ? 'background:#dcfce7;color:#15803d;' : 'background:#fef3c7;color:#b45309;') }}">
                                                        <i class="fas fa-chair"></i>{{ $slot['has_own_booking'] ? 'Sudah diajukan' : ($slot['remaining'] > 0 ? 'Tersedia' : 'Penuh') }}
                                                    </span>
                                                    <span class="assignment-pill"><i class="fas fa-clock"></i>{{ $slot['date'] }} &middot; {{ $slot['time'] }}</span>
                                                </div>
                                                <div class="fw-bold">{{ $slot['lecturer'] }}</div>
                                                <div class="text-secondary small">{{ $slot['study_program'] }}</div>
                                                <div class="assignment-panel mt-3">
                                                    <div class="fw-bold">{{ $slot['title'] }}</div>
                                                    <div class="text-secondary small">{{ $slot['mode'] }} &middot; {{ $slot['place'] }}</div>
                                                    @if ($slot['notes'])
                                                        <div class="text-secondary small mt-2">{{ $slot['notes'] }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-5">
                                        @if ($slot['has_own_booking'])
                                            <div class="assignment-panel h-100">
                                                <div class="text-primary fw-bold"><i class="fas fa-circle-info me-1"></i>Request sudah ada</div>
                                                <div class="text-secondary small mt-1">Kamu sudah mengajukan konsultasi pada slot ini.</div>
                                            </div>
                                        @else
                                            <div class="assignment-panel">
                                                <label class="form-label">Topik</label>
                                                <input type="text" class="form-control" wire:model.defer="bookingTopics.{{ $slot['key'] }}">
                                                @error("bookingTopics.{$slot['key']}") <small class="text-danger">{{ $message }}</small> @enderror
                                                <label class="form-label mt-3">Catatan</label>
                                                <textarea rows="2" class="form-control" wire:model.defer="bookingNotes.{{ $slot['key'] }}"></textarea>
                                                <button type="button" class="assignment-action mt-3 w-100" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;" wire:click="book('{{ $slot['key'] }}')" wire:loading.attr="disabled">
                                                    <i class="fas fa-paper-plane"></i>Ajukan Konsultasi
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-secondary py-5">
                                <i class="fas fa-calendar-days fa-3x mb-3"></i>
                                <div>Belum ada slot konsultasi dari dosen terkait.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card assignment-card">
                <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-list-check me-2 text-primary"></i>Request Saya</h3>
                    <span class="assignment-pill">{{ count($appointments) }} request</span>
                </div>
                <div class="card-body p-4">
                    <div class="assignment-shell">
                        @forelse ($appointments as $appointment)
                            <div class="assignment-list-item">
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span class="assignment-pill" style="{{ $this->statusStyle($appointment['status']) }}"><i class="fas fa-circle-info"></i>{{ $appointment['status_label'] }}</span>
                                </div>
                                <div class="fw-bold">{{ $appointment['topic'] }}</div>
                                <div class="text-secondary small">{{ $appointment['lecturer'] }}</div>
                                <div class="assignment-panel mt-3">
                                    <div class="text-secondary small">{{ $appointment['schedule'] }}</div>
                                    <div class="text-secondary small">{{ $appointment['place'] }}</div>
                                    @if ($appointment['lecturer_notes'])
                                        <div class="mt-2">{{ $appointment['lecturer_notes'] }}</div>
                                    @endif
                                </div>
                                @if (in_array($appointment['status'], ['requested', 'confirmed', 'waitlisted'], true))
                                    <button type="button" class="btn btn-outline-danger btn-sm mt-3" wire:click="cancel({{ $appointment['id'] }})">Batalkan</button>
                                @endif
                            </div>
                        @empty
                            <div class="text-center text-secondary py-5">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <div>Belum ada request konsultasi.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
