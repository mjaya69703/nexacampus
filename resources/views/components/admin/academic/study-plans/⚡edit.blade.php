<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Support\ActivePermission;
use App\Support\Notifications\NotificationDispatchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public StudyPlan $studyPlan;

    public array $studyPlanForm = [];
    public array $detailForm = [];

    public array $studentProfiles = [];
    public array $academicYears = [];
    public array $studentRegistrations = [];
    public array $courseOfferings = [];

    public ?int $editingDetailId = null;
    public bool $showDetailForm = false;
    public string $searchStudent = '';

    public function mount($id): void
    {
        $this->studyPlan = StudyPlan::with(['studentProfile.user', 'studentProfile.studyProgram', 'academicYear', 'studentRegistration'])
            ->findOrFail($id);

        $this->studyPlanForm = [
            'student_profile_id' => $this->studyPlan->student_profile_id,
            'academic_year_id' => $this->studyPlan->academic_year_id,
            'student_registration_id' => $this->studyPlan->student_registration_id,
            'semester_no' => $this->studyPlan->semester_no,
            'status' => $this->studyPlan->status,
            'notes' => $this->studyPlan->notes,
        ];

        $this->loadStudentProfiles();

        $this->academicYears = AcademicYear::orderByDesc('created_at')
            ->get(['id', 'name'])
            ->toArray();

        $this->loadStudentRegistrations();
        $this->loadCourseOfferings();
        $this->resetDetailForm();
    }

    public function loadStudentProfiles(): void
    {
        $query = StudentProfile::with('user', 'studyProgram');

        if (trim($this->searchStudent) !== '') {
            $keyword = trim($this->searchStudent);
            $query->where(function ($q) use ($keyword) {
                $q->whereHas('user', function ($subQ) use ($keyword) {
                    $subQ->where('first_name', 'like', "%{$keyword}%")
                        ->orWhere('last_name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                })->orWhere('nim', 'like', "%{$keyword}%");
            });
        }

        $this->studentProfiles = $query->limit(15)->get()->map(fn (StudentProfile $profile) => [
            'id' => $profile->id,
            'label' => $profile->user?->name . ' (' . $profile->nim . ') - ' . ($profile->studyProgram?->name ?? '-'),
            'nim' => $profile->nim,
            'name' => $profile->user?->name ?? 'Mahasiswa',
            'studyProgram' => $profile->studyProgram?->name ?? '-',
            'entry_year' => $profile->entry_year ?? '-',
        ])->toArray();
    }

    public function updatedSearchStudent(): void
    {
        $this->loadStudentProfiles();
    }

    public function selectStudent(int $id): void
    {
        $this->studyPlanForm['student_profile_id'] = $id;
        $this->studyPlanForm['student_registration_id'] = null;
        $profile = StudentProfile::with('user')->find($id);
        $this->searchStudent = $profile ? ($profile->user?->name . ' (' . $profile->nim . ')') : '';
        $this->loadStudentRegistrations();
        $this->loadCourseOfferings();
    }

    public function clearStudent(): void
    {
        $this->studyPlanForm['student_profile_id'] = null;
        $this->studyPlanForm['student_registration_id'] = null;
        $this->searchStudent = '';
        $this->loadStudentRegistrations();
        $this->loadCourseOfferings();
    }

    public function updatedStudyPlanFormStudentProfileId(): void
    {
        $this->studyPlanForm['student_registration_id'] = null;
        $this->loadStudentRegistrations();
        $this->loadCourseOfferings();
    }

    public function updatedStudyPlanFormAcademicYearId(): void
    {
        $this->studyPlanForm['student_registration_id'] = null;
        $this->loadStudentRegistrations();
        $this->loadCourseOfferings();
    }

    protected function loadStudentRegistrations(): void
    {
        $studentProfileId = $this->studyPlanForm['student_profile_id'] ?? null;
        $academicYearId = $this->studyPlanForm['academic_year_id'] ?? null;

        if (! $studentProfileId || ! $academicYearId) {
            $this->studentRegistrations = [];

            return;
        }

        $this->studentRegistrations = StudentRegistration::query()
            ->where('student_profile_id', $studentProfileId)
            ->where('academic_year_id', $academicYearId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (StudentRegistration $registration) => [
                'id' => $registration->id,
                'label' => '#' . $registration->id . ' - ' . $registration->registration_status,
            ])
            ->toArray();
    }

    protected function loadCourseOfferings(): void
    {
        $academicYearId = $this->studyPlanForm['academic_year_id'] ?? null;
        $studentProfileId = $this->studyPlanForm['student_profile_id'] ?? null;

        if (! $academicYearId || ! $studentProfileId) {
            $this->courseOfferings = [];

            return;
        }

        $studentProfile = StudentProfile::find($studentProfileId);

        if (! $studentProfile?->study_program_id) {
            $this->courseOfferings = [];

            return;
        }

        $this->courseOfferings = CourseOffering::query()
            ->with('course')
            ->where('academic_year_id', $academicYearId)
            ->where('study_program_id', $studentProfile->study_program_id)
            ->whereIn('status', ['Open', 'Draft'])
            ->orderBy('semester_no')
            ->orderBy('label')
            ->get()
            ->map(fn (CourseOffering $offering) => [
                'id' => $offering->id,
                'label' => ($offering->course?->code ?? '-') . ' - ' . ($offering->course?->name ?? '-') . ' / ' . ($offering->label ?? '-') . ' (' . ($offering->credits ?? 0) . ' SKS)',
                'default_credits' => $offering->credits,
            ])
            ->toArray();
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.study-plans.index');
    }

    public function updateStudyPlan(): void
    {
        $validatedData = $this->validate([
            'studyPlanForm.student_profile_id' => [
                'required',
                'exists:student_profiles,id',
                Rule::unique('study_plans', 'student_profile_id')
                    ->where(fn ($query) => $query->where('academic_year_id', $this->studyPlanForm['academic_year_id'] ?? null))
                    ->whereNull('deleted_at')
                    ->ignore($this->studyPlan->id),
            ],
            'studyPlanForm.academic_year_id' => 'required|exists:academic_years,id',
            'studyPlanForm.student_registration_id' => 'nullable|exists:student_registrations,id',
            'studyPlanForm.semester_no' => 'nullable|integer|min:1|max:14',
            'studyPlanForm.status' => 'required|in:Draft,Submitted,Approved,Rejected,Cancelled',
            'studyPlanForm.notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $previousStatus = $this->studyPlan->status;
            $status = $validatedData['studyPlanForm']['status'];

            $payload = [
                'student_profile_id' => $validatedData['studyPlanForm']['student_profile_id'],
                'academic_year_id' => $validatedData['studyPlanForm']['academic_year_id'],
                'student_registration_id' => $validatedData['studyPlanForm']['student_registration_id'] ?: null,
                'semester_no' => $validatedData['studyPlanForm']['semester_no'] ?: null,
                'status' => $status,
                'notes' => $validatedData['studyPlanForm']['notes'] ?: null,
                'updated_by' => auth()->id(),
            ];

            if ($status === 'Submitted' && ! $this->studyPlan->submitted_at) {
                $payload['submitted_at'] = now();
            }

            if ($status === 'Approved') {
                $payload['approved_at'] = now();
                $payload['approved_by'] = auth()->id();
            }

            if (in_array($status, ['Draft', 'Rejected', 'Cancelled'], true)) {
                $payload['approved_at'] = null;
                $payload['approved_by'] = null;
            }

            $this->studyPlan->update($payload);

            DB::commit();
            $this->studyPlan->refresh();

            if ($previousStatus !== $this->studyPlan->status && in_array($this->studyPlan->status, ['Submitted', 'Approved', 'Rejected', 'Cancelled'], true)) {
                app(NotificationDispatchService::class)->studyPlanStatusUpdated($this->studyPlan, $this->studyPlan->notes);
            }

            $this->loadStudentRegistrations();
            $this->loadCourseOfferings();

            session()->flash('success', 'Header KRS berhasil diperbarui.');
        } catch (\Throwable $th) {
            DB::rollBack();
            session()->flash('error', 'Terjadi kesalahan: ' . $th->getMessage());
        }
    }

    public function startCreateDetail(): void
    {
        if (! ActivePermission::check('study-plan.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengelola detail KRS.');

            return;
        }

        $this->editingDetailId = null;
        $this->showDetailForm = true;
        $this->resetDetailForm();
    }

    public function startEditDetail(int $id): void
    {
        if (! ActivePermission::check('study-plan.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengelola detail KRS.');

            return;
        }

        $detail = $this->studyPlan->details()->with('courseOffering')->findOrFail($id);

        $this->editingDetailId = $detail->id;
        $this->showDetailForm = true;

        $this->detailForm = [
            'course_offering_id' => $detail->course_offering_id,
            'credits' => $detail->credits,
            'is_repeat' => $detail->is_repeat,
            'status' => $detail->status,
            'notes' => $detail->notes,
        ];
    }

    public function cancelDetailForm(): void
    {
        $this->showDetailForm = false;
        $this->editingDetailId = null;
        $this->resetDetailForm();
    }

    public function saveDetail(): void
    {
        if (! ActivePermission::check('study-plan.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengelola detail KRS.');

            return;
        }

        $validatedData = $this->validate([
            'detailForm.course_offering_id' => [
                'required',
                'exists:course_offerings,id',
                Rule::unique('study_plan_details', 'course_offering_id')
                    ->where(fn ($query) => $query->where('study_plan_id', $this->studyPlan->id))
                    ->whereNull('deleted_at')
                    ->ignore($this->editingDetailId),
            ],
            'detailForm.credits' => 'nullable|integer|min:1|max:30',
            'detailForm.is_repeat' => 'nullable|boolean',
            'detailForm.status' => 'required|in:Draft,Taken,Dropped,Cancelled',
            'detailForm.notes' => 'nullable|string',
        ]);

        $payload = [
            'study_plan_id' => $this->studyPlan->id,
            'course_offering_id' => $validatedData['detailForm']['course_offering_id'],
            'credits' => $validatedData['detailForm']['credits'] ?: null,
            'is_repeat' => (bool) ($validatedData['detailForm']['is_repeat'] ?? false),
            'status' => $validatedData['detailForm']['status'],
            'notes' => $validatedData['detailForm']['notes'] ?: null,
        ];

        if ($this->editingDetailId) {
            $detail = $this->studyPlan->details()->findOrFail($this->editingDetailId);
            $detail->update(array_merge($payload, ['updated_by' => auth()->id()]));
            session()->flash('success', 'Detail KRS berhasil diperbarui.');
        } else {
            StudyPlanDetail::create(array_merge($payload, ['created_by' => auth()->id()]));
            session()->flash('success', 'Offering berhasil ditambahkan ke KRS.');
        }

        $this->studyPlan->refresh();
        $this->cancelDetailForm();
    }

    public function confirmDeleteDetail(int $id): void
    {
        if (! ActivePermission::check('study-plan.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengelola detail KRS.');

            return;
        }

        $detail = $this->studyPlan->details()->with('courseOffering.course')->find($id);

        if (! $detail) {
            return;
        }

        $courseLabel = ($detail->courseOffering?->course?->code ?? '-') . ' - ' . ($detail->courseOffering?->course?->name ?? 'Mata kuliah');

        $this->js('
            Swal.fire({
                title: "Hapus detail KRS?",
                text: "' . $courseLabel . ' - Data tidak bisa dikembalikan!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya hapus",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("deleteDetailConfirmed", { id: ' . $id . ' })
                }
            });
        ');
    }

    #[\Livewire\Attributes\On('deleteDetailConfirmed')]
    public function deleteDetailConfirmed($id = null): void
    {
        if (! ActivePermission::check('study-plan.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengelola detail KRS.');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id detail KRS tidak ditemukan.');

            return;
        }

        $detail = $this->studyPlan->details()->find($id);

        if ($detail) {
            $detail->update(['deleted_by' => auth()->id()]);
            $detail->delete();
            $this->studyPlan->refresh();

            $this->js('
                Swal.fire({
                    title: "Data dihapus",
                    text: "Detail KRS berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function updatedDetailFormCourseOfferingId($value): void
    {
        if (! $value || $this->editingDetailId) {
            return;
        }

        $offering = CourseOffering::find($value);

        if ($offering && empty($this->detailForm['credits'])) {
            $this->detailForm['credits'] = $offering->credits;
        }
    }

    public function getStudyPlanDetailsProperty()
    {
        return $this->studyPlan->details()
            ->with(['courseOffering.course'])
            ->whereHas('courseOffering')
            ->join('course_offerings', 'course_offerings.id', '=', 'study_plan_details.course_offering_id')
            ->leftJoin('courses', 'courses.id', '=', 'course_offerings.course_id')
            ->orderByRaw('COALESCE(course_offerings.semester_no, 999) ASC')
            ->orderBy('courses.name')
            ->select('study_plan_details.*')
            ->get();
    }

    protected function resetDetailForm(): void
    {
        $this->detailForm = [
            'course_offering_id' => null,
            'credits' => null,
            'is_repeat' => false,
            'status' => 'Draft',
            'notes' => null,
        ];
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Edit KRS',
            ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Edit Kartu Rencana Studi (KRS)"
        description="Perbarui informasi utama rancangan studi atau kelola daftar mata kuliah beserta beban SKS mahasiswa."
        icon="book-open"
    >
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.academic.study-plans.show', ['id' => $studyPlan->id]) }}" class="btn btn-sm btn-light text-primary fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2 border">
                <i class="fa fa-eye"></i> <span>Lihat Detail</span>
            </a>
            <button type="button" class="btn btn-sm btn-light text-dark fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2 border" wire:click="cancel">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </button>
        </div>
    </x-admin.academic.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-book-reader fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Section A - Parameter Utama KRS</h4>
                            <div class="text-muted small">Tentukan mahasiswa, periode akademik, semester, serta status persetujuan KRS.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form wire:submit.prevent="updateStudyPlan">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Mahasiswa <span class="text-danger">*</span></label>
                                @if($studyPlanForm['student_profile_id'])
                                    @php
                                        $selected = \App\Models\Academic\StudentProfile::with('user', 'studyProgram')->find($studyPlanForm['student_profile_id']);
                                    @endphp
                                    <div class="alert alert-info border-0 rounded-3 p-3 mb-0 d-flex align-items-center justify-content-between shadow-sm">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="fa fa-user-check fs-5"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark fs-6">{{ $selected?->user?->name ?? 'Mahasiswa' }} ({{ $selected?->nim ?? '-' }})</div>
                                                <div class="small text-muted">{{ $selected?->studyProgram?->name ?? '-' }} | Angkatan {{ $selected?->entry_year ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 fw-semibold" wire:click="clearStudent">
                                            <i class="fa fa-times me-1"></i> Ganti Mahasiswa
                                        </button>
                                    </div>
                                    <input type="hidden" wire:model="studyPlanForm.student_profile_id">
                                @else
                                    <div class="position-relative">
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa fa-search"></i></span>
                                            <input 
                                                type="text" 
                                                class="form-control border-start-0 ps-0" 
                                                wire:model.live.debounce.300ms="searchStudent" 
                                                placeholder="Ketik nama mahasiswa, NIM, atau email untuk mencari..."
                                            >
                                        </div>
                                        @if(count($studentProfiles) > 0)
                                            <div class="list-group shadow-lg border rounded-3 overflow-hidden mt-1 bg-white" style="max-height: 280px; overflow-y: auto;">
                                                @foreach($studentProfiles as $profile)
                                                    <button 
                                                        type="button" 
                                                        class="list-group-item list-group-item-action p-3 text-start d-flex align-items-center justify-content-between border-bottom"
                                                        wire:click="selectStudent({{ $profile['id'] }})"
                                                    >
                                                        <div>
                                                            <div class="fw-bold text-dark">{{ $profile['name'] }}</div>
                                                            <div class="small text-muted"><i class="fa fa-id-card me-1 text-primary"></i> NIM: {{ $profile['nim'] }} | {{ $profile['studyProgram'] }}</div>
                                                        </div>
                                                        <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary px-3 py-1 fw-semibold border border-primary border-opacity-25">Pilih <i class="fa fa-check ms-1"></i></span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="p-3 text-center border rounded-3 bg-light text-muted mt-1 small">
                                                <i class="fa fa-info-circle me-1"></i> Tidak ditemukan mahasiswa dengan kata kunci tersebut.
                                            </div>
                                        @endif
                                    </div>
                                @endif
                                @error('studyPlanForm.student_profile_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Tahun Akademik <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model="studyPlanForm.academic_year_id" required>
                                    <option value="">Pilih Tahun Akademik</option>
                                    @foreach($academicYears as $year)
                                        <option value="{{ $year['id'] }}">{{ $year['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('studyPlanForm.academic_year_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Student Registration Terkait</label>
                                <select class="form-select" wire:model="studyPlanForm.student_registration_id">
                                    <option value="">Pilih Registration (Opsional)</option>
                                    @foreach($studentRegistrations as $registration)
                                        <option value="{{ $registration['id'] }}">{{ $registration['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('studyPlanForm.student_registration_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Semester Ke</label>
                                <input type="number" min="1" max="14" class="form-control" wire:model="studyPlanForm.semester_no" placeholder="Contoh: 3">
                                @error('studyPlanForm.semester_no') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Status KRS <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model="studyPlanForm.status" required>
                                    <option value="Draft">Draft</option>
                                    <option value="Submitted">Submitted</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                                @error('studyPlanForm.status') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Catatan</label>
                                <textarea class="form-control" rows="2" wire:model="studyPlanForm.notes" placeholder="Catatan atau keterangan mengenai KRS..."></textarea>
                                @error('studyPlanForm.notes') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12 border-top pt-3 mt-4 d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-semibold">
                                    <i class="fa fa-save me-1"></i> Simpan Header
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-list-check fs-5"></i>
                            </div>
                            <div>
                                <h4 class="card-title fw-bold mb-1 text-dark">Section B - Manajemen Detail Mata Kuliah</h4>
                                <div class="text-muted small">Daftar kelas/mata kuliah yang diambil mahasiswa beserta besaran SKS.</div>
                            </div>
                        </div>
                        @if (ActivePermission::check('study-plan.update'))
                            <button type="button" class="btn btn-success rounded-pill px-4 py-2 shadow-sm fw-semibold d-inline-flex align-items-center gap-2" wire:click="startCreateDetail">
                                <i class="fa fa-plus"></i> <span>Tambah Offering</span>
                            </button>
                        @endif
                    </div>
                </div>
                <div class="card-body p-4">
                    @if ($showDetailForm)
                        <div class="bg-light border rounded-4 p-4 mb-4 shadow-sm">
                            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                                <h6 class="fw-bold text-dark mb-0"><i class="fa fa-edit text-primary me-2"></i>{{ $editingDetailId ? 'Edit Detail KRS' : 'Tambah Offering ke KRS' }}</h6>
                                <button type="button" class="btn-close" aria-label="Close" wire:click="cancelDetailForm"></button>
                            </div>

                            <div class="row g-3">
                                <div class="col-lg-7">
                                    <label class="form-label fw-semibold small">Offering Mata Kuliah <span class="text-danger">*</span></label>
                                    <select class="form-select" wire:model="detailForm.course_offering_id" @if($editingDetailId) disabled @endif>
                                        <option value="">Pilih Offering</option>
                                        @foreach($courseOfferings as $offering)
                                            <option value="{{ $offering['id'] }}">{{ $offering['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('detailForm.course_offering_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-lg-2">
                                    <label class="form-label fw-semibold small">Bobot SKS</label>
                                    <input type="number" min="1" max="30" class="form-control" wire:model="detailForm.credits">
                                    @error('detailForm.credits') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-lg-3">
                                    <label class="form-label fw-semibold small">Status Matkul</label>
                                    <select class="form-select" wire:model="detailForm.status">
                                        <option value="Draft">Draft</option>
                                        <option value="Taken">Taken</option>
                                        <option value="Dropped">Dropped</option>
                                        <option value="Cancelled">Cancelled</option>
                                    </select>
                                    @error('detailForm.status') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-md-4 d-flex align-items-center pt-2">
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input" type="checkbox" id="isRepeatSwitch" wire:model="detailForm.is_repeat">
                                        <label class="form-check-label fw-semibold small" for="isRepeatSwitch">Mata Kuliah Ulang (Mengulang)</label>
                                    </div>
                                </div>

                                <div class="col-md-8">
                                    <label class="form-label fw-semibold small">Catatan Matkul</label>
                                    <input type="text" class="form-control" wire:model="detailForm.notes" placeholder="Keterangan opsional untuk mata kuliah ini...">
                                    @error('detailForm.notes') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-12 border-top pt-3 mt-3 d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-light rounded-pill px-3 py-2" wire:click="cancelDetailForm">
                                        <i class="fa fa-times me-1"></i> Batal
                                    </button>
                                    <button type="button" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-semibold" wire:click="saveDetail">
                                        <i class="fa fa-save me-1"></i> Simpan Detail Matkul
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($this->studyPlanDetails->count() > 0)
                        @php
                            $totalMataKuliah = $this->studyPlanDetails->count();
                            $totalSks = (int) $this->studyPlanDetails->sum('credits');
                        @endphp

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-3 px-3" style="width: 50px;">No</th>
                                        <th class="py-3 px-3">Offering & Mata Kuliah</th>
                                        <th class="py-3 px-3 text-center">Semester</th>
                                        <th class="py-3 px-3 text-center">SKS</th>
                                        <th class="py-3 px-3 text-center">Status</th>
                                        <th class="py-3 px-3 text-center">Mengulang</th>
                                        <th class="py-3 px-3">Catatan</th>
                                        <th class="py-3 px-3 text-center" style="width: 110px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->studyPlanDetails as $index => $detail)
                                        <tr>
                                            <td class="px-3 fw-semibold text-muted">{{ $index + 1 }}</td>
                                            <td class="px-3">
                                                <div class="fw-bold text-dark">
                                                    {{ $detail->courseOffering?->course?->code ?? '-' }} - {{ $detail->courseOffering?->course?->name ?? '-' }}
                                                </div>
                                                <small class="text-muted"><i class="fa fa-tag me-1"></i>Kelas: {{ $detail->courseOffering?->label ?? '-' }}</small>
                                            </td>
                                            <td class="px-3 text-center">{{ $detail->courseOffering?->semester_no ?? '-' }}</td>
                                            <td class="px-3 text-center fw-bold text-primary">{{ $detail->credits ?? '-' }}</td>
                                            <td class="px-3 text-center">
                                                <span class="badge rounded-pill px-3 py-2 @if($detail->status === 'Taken') bg-success @elseif($detail->status === 'Dropped' || $detail->status === 'Cancelled') bg-danger @else bg-info @endif">
                                                    {{ $detail->status }}
                                                </span>
                                            </td>
                                            <td class="px-3 text-center">
                                                @if ($detail->is_repeat)
                                                    <span class="badge bg-warning text-dark rounded-pill px-2">Ya</span>
                                                @else
                                                    <span class="badge bg-light text-muted border rounded-pill px-2">Tidak</span>
                                                @endif
                                            </td>
                                            <td class="px-3 small text-muted">{{ $detail->notes ?? '-' }}</td>
                                            <td class="px-3 text-center">
                                                @if (ActivePermission::check('study-plan.update'))
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <button type="button" class="btn btn-sm btn-light text-warning border shadow-sm rounded-circle" style="width: 34px; height: 34px;" title="Edit Detail" wire:click="startEditDetail({{ $detail->id }})">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-light text-danger border shadow-sm rounded-circle" style="width: 34px; height: 34px;" title="Hapus Detail" wire:click="confirmDeleteDetail({{ $detail->id }})">
                                                            <i class="fa fa-trash"></i>
                                                        </button>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="3" class="text-end py-3 px-3 fw-bold">Total Pengambilan:</th>
                                        <th class="text-center py-3 px-3 fw-bold text-primary fs-6">{{ $totalSks }} SKS</th>
                                        <th colspan="4" class="py-3 px-3 fw-semibold text-muted">{{ $totalMataKuliah }} Mata Kuliah</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info border-0 shadow-sm rounded-4 p-4 mb-0 d-flex align-items-center gap-3">
                            <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                                <i class="fa fa-info-circle fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Belum Ada Mata Kuliah Diambil</h6>
                                <div class="small">Klik tombol <strong>Tambah Offering</strong> di sudut kanan atas untuk memasukkan kelas/mata kuliah ke rancangan studi ini.</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
