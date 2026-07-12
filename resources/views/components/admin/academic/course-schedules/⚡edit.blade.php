<?php

use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\CourseSchedule;
use App\Models\Campus\Room;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public CourseSchedule $schedule;

    public $courseOfferingId = '';
    public $lecturerProfileId = '';
    public $roomId = null;
    public $dayOfWeek = 'Monday';
    public $startTime = '';
    public $endTime = '';
    public $sessionType = 'Lecture';
    public $deliveryMode = 'Offline';
    public $meetingLink = '';
    public $notes = '';
    public $isActive = true;

    public array $courseOfferings = [];
    public array $lecturers = [];
    public array $rooms = [];
    public array $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function mount($id): void
    {
        $this->schedule = CourseSchedule::findOrFail($id);

        $this->courseOfferingId = $this->schedule->course_offering_id;
        $this->lecturerProfileId = $this->schedule->lecturer_profile_id;
        $this->roomId = $this->schedule->room_id;
        $this->dayOfWeek = $this->schedule->day_of_week;
        $this->startTime = $this->schedule->start_time?->format('H:i');
        $this->endTime = $this->schedule->end_time?->format('H:i');
        $this->sessionType = $this->schedule->session_type;
        $this->deliveryMode = $this->schedule->delivery_mode;
        $this->meetingLink = $this->schedule->meeting_link;
        $this->notes = $this->schedule->notes;
        $this->isActive = $this->schedule->is_active;

        $this->courseOfferings = CourseOffering::with('course', 'academicYear')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($offering) => [
                'id' => $offering->id,
                'label' => ($offering->course?->code ?? '-') . ' - ' . ($offering->course?->name ?? '-') . ' / ' . ($offering->label ?? '-'),
            ])
            ->toArray();

        $this->rooms = Room::query()
            ->with('building')
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'label' => trim(
                    ($room->building?->name ? $room->building->name.' - ' : '')
                    .$room->name
                    .($room->code ? ' ('.$room->code.')' : '')
                ),
            ])
            ->toArray();

        $this->loadLecturersForOffering();
    }

    public function updatedCourseOfferingId(): void
    {
        $this->loadLecturersForOffering();
    }

    public function loadLecturersForOffering(): void
    {
        if (! $this->courseOfferingId) {
            $this->lecturers = [];
            $this->lecturerProfileId = '';

            return;
        }

        $this->lecturers = CourseOfferingLecturer::query()
            ->where('course_offering_id', $this->courseOfferingId)
            ->with('lecturerProfile.user')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($l) => [
                'id' => $l->lecturer_profile_id,
                'label' => $l->lecturerProfile?->user?->name ?? '-',
            ])
            ->toArray();
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.course-schedules.index');
    }

    public function update(): void
    {
        $this->validate([
            'courseOfferingId' => 'required|exists:course_offerings,id',
            'lecturerProfileId' => [
                'nullable',
                Rule::exists('course_offering_lecturers', 'lecturer_profile_id')
                    ->where('course_offering_id', $this->courseOfferingId),
            ],
            'roomId' => 'nullable|exists:rooms,id',
            'dayOfWeek' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'startTime' => 'required|date_format:H:i',
            'endTime' => 'required|date_format:H:i|after:startTime',
            'sessionType' => 'required|in:Lecture,Practicum,Tutorial,Exam,Custom',
            'deliveryMode' => 'required|in:Offline,Online,Hybrid',
            'meetingLink' => 'nullable|url',
            'notes' => 'nullable|string',
            'isActive' => 'nullable|boolean',
        ], [
            'lecturerProfileId.exists' => 'Dosen yang dipilih harus terdaftar di course offering ini.',
            'endTime.after' => 'Waktu akhir harus lebih lambat dari waktu mulai.',
        ]);

        try {
            $this->schedule->update([
                'course_offering_id' => $this->courseOfferingId,
                'lecturer_profile_id' => $this->lecturerProfileId ?: null,
                'room_id' => $this->roomId ?: null,
                'day_of_week' => $this->dayOfWeek,
                'start_time' => $this->startTime,
                'end_time' => $this->endTime,
                'session_type' => $this->sessionType,
                'delivery_mode' => $this->deliveryMode,
                'meeting_link' => $this->meetingLink ?: null,
                'notes' => $this->notes ?: null,
                'is_active' => $this->isActive,
                'updated_by' => auth()->id(),
            ]);

            session()->flash('success', 'Jadwal kuliah berhasil diperbarui!');
            $this->redirectRoute('admin.academic.course-schedules.index');
        } catch (\Throwable $th) {
            session()->flash('error', 'Terjadi kesalahan: ' . $th->getMessage());
        }
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Edit Jadwal Kuliah',
            ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Edit Jadwal Kuliah"
        description="Perbarui jadwal pertemuan kuliah, jam perkuliahan, alokasi ruangan, atau dosen pengampu."
        icon="calendar-check"
    >
        <button type="button" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2" wire:click="cancel">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </button>
    </x-admin.academic.header>

    <form wire:submit.prevent="update">
        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-clock fs-5"></i>
                            </div>
                            <div>
                                <h4 class="card-title fw-bold mb-1 text-dark">Informasi Penawaran & Waktu Kuliah</h4>
                                <div class="text-muted small">Perbarui kelas penawaran, dosen pengampu, hari, waktu mulai dan selesai perkuliahan.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Kelas Penawaran (Course Offering) <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model="courseOfferingId" required>
                                    <option value="">Pilih Course Offering</option>
                                    @foreach($courseOfferings as $offering)
                                        <option value="{{ $offering['id'] }}">{{ $offering['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('courseOfferingId') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Dosen Pengampu</label>
                                <select class="form-select" wire:model="lecturerProfileId">
                                    <option value="">Jadwal Umum (Tanpa Dosen)</option>
                                    @foreach($lecturers as $lecturer)
                                        <option value="{{ $lecturer['id'] }}">{{ $lecturer['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('lecturerProfileId') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Hari Perkuliahan <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model="dayOfWeek" required>
                                    @foreach($days as $day)
                                        <option value="{{ $day }}">{{ $day }}</option>
                                    @endforeach
                                </select>
                                @error('dayOfWeek') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Jam Mulai <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" wire:model="startTime" required>
                                @error('startTime') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Jam Selesai <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" wire:model="endTime" required>
                                @error('endTime') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Ruang Kelas (Lokasi)</label>
                                <select class="form-select" wire:model="roomId">
                                    <option value="">Pilih Ruangan (Opsional jika Online)</option>
                                    @foreach($rooms as $room)
                                        <option value="{{ $room['id'] }}">{{ $room['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('roomId') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Tautan Pertemuan Online / Meeting Link (Opsional)</label>
                                <input type="url" class="form-control" wire:model="meetingLink" placeholder="https://zoom.us/j/...">
                                @error('meetingLink') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Catatan Tambahan</label>
                                <textarea class="form-control" rows="2" wire:model="notes" placeholder="Catatan jadwal atau instruksi ruangan..."></textarea>
                                @error('notes') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-sliders fs-5"></i>
                            </div>
                            <div>
                                <h5 class="card-title fw-bold mb-1 text-dark">Pengaturan Sesi & Status</h5>
                                <div class="text-muted small">Atur tipe sesi dan metode perkuliahan.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Tipe Sesi Perkuliahan <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model="sessionType" required>
                                    <option value="Lecture">Lecture (Kuliah Teori)</option>
                                    <option value="Practicum">Practicum (Praktikum)</option>
                                    <option value="Tutorial">Tutorial (Responsi)</option>
                                    <option value="Exam">Exam (Ujian)</option>
                                    <option value="Custom">Custom</option>
                                </select>
                                @error('sessionType') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Mode Perkuliahan <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model="deliveryMode" required>
                                    <option value="Offline">Offline (Tatap Muka)</option>
                                    <option value="Online">Online (Daring)</option>
                                    <option value="Hybrid">Hybrid (Campuran)</option>
                                </select>
                                @error('deliveryMode') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12 border-top pt-3 mt-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="isActive" wire:model="isActive">
                                    <label class="form-check-label fw-semibold" for="isActive">Jadwal Kuliah Aktif</label>
                                </div>
                                @error('isActive') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12 border-top pt-3 mt-3 d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-light rounded-pill px-3 py-2" wire:click="cancel">
                                    <i class="fa fa-times me-1"></i> Batal
                                </button>
                                <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm">
                                    <i class="fa fa-save me-1"></i> Simpan Perubahan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
