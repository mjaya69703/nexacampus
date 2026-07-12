<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudyProgram;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\CourseSchedule;
use App\Models\Academic\LecturerProfile;
use App\Support\GenerateAttendanceSessionsService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public int $courseOfferingId;
    public array $courseOfferingForm = [];
    public array $availableAcademicYears = [];
    public array $availableStudyPrograms = [];
    public array $availableCourses = [];
    public array $availableLecturers = [];
    public array $schedulesData = [];
    public array $generationMessage = [];

    // Lecturer form state
    public $lecturerFormMode = 'add'; // add or edit
    public $selectedLecturerId = null; // lecturer_profile_id for add mode, lecturer record ID for form state
    public $editingLecturerRecordId = null;
    public $editingLecturerName = null;
    public $lecturerRole = 'Primary';
    public $lecturerSortOrder = 0;
    public $lecturerNotes = '';
    public $lecturerIsActive = true;
    public array $lecturersData = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.course-offerings.index');
    }

    public function mount($id): void
    {
        $offering = CourseOffering::findOrFail($id);
        $this->courseOfferingId = (int) $id;

        $this->courseOfferingForm = [
            'academic_year_id' => $offering->academic_year_id,
            'study_program_id' => $offering->study_program_id,
            'curriculum_id' => $offering->curriculum_id,
            'course_id' => $offering->course_id,
            'label' => $offering->label,
            'code' => $offering->code,
            'semester_no' => $offering->semester_no,
            'capacity' => $offering->capacity,
            'credits' => $offering->credits,
            'total_meetings' => $offering->total_meetings,
            'class_start_date' => $offering->class_start_date?->format('Y-m-d'),
            'class_end_date' => $offering->class_end_date?->format('Y-m-d'),
            'is_required' => (bool) $offering->is_required,
            'delivery_mode' => $offering->delivery_mode,
            'status' => $offering->status,
            'notes' => $offering->notes,
        ];

        $this->availableAcademicYears = AcademicYear::orderByDesc('start_date')->get(['id', 'name'])->toArray();
        $this->availableStudyPrograms = StudyProgram::orderBy('name')->get(['id', 'name'])->toArray();
        $this->availableCourses = Course::orderBy('code')->get(['id', 'code', 'name'])->toArray();
        $this->availableLecturers = LecturerProfile::with('user')->orderBy('id')->get(['id', 'user_id'])
            ->map(fn($l) => ['id' => $l->id, 'name' => $l->user?->name])
            ->toArray();

        $this->loadSchedulesData();
        $this->loadLecturersData();
    }

    public function loadSchedulesData(): void
    {
        $daysMap = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ];

        $this->schedulesData = CourseSchedule::query()
            ->where('course_offering_id', $this->courseOfferingId)
            ->with(['lecturerProfile.user', 'room.building'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(fn ($schedule) => [
                'id' => $schedule->id,
                'day_name' => $daysMap[$schedule->day_of_week] ?? $schedule->day_of_week,
                'start_time' => $schedule->start_time?->format('H:i') ?? '-',
                'end_time' => $schedule->end_time?->format('H:i') ?? '-',
                'lecturer_name' => $schedule->lecturerProfile?->user?->name ?? '-',
                'room' => $schedule->room?->name,
                'building' => $schedule->room?->building?->name,
            ])
            ->toArray();
    }

    public function generateSessions(): void
    {
        $validatedData = $this->validate([
            'courseOfferingForm.total_meetings' => 'required|integer|min:1|max:32',
            'courseOfferingForm.class_start_date' => 'required|date',
            'courseOfferingForm.class_end_date' => 'required|date|after_or_equal:courseOfferingForm.class_start_date',
        ]);

        try {
            DB::beginTransaction();

            $offering = CourseOffering::query()->findOrFail($this->courseOfferingId);
            $offering->update([
                'total_meetings' => $validatedData['courseOfferingForm']['total_meetings'],
                'class_start_date' => $validatedData['courseOfferingForm']['class_start_date'],
                'class_end_date' => $validatedData['courseOfferingForm']['class_end_date'],
                'updated_by' => auth()->id(),
            ]);

            $generatedCount = app(GenerateAttendanceSessionsService::class)->generate($offering);

            DB::commit();

            $this->generationMessage = [
                'type' => 'success',
                'text' => "{$generatedCount} sesi absensi berhasil di-generate. Session tetap bisa diedit manual setelah ini.",
            ];

            session()->flash('success', $this->generationMessage['text']);
        } catch (\Throwable $throwable) {
            DB::rollBack();
            $this->generationMessage = [
                'type' => 'danger',
                'text' => 'Gagal generate sesi absensi: '.$throwable->getMessage(),
            ];
            session()->flash('error', $this->generationMessage['text']);
        }
    }

    public function loadLecturersData(): void
    {
        $this->lecturersData = CourseOfferingLecturer::where('course_offering_id', $this->courseOfferingId)
            ->with('lecturerProfile.user')
            ->orderBy('sort_order')
            ->get()
            ->map(fn($l) => [
                'id' => $l->id,
                'lecturer_profile_id' => $l->lecturer_profile_id,
                'lecturer_name' => $l->lecturerProfile?->user?->name,
                'role' => $l->role,
                'sort_order' => $l->sort_order,
                'notes' => $l->notes,
                'is_active' => $l->is_active,
            ])
            ->toArray();
    }

    public function addLecturerForm(): void
    {
        $this->lecturerFormMode = 'add';
        $this->resetLecturerForm();
    }

    public function editLecturerForm($lecturerId): void
    {
        $lecturer = CourseOfferingLecturer::findOrFail($lecturerId);
        $this->lecturerFormMode = 'edit';
        $this->editingLecturerRecordId = $lecturerId;
        $this->editingLecturerName = $lecturer->lecturerProfile?->user?->name;
        $this->lecturerRole = $lecturer->role;
        $this->lecturerSortOrder = $lecturer->sort_order;
        $this->lecturerNotes = $lecturer->notes;
        $this->lecturerIsActive = (bool) $lecturer->is_active;
    }

    public function saveLecturer(): void
    {
        $this->validate([
            'selectedLecturerId' => 'nullable|required_if:lecturerFormMode,add|exists:lecturer_profiles,id',
            'lecturerRole' => 'required|in:Coordinator,Primary,Secondary,Assistant',
            'lecturerSortOrder' => 'nullable|integer|min:0',
            'lecturerNotes' => 'nullable|string',
        ]);

        if ($this->lecturerFormMode === 'add') {
            CourseOfferingLecturer::create([
                'course_offering_id' => $this->courseOfferingId,
                'lecturer_profile_id' => $this->selectedLecturerId,
                'role' => $this->lecturerRole,
                'sort_order' => $this->lecturerSortOrder,
                'notes' => $this->lecturerNotes ?: null,
                'is_active' => $this->lecturerIsActive,
                'created_by' => auth()->id(),
            ]);
        } else {
            $lecturer = CourseOfferingLecturer::findOrFail($this->editingLecturerRecordId);
            $lecturer->update([
                'role' => $this->lecturerRole,
                'sort_order' => $this->lecturerSortOrder,
                'notes' => $this->lecturerNotes ?: null,
                'is_active' => $this->lecturerIsActive,
                'updated_by' => auth()->id(),
            ]);
        }

        $this->loadLecturersData();
        $this->resetLecturerForm();
    }

    public function deleteLecturer($lecturerId): void
    {
        $lecturer = CourseOfferingLecturer::findOrFail($lecturerId);
        $lecturerName = $lecturer->lecturerProfile?->user?->name;

        $this->js("
            Swal.fire({
                title: 'Hapus dosen?',
                text: '$lecturerName akan dihapus dari course offering ini!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch('confirmedDeleteLecturer', {lecturerId: $lecturerId})
                }
            });
        ");
    }

    #[\Livewire\Attributes\On('confirmedDeleteLecturer')]
    public function confirmedDeleteLecturer($lecturerId): void
    {
        $lecturer = CourseOfferingLecturer::findOrFail($lecturerId);
        $lecturer->delete();
        $this->loadLecturersData();
    }

    public function resetLecturerForm(): void
    {
        $this->lecturerFormMode = 'add';
        $this->selectedLecturerId = null;
        $this->editingLecturerRecordId = null;
        $this->editingLecturerName = null;
        $this->lecturerRole = 'Primary';
        $this->lecturerSortOrder = 0;
        $this->lecturerNotes = '';
        $this->lecturerIsActive = true;
    }

    public function updateCourseOffering(): void
    {
        $validatedData = $this->validate([
            'courseOfferingForm.academic_year_id' => 'required|integer|exists:academic_years,id',
            'courseOfferingForm.study_program_id' => 'required|integer|exists:study_programs,id',
            'courseOfferingForm.curriculum_id' => 'nullable|integer|exists:curriculums,id',
            'courseOfferingForm.course_id' => 'required|integer|exists:courses,id',
            'courseOfferingForm.label' => 'nullable|string|max:255',
            'courseOfferingForm.code' => 'nullable|string|max:100',
            'courseOfferingForm.semester_no' => 'nullable|integer|min:1|max:14',
            'courseOfferingForm.capacity' => 'nullable|integer|min:1',
            'courseOfferingForm.credits' => 'nullable|integer|min:1|max:24',
            'courseOfferingForm.total_meetings' => 'nullable|integer|min:1|max:32',
            'courseOfferingForm.class_start_date' => 'nullable|date',
            'courseOfferingForm.class_end_date' => 'nullable|date|after_or_equal:courseOfferingForm.class_start_date',
            'courseOfferingForm.is_required' => 'boolean',
            'courseOfferingForm.delivery_mode' => 'required|in:Offline,Online,Hybrid',
            'courseOfferingForm.status' => 'required|in:Draft,Open,Closed,Cancelled',
            'courseOfferingForm.notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $offering = CourseOffering::findOrFail($this->courseOfferingId);

            $offering->update([
                'academic_year_id' => $validatedData['courseOfferingForm']['academic_year_id'],
                'study_program_id' => $validatedData['courseOfferingForm']['study_program_id'],
                'curriculum_id' => $validatedData['courseOfferingForm']['curriculum_id'] ?: null,
                'course_id' => $validatedData['courseOfferingForm']['course_id'],
                'label' => $validatedData['courseOfferingForm']['label'] ?: null,
                'code' => $validatedData['courseOfferingForm']['code'] ?: null,
                'semester_no' => $validatedData['courseOfferingForm']['semester_no'] ?: null,
                'capacity' => $validatedData['courseOfferingForm']['capacity'] ?: null,
                'credits' => $validatedData['courseOfferingForm']['credits'] ?: null,
                'total_meetings' => $validatedData['courseOfferingForm']['total_meetings'] ?: null,
                'class_start_date' => $validatedData['courseOfferingForm']['class_start_date'] ?: null,
                'class_end_date' => $validatedData['courseOfferingForm']['class_end_date'] ?: null,
                'is_required' => (bool) $validatedData['courseOfferingForm']['is_required'],
                'delivery_mode' => $validatedData['courseOfferingForm']['delivery_mode'],
                'status' => $validatedData['courseOfferingForm']['status'],
                'notes' => $validatedData['courseOfferingForm']['notes'] ?: null,
                'updated_by' => auth()->id(),
            ]);

            DB::commit();
            session()->flash('success', 'Course offering berhasil diperbarui.');
            $this->loadLecturersData();
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Edit Course Offering',
            ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Edit Kelas Penawaran: {{ $courseOfferingForm['code'] ?? '' }} {{ $courseOfferingForm['label'] ?? '' }}"
        description="Perbarui informasi kelas perkuliahan, kelola jadwal pertemuan, generate sesi absensi, serta penugasan dosen pengampu."
        icon="layer-group"
    >
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.academic.course-offerings.show', ['id' => $courseOfferingId]) }}" class="btn btn-sm btn-light text-primary fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2">
                <i class="fa fa-eye"></i> <span>Lihat Detail</span>
            </a>
            <button type="button" class="btn btn-sm btn-light text-dark fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2" wire:click="cancel">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </button>
        </div>
    </x-admin.academic.header>

    <div class="row g-4 align-items-start">
        <!-- Left Column: Main Detail & Schedule/Generate -->
        <div class="col-lg-8">
            <!-- Section A: Course Offering Form -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-layer-group fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Informasi & Jadwal Kelas</h4>
                            <div class="text-muted small">Perbarui mata kuliah, tahun akademik, program studi, kapasitas, dan rentang tanggal pertemuan.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form wire:submit.prevent="updateCourseOffering">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Tahun Akademik <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model.defer="courseOfferingForm.academic_year_id">
                                    <option value="">Pilih Tahun Akademik</option>
                                    @foreach ($availableAcademicYears as $year)
                                        <option value="{{ $year['id'] }}">{{ $year['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('courseOfferingForm.academic_year_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Program Studi <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model.defer="courseOfferingForm.study_program_id">
                                    <option value="">Pilih Program Studi</option>
                                    @foreach ($availableStudyPrograms as $program)
                                        <option value="{{ $program['id'] }}">{{ $program['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('courseOfferingForm.study_program_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Mata Kuliah <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model.defer="courseOfferingForm.course_id">
                                    <option value="">Pilih Mata Kuliah</option>
                                    @foreach ($availableCourses as $course)
                                        <option value="{{ $course['id'] }}">{{ $course['code'] }} - {{ $course['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('courseOfferingForm.course_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Label (Kelas)</label>
                                <input type="text" class="form-control" wire:model.defer="courseOfferingForm.label" placeholder="Contoh: Reguler A">
                                @error('courseOfferingForm.label') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Kode Penawaran</label>
                                <input type="text" class="form-control" wire:model.defer="courseOfferingForm.code" placeholder="Contoh: OFF-IF101">
                                @error('courseOfferingForm.code') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Semester</label>
                                <input type="number" min="1" max="14" class="form-control" wire:model.defer="courseOfferingForm.semester_no">
                                @error('courseOfferingForm.semester_no') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Kapasitas</label>
                                <input type="number" min="1" class="form-control" wire:model.defer="courseOfferingForm.capacity">
                                @error('courseOfferingForm.capacity') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Bobot SKS</label>
                                <input type="number" min="1" max="24" class="form-control" wire:model.defer="courseOfferingForm.credits">
                                @error('courseOfferingForm.credits') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Total Pertemuan</label>
                                <input type="number" min="1" max="32" class="form-control" wire:model.defer="courseOfferingForm.total_meetings">
                                @error('courseOfferingForm.total_meetings') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Tanggal Mulai Kelas</label>
                                <input type="date" class="form-control" wire:model.defer="courseOfferingForm.class_start_date">
                                @error('courseOfferingForm.class_start_date') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Tanggal Akhir Kelas</label>
                                <input type="date" class="form-control" wire:model.defer="courseOfferingForm.class_end_date">
                                @error('courseOfferingForm.class_end_date') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Mode Perkuliahan</label>
                                <select class="form-select" wire:model.defer="courseOfferingForm.delivery_mode">
                                    <option value="Offline">Offline</option>
                                    <option value="Online">Online</option>
                                    <option value="Hybrid">Hybrid</option>
                                </select>
                                @error('courseOfferingForm.delivery_mode') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status Kelas</label>
                                <select class="form-select" wire:model.defer="courseOfferingForm.status">
                                    <option value="Draft">Draft</option>
                                    <option value="Open">Open</option>
                                    <option value="Closed">Closed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                                @error('courseOfferingForm.status') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12 border-top pt-3 mt-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" id="is_required" type="checkbox" wire:model.defer="courseOfferingForm.is_required">
                                    <label class="form-check-label fw-semibold" for="is_required">Wajib Diambil Mahasiswa</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Catatan</label>
                                <textarea class="form-control" rows="2" wire:model.defer="courseOfferingForm.notes" placeholder="Catatan kelas..."></textarea>
                                @error('courseOfferingForm.notes') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12 border-top pt-3 mt-3 d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-light rounded-pill px-3 py-2" wire:click="cancel">
                                    Batal
                                </button>
                                <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm">
                                    <i class="fas fa-save me-1"></i> Simpan Perubahan
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Section B: Schedules & Session Generation -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-calendar-alt fs-5"></i>
                        </div>
                        <div>
                            <h5 class="card-title fw-bold mb-1 text-dark">Jadwal & Generate Sesi Absensi</h5>
                            <div class="text-muted small">Daftar jadwal pertemuan mingguan dan tombol otomatisasi pembuatan sesi absensi.</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-info rounded-pill px-3 shadow-sm text-white fw-semibold d-inline-flex align-items-center gap-2" wire:click="generateSessions">
                        <i class="fa fa-bolt"></i> <span>Generate Sesi</span>
                    </button>
                </div>
                <div class="card-body p-4">
                    @if (!empty($generationMessage))
                        <div class="alert alert-{{ $generationMessage['type'] }} mb-3">
                            {{ $generationMessage['text'] }}
                        </div>
                    @endif

                    @if (count($schedulesData) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Hari</th>
                                        <th>Waktu</th>
                                        <th>Dosen Pengampu</th>
                                        <th>Ruang & Gedung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($schedulesData as $schedule)
                                        <tr>
                                            <td class="fw-semibold">{{ $schedule['day_name'] }}</td>
                                            <td><span class="badge bg-light text-dark border">{{ $schedule['start_time'] }} - {{ $schedule['end_time'] }}</span></td>
                                            <td>{{ $schedule['lecturer_name'] }}</td>
                                            <td>{{ trim(($schedule['building'] ?? '') . ' ' . ($schedule['room'] ?? '')) ?: '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-warning mb-0 border-0 bg-warning bg-opacity-10 text-warning-emphasis d-flex align-items-center gap-2">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Belum ada jadwal perkuliahan. Atur jadwal pada menu Course Schedules sebelum melakukan generate sesi absensi.</span>
                        </div>
                    @endif
                    <small class="d-block text-muted mt-3"><i class="fas fa-info-circle me-1"></i> Sesi absensi tetap dapat diedit secara manual setelah di-generate.</small>
                </div>
            </div>
        </div>

        <!-- Right Column: Lecturers Management -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-circle-info"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Pedoman Pengaturan</h5>
                            <div class="text-muted small">Panduan operasional kelas.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Periksa <strong>Dosen Pengampu</strong> dan pastikan koordinator mata kuliah telah ditentukan.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pastikan jadwal pertemuan mingguan telah terisi sebelum menekan tombol <strong>Generate Sesi</strong>.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Perubahan jumlah pertemuan setelah generate sesi tidak akan menghapus sesi lama yang sudah berjalan.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-chalkboard-teacher fs-5"></i>
                        </div>
                        <div>
                            <h5 class="card-title fw-bold mb-0 text-dark">Dosen Pengampu</h5>
                            <div class="text-muted small">Penugasan dosen kelas.</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm fw-semibold d-inline-flex align-items-center gap-1" wire:click="addLecturerForm">
                        <i class="fa fa-plus"></i> <span>Tambah</span>
                    </button>
                </div>
                <div class="card-body p-4">
                    @if (count($lecturersData) > 0)
                        <div class="d-flex flex-column gap-3 mb-4">
                            @foreach ($lecturersData as $index => $lecturer)
                                <div class="p-3 rounded-3 border bg-light bg-opacity-50 d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold">{{ $lecturer['lecturer_name'] }}</div>
                                        <div class="d-flex align-items-center gap-2 mt-1">
                                            <span class="badge bg-primary bg-opacity-10 text-primary">{{ $lecturer['role'] }}</span>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary">Urutan: {{ $lecturer['sort_order'] }}</span>
                                            @if ($lecturer['is_active'])
                                                <span class="badge bg-success bg-opacity-10 text-success">Aktif</span>
                                            @else
                                                <span class="badge bg-danger bg-opacity-10 text-danger">Nonaktif</span>
                                            @endif
                                        </div>
                                        @if($lecturer['notes'])
                                            <div class="small text-muted mt-1">{{ $lecturer['notes'] }}</div>
                                        @endif
                                    </div>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-light border text-warning" wire:click="editLecturerForm({{ $lecturer['id'] }})" title="Edit Dosen">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-light border text-danger" wire:click="deleteLecturer({{ $lecturer['id'] }})" title="Hapus Dosen">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="alert alert-info border-0 bg-info bg-opacity-10 text-info-emphasis mb-4">
                            <i class="fas fa-info-circle me-2"></i> Belum ada dosen yang ditugaskan pada kelas ini.
                        </div>
                    @endif

                    @if ($lecturerFormMode)
                        <div class="p-3 rounded-4 border bg-white shadow-sm mt-3">
                            <h6 class="fw-bold border-bottom pb-2 mb-3">{{ $lecturerFormMode === 'add' ? 'Tambah Dosen Baru' : 'Edit Penugasan Dosen' }}</h6>
                            <div class="row g-2">
                                @if ($lecturerFormMode === 'add')
                                    <div class="col-12 mb-2">
                                        <label class="form-label small fw-semibold">Pilih Dosen <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" wire:model="selectedLecturerId">
                                            <option value="">Pilih Dosen</option>
                                            @foreach ($availableLecturers as $lecturer)
                                                <option value="{{ $lecturer['id'] }}">{{ $lecturer['name'] }}</option>
                                            @endforeach
                                        </select>
                                        @error('selectedLecturerId') <span class="text-danger small">{{ $message }}</span> @enderror
                                    </div>
                                @else
                                    <div class="col-12 mb-2">
                                        <label class="form-label small fw-semibold">Nama Dosen</label>
                                        <input type="text" class="form-control form-control-sm bg-light" value="{{ $editingLecturerName }}" readonly>
                                    </div>
                                @endif

                                <div class="col-6 mb-2">
                                    <label class="form-label small fw-semibold">Peran (Role) <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" wire:model="lecturerRole">
                                        <option value="Coordinator">Coordinator</option>
                                        <option value="Primary">Primary</option>
                                        <option value="Secondary">Secondary</option>
                                        <option value="Assistant">Assistant</option>
                                    </select>
                                    @error('lecturerRole') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-6 mb-2">
                                    <label class="form-label small fw-semibold">Urutan Tampil</label>
                                    <input type="number" min="0" class="form-control form-control-sm" wire:model="lecturerSortOrder">
                                    @error('lecturerSortOrder') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-12 mb-2">
                                    <label class="form-label small fw-semibold">Catatan Tambahan</label>
                                    <textarea class="form-control form-control-sm" rows="2" wire:model="lecturerNotes" placeholder="Opsional..."></textarea>
                                    @error('lecturerNotes') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-12 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="lecturerIsActive" wire:model="lecturerIsActive">
                                        <label class="form-check-label small fw-semibold" for="lecturerIsActive">Status Aktif</label>
                                    </div>
                                </div>

                                <div class="col-12 d-flex justify-content-end gap-2 border-top pt-2">
                                    <button type="button" class="btn btn-sm btn-light rounded-pill px-3" wire:click="resetLecturerForm">
                                        Batal
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" wire:click="saveLecturer">
                                        <i class="fas fa-save me-1"></i> Simpan
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
