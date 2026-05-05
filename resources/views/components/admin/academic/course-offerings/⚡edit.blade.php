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

<div class="row">
    <div class="col-12">
        <x-alert />

        <!-- Section A: Course Offering Form -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Course Offering Detail</h5>
                <a href="{{ route('admin.academic.course-offerings.show', ['id' => $courseOfferingId]) }}" class="btn btn-info  ">
                    <i class="fas fa-eye me-1"></i> Lihat Detail
                </a>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="updateCourseOffering">
                    <div class="row">
                        <div class="form-group col-lg-6 col-md-6 col-sm-12">
                            <label>Tahun Akademik</label>
                            <select class="form-control" wire:model.defer="courseOfferingForm.academic_year_id">
                                <option value="">Pilih Tahun Akademik</option>
                                @foreach ($availableAcademicYears as $year)
                                    <option value="{{ $year['id'] }}">{{ $year['name'] }}</option>
                                @endforeach
                            </select>
                            @error('courseOfferingForm.academic_year_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-lg-6 col-md-6 col-sm-12">
                            <label>Program Studi</label>
                            <select class="form-control" wire:model.defer="courseOfferingForm.study_program_id">
                                <option value="">Pilih Program Studi</option>
                                @foreach ($availableStudyPrograms as $program)
                                    <option value="{{ $program['id'] }}">{{ $program['name'] }}</option>
                                @endforeach
                            </select>
                            @error('courseOfferingForm.study_program_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-lg-6 col-md-6 col-sm-12">
                            <label>Mata Kuliah</label>
                            <select class="form-control" wire:model.defer="courseOfferingForm.course_id">
                                <option value="">Pilih Mata Kuliah</option>
                                @foreach ($availableCourses as $course)
                                    <option value="{{ $course['id'] }}">{{ $course['code'] }} - {{ $course['name'] }}</option>
                                @endforeach
                            </select>
                            @error('courseOfferingForm.course_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-lg-6 col-md-6 col-sm-12">
                            <label>Label (Kelas)</label>
                            <input type="text" class="form-control" wire:model.defer="courseOfferingForm.label">
                            @error('courseOfferingForm.label') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-lg-3 col-md-6 col-sm-12">
                            <label>Kode</label>
                            <input type="text" class="form-control" wire:model.defer="courseOfferingForm.code">
                            @error('courseOfferingForm.code') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-lg-3 col-md-6 col-sm-12">
                            <label>Semester</label>
                            <input type="number" min="1" max="14" class="form-control" wire:model.defer="courseOfferingForm.semester_no">
                            @error('courseOfferingForm.semester_no') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-lg-3 col-md-6 col-sm-12">
                            <label>Kapasitas</label>
                            <input type="number" min="1" class="form-control" wire:model.defer="courseOfferingForm.capacity">
                            @error('courseOfferingForm.capacity') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-lg-3 col-md-6 col-sm-12">
                            <label>SKS</label>
                            <input type="number" min="1" max="24" class="form-control" wire:model.defer="courseOfferingForm.credits">
                            @error('courseOfferingForm.credits') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-lg-3 col-md-6 col-sm-12">
                            <label>Total Pertemuan</label>
                            <input type="number" min="1" max="32" class="form-control" wire:model.defer="courseOfferingForm.total_meetings">
                            @error('courseOfferingForm.total_meetings') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-lg-3 col-md-6 col-sm-12">
                            <label>Tanggal Mulai Kelas</label>
                            <input type="date" class="form-control" wire:model.defer="courseOfferingForm.class_start_date">
                            @error('courseOfferingForm.class_start_date') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-lg-3 col-md-6 col-sm-12">
                            <label>Tanggal Akhir Kelas</label>
                            <input type="date" class="form-control" wire:model.defer="courseOfferingForm.class_end_date">
                            @error('courseOfferingForm.class_end_date') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-lg-4 col-md-6 col-sm-12">
                            <label>Mode Pengiriman</label>
                            <select class="form-control" wire:model.defer="courseOfferingForm.delivery_mode">
                                <option value="Offline">Offline</option>
                                <option value="Online">Online</option>
                                <option value="Hybrid">Hybrid</option>
                            </select>
                            @error('courseOfferingForm.delivery_mode') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-lg-4 col-md-6 col-sm-12">
                            <label>Status</label>
                            <select class="form-control" wire:model.defer="courseOfferingForm.status">
                                <option value="Draft">Draft</option>
                                <option value="Open">Open</option>
                                <option value="Closed">Closed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                            @error('courseOfferingForm.status') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-lg-4 col-md-6 col-sm-12">
                            <label class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" wire:model.defer="courseOfferingForm.is_required">
                                <span class="form-check-label">Wajib Diambil</span>
                            </label>
                        </div>

                        <div class="form-group col-12">
                            <label>Catatan</label>
                            <textarea class="form-control" rows="2" wire:model.defer="courseOfferingForm.notes"></textarea>
                            @error('courseOfferingForm.notes') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group col-12 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Simpan Perubahan
                            </button>
                            <button type="button" class="btn btn-secondary" wire:click="cancel">
                                <i class="fas fa-arrow-left me-1"></i> Kembali
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Section B: Schedules & Session Generation -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Schedules & Generate Sessions</h5>
            </div>
            <div class="card-body">
                @if (!empty($generationMessage))
                    <div class="alert alert-{{ $generationMessage['type'] }} mb-3">
                        {{ $generationMessage['text'] }}
                    </div>
                @endif

                <div class="mb-3">
                    <h6 class="mb-2">Schedules</h6>
                    @if (count($schedulesData) > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Hari</th>
                                        <th>Mulai</th>
                                        <th>Selesai</th>
                                        <th>Dosen</th>
                                        <th>Lokasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($schedulesData as $schedule)
                                        <tr>
                                            <td>{{ $schedule['day_name'] }}</td>
                                            <td>{{ $schedule['start_time'] }}</td>
                                            <td>{{ $schedule['end_time'] }}</td>
                                            <td>{{ $schedule['lecturer_name'] }}</td>
                                            <td>{{ trim(($schedule['building'] ?? '') . ' ' . ($schedule['room'] ?? '')) ?: '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-warning mb-0">
                            Belum ada schedule. Isi dulu di menu course schedules sebelum generate session.
                        </div>
                    @endif
                </div>

                <button type="button" class="btn btn-info" wire:click="generateSessions">
                    <i class="fas fa-bolt me-1"></i> Generate Sessions
                </button>
                <small class="d-block text-muted mt-2">Session boleh dan tetap editable manual setelah generate.</small>
            </div>
        </div>

        <!-- Section C: Lecturers Management -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Dosen Pengajar</h5>
                <button class="btn btn-success  " wire:click="addLecturerForm">
                    <i class="fas fa-plus me-1"></i> Tambah Dosen
                </button>
            </div>
            <div class="card-body">
                @if (count($lecturersData) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Nama Dosen</th>
                                    <th>Role</th>
                                    <th>Urut</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lecturersData as $index => $lecturer)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $lecturer['lecturer_name'] }}</td>
                                        <td><span class="badge bg-info">{{ $lecturer['role'] }}</span></td>
                                        <td>{{ $lecturer['sort_order'] }}</td>
                                        <td>
                                            @if ($lecturer['is_active'])
                                                <span class="badge bg-success">Aktif</span>
                                            @else
                                                <span class="badge bg-secondary">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td>
                                            <button class="btn   btn-warning" wire:click="editLecturerForm({{ $lecturer['id'] }})">
                                                <i class="fas fa-pencil"></i>
                                            </button>
                                            <button class="btn   btn-danger" wire:click="deleteLecturer({{ $lecturer['id'] }})">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i> Belum ada dosen pengajar untuk course offering ini.
                    </div>
                @endif

                @if ($lecturerFormMode)
                    <hr>
                    <div class="p-3 rounded">
                        <div class="mb-2">
                            <h6>{{ $lecturerFormMode === 'add' ? 'Tambah Dosen Baru' : 'Edit Dosen' }}</h6>
                        </div>

                        <div class="row">
                            @if ($lecturerFormMode === 'add')
                                <div class="form-group col-lg-6 col-md-6 col-sm-12 mb-2">
                                    <label class="form-label">Dosen</label>
                                    <select class="form-control form-control-sm" wire:model="selectedLecturerId">
                                        <option value="">Pilih Dosen</option>
                                        @foreach ($availableLecturers as $lecturer)
                                            <option value="{{ $lecturer['id'] }}">{{ $lecturer['name'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('selectedLecturerId') <span class="text-danger text-sm">{{ $message }}</span> @enderror
                                </div>
                            @else
                                <div class="form-group col-lg-6 col-md-6 col-sm-12 mb-2">
                                    <label class="form-label">Dosen</label>
                                    <input type="text" class="form-control form-control-sm" value="{{ $editingLecturerName }}" readonly>
                                </div>
                            @endif

                            <div class="form-group col-lg-6 col-md-6 col-sm-12 mb-2">
                                <label class="form-label">Role</label>
                                <select class="form-control form-control-sm" wire:model="lecturerRole">
                                    <option value="Coordinator">Coordinator</option>
                                    <option value="Primary">Primary</option>
                                    <option value="Secondary">Secondary</option>
                                    <option value="Assistant">Assistant</option>
                                </select>
                                @error('lecturerRole') <span class="text-danger text-sm">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-2">
                                <label class="form-label">Urutan</label>
                                <input type="number" min="0" class="form-control form-control-sm" wire:model="lecturerSortOrder">
                                @error('lecturerSortOrder') <span class="text-danger text-sm">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-group col-lg-9 col-md-6 col-sm-12 mb-2">
                                <label class="form-label">Catatan</label>
                                <textarea class="form-control form-control-sm" rows="1" wire:model="lecturerNotes"></textarea>
                                @error('lecturerNotes') <span class="text-danger text-sm">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-group col-12 mb-2">
                                <label class="form-check me-3">
                                    <input class="form-check-input form-check-input-sm" type="checkbox" wire:model="lecturerIsActive">
                                    <span class="form-check-label">Aktif</span>
                                </label>
                            </div>

                            <div class="form-group col-12">
                                <button type="button" class="btn   btn-primary" wire:click="saveLecturer">
                                    <i class="fas fa-save me-1"></i> Simpan
                                </button>
                                <button type="button" class="btn   btn-secondary" wire:click="resetLecturerForm">
                                    <i class="fas fa-times me-1"></i> Batal
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
