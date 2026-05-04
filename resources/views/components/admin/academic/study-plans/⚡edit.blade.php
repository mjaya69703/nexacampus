<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Support\ActivePermission;
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

        $this->studentProfiles = StudentProfile::with('user', 'studyProgram')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (StudentProfile $profile) => [
                'id' => $profile->id,
                'label' => $profile->user?->name . ' (' . $profile->nim . ') - ' . ($profile->studyProgram?->name ?? '-'),
            ])
            ->toArray();

        $this->academicYears = AcademicYear::orderByDesc('created_at')
            ->get(['id', 'name'])
            ->toArray();

        $this->loadStudentRegistrations();
        $this->loadCourseOfferings();
        $this->resetDetailForm();
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

<div class="row">
    <div class="col-12">
        <x-alert />

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Section A - Header KRS</h5>
                <a href="{{ route('admin.academic.study-plans.show', ['id' => $studyPlan->id]) }}" class="btn btn-info ">
                    <i class="fas fa-eye me-1"></i> Lihat Detail
                </a>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="updateStudyPlan">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mahasiswa <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="studyPlanForm.student_profile_id" required>
                                <option value="">Pilih Mahasiswa</option>
                                @foreach($studentProfiles as $profile)
                                    <option value="{{ $profile['id'] }}">{{ $profile['label'] }}</option>
                                @endforeach
                            </select>
                            @error('studyPlanForm.student_profile_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tahun Akademik <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="studyPlanForm.academic_year_id" required>
                                <option value="">Pilih Tahun Akademik</option>
                                @foreach($academicYears as $year)
                                    <option value="{{ $year['id'] }}">{{ $year['name'] }}</option>
                                @endforeach
                            </select>
                            @error('studyPlanForm.academic_year_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Student Registration</label>
                            <select class="form-select" wire:model="studyPlanForm.student_registration_id">
                                <option value="">Pilih Registration (Opsional)</option>
                                @foreach($studentRegistrations as $registration)
                                    <option value="{{ $registration['id'] }}">{{ $registration['label'] }}</option>
                                @endforeach
                            </select>
                            @error('studyPlanForm.student_registration_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Semester</label>
                            <input type="number" min="1" max="14" class="form-control" wire:model="studyPlanForm.semester_no">
                            @error('studyPlanForm.semester_no') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="studyPlanForm.status" required>
                                <option value="Draft">Draft</option>
                                <option value="Submitted">Submitted</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                            @error('studyPlanForm.status') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Catatan</label>
                            <textarea class="form-control" rows="2" wire:model="studyPlanForm.notes"></textarea>
                            @error('studyPlanForm.notes') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Simpan Header
                            </button>
                            <button type="button" class="btn btn-secondary" wire:click="cancel">
                                <i class="fas fa-arrow-left me-1"></i> Kembali
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Section B - Manajemen Detail KRS</h5>
                @if (ActivePermission::check('study-plan.update'))
                    <button class="btn btn-success " wire:click="startCreateDetail">
                        <i class="fas fa-plus me-1"></i> Tambah Offering
                    </button>
                @endif
            </div>
            <div class="card-body">
                @if ($showDetailForm)
                    <div class="border rounded p-3 mb-3 ">
                        <h6 class="mb-3">{{ $editingDetailId ? 'Edit Detail KRS' : 'Tambah Offering ke KRS' }}</h6>

                        <div class="row">
                            <div class="col-lg-8 mb-2">
                                <label class="form-label">Offering <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" wire:model="detailForm.course_offering_id" @if($editingDetailId) disabled @endif>
                                    <option value="">Pilih Offering</option>
                                    @foreach($courseOfferings as $offering)
                                        <option value="{{ $offering['id'] }}">{{ $offering['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('detailForm.course_offering_id') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-lg-2 mb-2">
                                <label class="form-label">SKS</label>
                                <input type="number" min="1" max="30" class="form-control form-control-sm" wire:model="detailForm.credits">
                                @error('detailForm.credits') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-lg-2 mb-2">
                                <label class="form-label">Status</label>
                                <select class="form-select form-select-sm" wire:model="detailForm.status">
                                    <option value="Draft">Draft</option>
                                    <option value="Taken">Taken</option>
                                    <option value="Dropped">Dropped</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                                @error('detailForm.status') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4 mb-2">
                                <label class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" wire:model="detailForm.is_repeat">
                                    <span class="form-check-label">Mata kuliah ulang</span>
                                </label>
                            </div>

                            <div class="col-md-8 mb-2">
                                <label class="form-label">Catatan</label>
                                <input type="text" class="form-control form-control-sm" wire:model="detailForm.notes">
                                @error('detailForm.notes') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12 mt-2">
                                <button type="button" class="btn btn-primary " wire:click="saveDetail">
                                    <i class="fas fa-save me-1"></i> Simpan Detail
                                </button>
                                <button type="button" class="btn btn-secondary " wire:click="cancelDetailForm">
                                    <i class="fas fa-times me-1"></i> Batal
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
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Offering</th>
                                    <th>Semester</th>
                                    <th>SKS</th>
                                    <th>Status</th>
                                    <th>Repeat</th>
                                    <th>Catatan</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->studyPlanDetails as $index => $detail)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            {{ $detail->courseOffering?->course?->code ?? '-' }} -
                                            {{ $detail->courseOffering?->course?->name ?? '-' }}
                                            <span class="text-muted">/ {{ $detail->courseOffering?->label ?? '-' }}</span>
                                        </td>
                                        <td>{{ $detail->courseOffering?->semester_no ?? '-' }}</td>
                                        <td>{{ $detail->credits ?? '-' }}</td>
                                        <td><span class="badge bg-info">{{ $detail->status }}</span></td>
                                        <td>
                                            @if ($detail->is_repeat)
                                                <span class="badge bg-warning">Ya</span>
                                            @else
                                                <span class="badge bg-secondary">Tidak</span>
                                            @endif
                                        </td>
                                        <td>{{ $detail->notes ?? '-' }}</td>
                                        <td>
                                            @if (ActivePermission::check('study-plan.update'))
                                                <button class="btn btn-warning " wire:click="startEditDetail({{ $detail->id }})">
                                                    <i class="fas fa-pencil"></i>
                                                </button>
                                                <button class="btn btn-danger " wire:click="confirmDeleteDetail({{ $detail->id }})">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="3" class="text-end">Total</th>
                                    <th>{{ $totalSks }} SKS</th>
                                    <th colspan="4">{{ $totalMataKuliah }} Mata Kuliah</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i> Belum ada offering yang diambil pada KRS ini.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
