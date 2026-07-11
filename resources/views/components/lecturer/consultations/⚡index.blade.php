<?php

use App\Models\Academic\ConsultationSlot;
use App\Models\Academic\ConsultationAppointment;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public array $form = [
        'title' => 'Konsultasi Akademik',
        'weekday' => 1,
        'start_time' => '09:00',
        'end_time' => '11:00',
        'slot_minutes' => 30,
        'capacity' => 1,
        'consultation_mode' => 'in_person',
        'location' => '',
        'meeting_link' => '',
        'starts_on' => '',
        'ends_on' => '',
        'notes' => '',
        'is_active' => true,
    ];
    public array $consultationSlots = [];
    public array $appointments = [];
    public array $appointmentNotes = [];
    public array $stats = [];
    public ?int $editingId = null;
    public bool $showForm = false;
    public string $appointmentFilter = 'active';

    public function mount(): void
    {
        abort_unless(auth()->user()?->lecturerProfile?->id, 403);
        $this->loadPage();
    }

    public function updatedAppointmentFilter(): void
    {
        $this->loadAppointments();
    }

    public function createConsultationSlot(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function saveConsultationSlot(): void
    {
        $validated = $this->validate([
            'form.title' => ['required', 'string', 'max:120'],
            'form.weekday' => ['required', 'integer', 'min:1', 'max:7'],
            'form.start_time' => ['required', 'date_format:H:i'],
            'form.end_time' => ['required', 'date_format:H:i', 'after:form.start_time'],
            'form.slot_minutes' => ['required', 'integer', 'min:15', 'max:180'],
            'form.capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'form.consultation_mode' => ['required', 'in:in_person,online,hybrid'],
            'form.location' => ['nullable', 'string', 'max:160'],
            'form.meeting_link' => ['nullable', 'url', 'max:255'],
            'form.starts_on' => ['nullable', 'date'],
            'form.ends_on' => ['nullable', 'date', 'after_or_equal:form.starts_on'],
            'form.notes' => ['nullable', 'string', 'max:1000'],
            'form.is_active' => ['boolean'],
        ]);

        $lecturerProfileId = auth()->user()->lecturerProfile->id;
        $payload = $validated['form'] + [
            'lecturer_profile_id' => $lecturerProfileId,
            'updated_by' => auth()->id(),
        ];
        $payload['starts_on'] = $payload['starts_on'] ?: null;
        $payload['ends_on'] = $payload['ends_on'] ?: null;
        $payload['location'] = $payload['location'] ?: null;
        $payload['meeting_link'] = $payload['meeting_link'] ?: null;
        $payload['notes'] = $payload['notes'] ?: null;

        if ($this->editingId) {
            ConsultationSlot::query()
                ->whereKey($this->editingId)
                ->where('lecturer_profile_id', $lecturerProfileId)
                ->firstOrFail()
                ->update($payload);
        } else {
            ConsultationSlot::query()->create($payload + ['created_by' => auth()->id()]);
        }

        $this->resetForm();
        $this->showForm = false;
        $this->dispatch('success', message: 'Jadwal konsultasi berhasil disimpan.');
        $this->loadPage();
    }

    public function editConsultationSlot(int $id): void
    {
        $consultationSlot = ConsultationSlot::query()
            ->whereKey($id)
            ->where('lecturer_profile_id', auth()->user()->lecturerProfile->id)
            ->firstOrFail();

        $this->editingId = $consultationSlot->id;
        $this->showForm = true;
        $this->form = [
            'title' => $consultationSlot->title,
            'weekday' => $consultationSlot->weekday,
            'start_time' => substr((string) $consultationSlot->start_time, 0, 5),
            'end_time' => substr((string) $consultationSlot->end_time, 0, 5),
            'slot_minutes' => $consultationSlot->slot_minutes,
            'capacity' => $consultationSlot->capacity,
            'consultation_mode' => $consultationSlot->consultation_mode,
            'location' => $consultationSlot->location ?? '',
            'meeting_link' => $consultationSlot->meeting_link ?? '',
            'starts_on' => $consultationSlot->starts_on?->format('Y-m-d') ?? '',
            'ends_on' => $consultationSlot->ends_on?->format('Y-m-d') ?? '',
            'notes' => $consultationSlot->notes ?? '',
            'is_active' => $consultationSlot->is_active,
        ];
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'title' => 'Konsultasi Akademik',
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'slot_minutes' => 30,
            'capacity' => 1,
            'consultation_mode' => 'in_person',
            'location' => '',
            'meeting_link' => '',
            'starts_on' => '',
            'ends_on' => '',
            'notes' => '',
            'is_active' => true,
        ];
    }

    public function cancelForm(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    public function toggleConsultationSlot(int $id): void
    {
        $consultationSlot = ConsultationSlot::query()
            ->whereKey($id)
            ->where('lecturer_profile_id', auth()->user()->lecturerProfile->id)
            ->firstOrFail();

        $consultationSlot->update([
            'is_active' => ! $consultationSlot->is_active,
            'updated_by' => auth()->id(),
        ]);

        $this->loadConsultationSlots();
        $this->loadStats();
    }

    public function setAppointmentStatus(int $id, string $status): void
    {
        abort_unless(in_array($status, ['confirmed', 'completed', 'cancelled', 'rejected', 'no_show'], true), 422);

        $appointment = ConsultationAppointment::query()
            ->whereKey($id)
            ->where('lecturer_profile_id', auth()->user()->lecturerProfile->id)
            ->firstOrFail();

        $payload = ['status' => $status, 'updated_by' => auth()->id()];
        if ($status === 'confirmed') {
            $payload['confirmed_at'] = now();
        }
        if ($status === 'completed') {
            $payload['completed_at'] = now();
        }
        if (in_array($status, ['cancelled', 'rejected', 'no_show'], true)) {
            $payload['cancelled_at'] = now();
        }

        $appointment->update($payload);
        $this->dispatch('success', message: 'Status konsultasi diperbarui.');
        $this->loadAppointments();
        $this->loadStats();
    }

    public function saveAppointmentNote(int $id): void
    {
        $validated = $this->validate([
            "appointmentNotes.{$id}" => ['nullable', 'string', 'max:2000'],
        ]);

        ConsultationAppointment::query()
            ->whereKey($id)
            ->where('lecturer_profile_id', auth()->user()->lecturerProfile->id)
            ->firstOrFail()
            ->update([
                'lecturer_notes' => $validated['appointmentNotes'][$id] ?? null,
                'updated_by' => auth()->id(),
            ]);

        $this->dispatch('success', message: 'Catatan konsultasi disimpan.');
        $this->loadAppointments();
    }

    private function loadPage(): void
    {
        $this->loadStats();
        $this->loadConsultationSlots();
        $this->loadAppointments();
    }

    private function loadStats(): void
    {
        $lecturerProfileId = auth()->user()->lecturerProfile->id;

        $this->stats = [
            'active_slots' => ConsultationSlot::query()
                ->where('lecturer_profile_id', $lecturerProfileId)
                ->where('is_active', true)
                ->count(),
            'requested' => ConsultationAppointment::query()
                ->where('lecturer_profile_id', $lecturerProfileId)
                ->where('status', 'requested')
                ->count(),
            'confirmed' => ConsultationAppointment::query()
                ->where('lecturer_profile_id', $lecturerProfileId)
                ->where('status', 'confirmed')
                ->where('starts_at', '>=', now())
                ->count(),
            'completed' => ConsultationAppointment::query()
                ->where('lecturer_profile_id', $lecturerProfileId)
                ->where('status', 'completed')
                ->whereDate('appointment_date', '>=', now()->startOfMonth()->toDateString())
                ->count(),
        ];
    }

    private function loadConsultationSlots(): void
    {
        $lecturerProfileId = auth()->user()->lecturerProfile->id;

        $this->consultationSlots = ConsultationSlot::query()
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->withCount([
                'appointments as upcoming_count' => fn ($query) => $query
                    ->whereIn('status', ['requested', 'confirmed', 'waitlisted'])
                    ->where('starts_at', '>=', now()),
            ])
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->map(fn (ConsultationSlot $consultationSlot) => [
                'id' => $consultationSlot->id,
                'title' => $consultationSlot->title,
                'weekday' => $consultationSlot->weekdayLabel(),
                'time' => substr((string) $consultationSlot->start_time, 0, 5).' - '.substr((string) $consultationSlot->end_time, 0, 5),
                'slot_minutes' => $consultationSlot->slot_minutes,
                'capacity' => $consultationSlot->capacity,
                'mode' => $consultationSlot->modeLabel(),
                'location' => $consultationSlot->location ?: $consultationSlot->meeting_link ?: '-',
                'is_active' => $consultationSlot->is_active,
                'upcoming_count' => $consultationSlot->upcoming_count,
            ])
            ->values()
            ->all();
    }

    private function loadAppointments(): void
    {
        $lecturerProfileId = auth()->user()->lecturerProfile->id;

        $query = ConsultationAppointment::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'consultationSlot'])
            ->where('lecturer_profile_id', $lecturerProfileId);

        match ($this->appointmentFilter) {
            'history' => $query->whereIn('status', ['completed', 'cancelled', 'rejected', 'no_show']),
            'waitlisted' => $query->where('status', 'waitlisted'),
            default => $query->whereIn('status', ['requested', 'confirmed']),
        };

        $appointments = $query
            ->orderBy('starts_at')
            ->take(50)
            ->get();

        $this->appointmentNotes = $appointments
            ->mapWithKeys(fn (ConsultationAppointment $appointment) => [$appointment->id => $appointment->lecturer_notes ?? ''])
            ->all();

        $this->appointments = $appointments
            ->map(fn (ConsultationAppointment $appointment) => [
                'id' => $appointment->id,
                'student' => $appointment->studentProfile?->user?->name ?? '-',
                'nim' => $appointment->studentProfile?->nim ?? '-',
                'study_program' => $appointment->studentProfile?->studyProgram?->name ?? '-',
                'topic' => $appointment->topic,
                'student_notes' => $appointment->student_notes,
                'status' => $appointment->status,
                'status_label' => $appointment->statusLabel(),
                'schedule' => $appointment->starts_at?->format('d M Y H:i').' - '.$appointment->ends_at?->format('H:i'),
                'mode' => $appointment->consultationSlot?->modeLabel() ?? '-',
                'place' => $appointment->consultationSlot?->location ?: $appointment->consultationSlot?->meeting_link ?: '-',
            ])
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
                        <h1 class="h2 mb-2" style="font-weight:900;">Konsultasi</h1>
                        <div style="opacity:.9;">Atur slot mingguan, konfirmasi request mahasiswa, dan catat hasil konsultasi.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-clock"></i>{{ $stats['active_slots'] ?? 0 }} slot aktif</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-inbox"></i>{{ $stats['requested'] ?? 0 }} menunggu</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-check-circle"></i>{{ $stats['confirmed'] ?? 0 }} terkonfirmasi</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-flag-checkered"></i>{{ $stats['completed'] ?? 0 }} selesai</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div class="assignment-panel" style="min-width:min(100%, 240px);background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.28);color:white;">
                        <label class="form-label" style="color:white;">Filter appointment</label>
                        <select class="form-select" wire:model.live="appointmentFilter">
                            <option value="active">Aktif</option>
                            <option value="waitlisted">Waiting list</option>
                            <option value="history">Riwayat</option>
                        </select>
                    </div>
                    <button type="button" class="assignment-action" style="background:#fff;color:#4f46e5;" wire:click="createConsultationSlot">
                        <i class="fas fa-plus"></i>Tambah Slot
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if ($showForm)
        <form wire:submit.prevent="saveConsultationSlot" class="card assignment-card mb-4">
            <div class="card-header py-3">
                <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-clock me-2 text-primary"></i>{{ $editingId ? 'Edit Slot Konsultasi' : 'Tambah Slot Konsultasi' }}</h3>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-lg-4">
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-control" wire:model.defer="form.title">
                        @error('form.title') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Hari</label>
                        <select class="form-select" wire:model.defer="form.weekday">
                            @foreach ([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Mulai</label>
                        <input type="time" class="form-control" wire:model.defer="form.start_time">
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Selesai</label>
                        <input type="time" class="form-control" wire:model.defer="form.end_time">
                        @error('form.end_time') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Durasi</label>
                        <input type="number" min="15" class="form-control" wire:model.defer="form.slot_minutes">
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Jumlah Sesi</label>
                        <input type="number" min="1" class="form-control" wire:model.defer="form.capacity">
                        <div class="form-hint">Maksimal sesi booking per tanggal.</div>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Mode</label>
                        <select class="form-select" wire:model.defer="form.consultation_mode">
                            <option value="in_person">Tatap muka</option>
                            <option value="online">Online</option>
                            <option value="hybrid">Hybrid</option>
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">Lokasi</label>
                        <input type="text" class="form-control" wire:model.defer="form.location">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">Link Meeting</label>
                        <input type="url" class="form-control" wire:model.defer="form.meeting_link">
                        @error('form.meeting_link') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Berlaku Dari</label>
                        <input type="date" class="form-control" wire:model.defer="form.starts_on">
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Sampai</label>
                        <input type="date" class="form-control" wire:model.defer="form.ends_on">
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label">Catatan</label>
                        <textarea rows="2" class="form-control" wire:model.defer="form.notes"></textarea>
                    </div>
                    <div class="col-lg-2 d-flex align-items-end">
                        <label class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" wire:model.defer="form.is_active">
                            <span class="form-check-label">Aktif</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <button type="button" class="btn btn-outline-secondary me-2" wire:click="cancelForm">Batal</button>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled"><i class="fas fa-save me-1"></i>Simpan</button>
            </div>
        </form>
    @endif

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="card assignment-card h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-clock me-2 text-primary"></i>Slot Mingguan</h3>
                    <span class="assignment-pill">{{ count($consultationSlots) }} slot</span>
                </div>
                <div class="card-body p-4">
                    <div class="assignment-shell">
                        @forelse ($consultationSlots as $slot)
                            <div class="assignment-list-item">
                                <div class="d-flex justify-content-between gap-3">
                                    <div class="d-flex gap-3">
                                        <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-calendar-day"></i></span>
                                        <div>
                                            <div class="d-flex flex-wrap gap-2 mb-2">
                                                <span class="assignment-pill" style="{{ $slot['is_active'] ? 'background:#dcfce7;color:#15803d;' : 'background:#f1f5f9;color:#64748b;' }}">{{ $slot['is_active'] ? 'Aktif' : 'Nonaktif' }}</span>
                                                <span class="assignment-pill"><i class="fas fa-user-clock"></i>{{ $slot['upcoming_count'] }} upcoming</span>
                                            </div>
                                            <div class="fw-bold">{{ $slot['title'] }}</div>
                                            <div class="text-secondary small">{{ $slot['weekday'] }}, {{ $slot['time'] }} &middot; {{ $slot['mode'] }}</div>
                                            <div class="text-secondary small">{{ $slot['location'] }} &middot; {{ $slot['slot_minutes'] }} menit &middot; {{ $slot['capacity'] }} sesi</div>
                                        </div>
                                    </div>
                                    <div class="btn-list flex-nowrap">
                                        <button type="button" class="btn btn-light" wire:click="editConsultationSlot({{ $slot['id'] }})"><i class="fas fa-pen"></i></button>
                                        <button type="button" class="btn btn-light" wire:click="toggleConsultationSlot({{ $slot['id'] }})"><i class="fas fa-power-off"></i></button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-secondary py-5">
                                <i class="fas fa-calendar-days fa-3x mb-3"></i>
                                <div>Belum ada slot konsultasi.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card assignment-card">
                <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-list-check me-2 text-primary"></i>Appointment Mahasiswa</h3>
                    <span class="assignment-pill">{{ count($appointments) }} appointment</span>
                </div>
                <div class="card-body p-4">
                    <div class="assignment-shell">
                        @forelse ($appointments as $appointment)
                            <div class="assignment-list-item">
                                <div class="row g-3 align-items-start">
                                    <div class="col-xl-7">
                                        <div class="d-flex gap-3">
                                            <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-user-graduate"></i></span>
                                            <div>
                                                <div class="d-flex flex-wrap gap-2 mb-2">
                                                    <span class="assignment-pill" style="{{ $this->statusStyle($appointment['status']) }}"><i class="fas fa-circle-info"></i>{{ $appointment['status_label'] }}</span>
                                                    <span class="assignment-pill"><i class="fas fa-clock"></i>{{ $appointment['schedule'] }}</span>
                                                </div>
                                                <div class="fw-bold">{{ $appointment['student'] }} <span class="text-secondary">({{ $appointment['nim'] }})</span></div>
                                                <div class="text-secondary small">{{ $appointment['study_program'] }}</div>
                                                <div class="assignment-panel mt-3">
                                                    <div class="text-secondary small mb-1">Topik</div>
                                                    <div class="fw-bold">{{ $appointment['topic'] }}</div>
                                                    @if ($appointment['student_notes'])
                                                        <div class="text-secondary small mt-2">{{ $appointment['student_notes'] }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-5">
                                        <div class="assignment-panel">
                                            <div class="text-secondary small mb-1">Catatan dosen</div>
                                            <textarea rows="3" class="form-control" wire:model.defer="appointmentNotes.{{ $appointment['id'] }}"></textarea>
                                            <div class="d-flex justify-content-end gap-2 flex-wrap mt-3">
                                                <button type="button" class="btn btn-outline-secondary" wire:click="saveAppointmentNote({{ $appointment['id'] }})"><i class="fas fa-save me-1"></i>Simpan</button>
                                                @if ($appointment['status'] === 'requested')
                                                    <button type="button" class="btn btn-primary" wire:click="setAppointmentStatus({{ $appointment['id'] }}, 'confirmed')">Konfirmasi</button>
                                                    <button type="button" class="btn btn-outline-danger" wire:click="setAppointmentStatus({{ $appointment['id'] }}, 'rejected')">Tolak</button>
                                                @endif
                                                @if (in_array($appointment['status'], ['requested', 'confirmed', 'waitlisted'], true))
                                                    <button type="button" class="btn btn-success" wire:click="setAppointmentStatus({{ $appointment['id'] }}, 'completed')">Selesai</button>
                                                    <button type="button" class="btn btn-outline-warning" wire:click="setAppointmentStatus({{ $appointment['id'] }}, 'no_show')">Tidak Hadir</button>
                                                    <button type="button" class="btn btn-outline-secondary" wire:click="setAppointmentStatus({{ $appointment['id'] }}, 'cancelled')">Batalkan</button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-secondary py-5">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <div>Belum ada appointment pada filter ini.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
