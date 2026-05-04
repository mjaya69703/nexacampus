<?php

use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\CourseSchedule;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public $courseOfferingId = '';
    public $lecturerProfileId = '';
    public $room = '';
    public $building = '';
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
    public array $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function mount(): void
    {
        $this->courseOfferings = CourseOffering::with('course', 'academicYear')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($offering) => [
                'id' => $offering->id,
                'label' => ($offering->course?->code ?? '-') . ' - ' . ($offering->course?->name ?? '-') . ' / ' . ($offering->label ?? '-'),
            ])
            ->toArray();
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

    public function save(): void
    {
        $this->validate([
            'courseOfferingId' => 'required|exists:course_offerings,id',
            'lecturerProfileId' => [
                'nullable',
                Rule::exists('course_offering_lecturers', 'lecturer_profile_id')
                    ->where('course_offering_id', $this->courseOfferingId),
            ],
            'room' => 'nullable|string|max:100',
            'building' => 'nullable|string|max:100',
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
            CourseSchedule::create([
                'course_offering_id' => $this->courseOfferingId,
                'lecturer_profile_id' => $this->lecturerProfileId ?: null,
                'room' => $this->room ?: null,
                'building' => $this->building ?: null,
                'day_of_week' => $this->dayOfWeek,
                'start_time' => $this->startTime,
                'end_time' => $this->endTime,
                'session_type' => $this->sessionType,
                'delivery_mode' => $this->deliveryMode,
                'meeting_link' => $this->meetingLink ?: null,
                'notes' => $this->notes ?: null,
                'is_active' => $this->isActive,
                'created_by' => auth()->id(),
            ]);

            session()->flash('success', 'Jadwal kuliah berhasil dibuat!');
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
                'pages' => 'Buat Jadwal Kuliah Baru',
            ]);
    }
};
?>

<div class="row">
    <div class="col-12">
        <x-alert />

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Form Jadwal Kuliah</h5>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Course Offering <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="courseOfferingId" required>
                                <option value="">Pilih Course Offering</option>
                                @foreach($courseOfferings as $offering)
                                    <option value="{{ $offering['id'] }}">{{ $offering['label'] }}</option>
                                @endforeach
                            </select>
                            @error('courseOfferingId') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Dosen</label>
                            <select class="form-select" wire:model="lecturerProfileId">
                                <option value="">Jadwal Umum (Tanpa Dosen)</option>
                                @foreach($lecturers as $lecturer)
                                    <option value="{{ $lecturer['id'] }}">{{ $lecturer['label'] }}</option>
                                @endforeach
                            </select>
                            @error('lecturerProfileId') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Hari <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="dayOfWeek" required>
                                @foreach($days as $day)
                                    <option value="{{ $day }}">{{ $day }}</option>
                                @endforeach
                            </select>
                            @error('dayOfWeek') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-2 mb-3">
                            <label class="form-label">Mulai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" wire:model="startTime" required>
                            @error('startTime') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-2 mb-3">
                            <label class="form-label">Selesai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" wire:model="endTime" required>
                            @error('endTime') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Ruangan</label>
                            <input type="text" class="form-control" wire:model="room" placeholder="R101">
                            @error('room') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gedung</label>
                            <input type="text" class="form-control" wire:model="building" placeholder="Gedung A">
                            @error('building') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tipe Sesi <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="sessionType" required>
                                <option value="Lecture">Lecture</option>
                                <option value="Practicum">Practicum</option>
                                <option value="Tutorial">Tutorial</option>
                                <option value="Exam">Exam</option>
                                <option value="Custom">Custom</option>
                            </select>
                            @error('sessionType') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Mode Pengiriman <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="deliveryMode" required>
                                <option value="Offline">Offline</option>
                                <option value="Online">Online</option>
                                <option value="Hybrid">Hybrid</option>
                            </select>
                            @error('deliveryMode') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-8 mb-3">
                            <label class="form-label">Meeting Link</label>
                            <input type="url" class="form-control" wire:model="meetingLink" placeholder="https://...">
                            @error('meetingLink') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" wire:model="isActive" checked>
                                <span class="form-check-label">Aktif</span>
                            </label>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Catatan</label>
                            <textarea class="form-control" rows="2" wire:model="notes"></textarea>
                            @error('notes') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Simpan Jadwal
                            </button>
                            <button type="button" class="btn btn-secondary" wire:click="cancel">
                                <i class="fas fa-times me-1"></i> Batal
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
