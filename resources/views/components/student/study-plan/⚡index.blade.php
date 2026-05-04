<?php

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\TranscriptEntry;
use Livewire\Component;

new class extends Component
{
    public ?int $studentProfileId = null;
    public ?int $activeAcademicYearId = null;
    public ?int $studyPlanId = null;
    public ?int $currentRegistrationId = null;
    public ?int $registrationSemesterNo = null;
    public ?int $semesterFilter = null;
    public bool $showRetakeOfferings = false;
    public bool $hasProfile = false;
    public bool $hasActiveAcademicYear = false;
    public bool $hasActiveRegistration = false;
    public bool $isRegistrationApproved = false;
    public bool $canModifyStudyPlan = true;
    public bool $isStudyPlanPeriodOpen = false;
    public ?string $studyPlanPeriodName = null;
    public ?string $studyPlanPeriodRange = null;
    public ?string $submittedAt = null;
    public ?string $studyPlanStatus = null;
    public ?string $activeAcademicYearName = null;
    public ?string $registrationStatus = null;
    public array $studentInfo = [];
    public array $selectedCourses = [];
    public array $availableOfferings = [];
    public int $totalCredits = 0;

    public function mount(): void
    {
        $this->studentInfo = [
            'name' => '-',
            'nim' => '-',
            'study_program' => '-',
            'current_semester' => null,
        ];

        $user = auth()->user();

        if (! $user) {
            return;
        }

        $studentProfile = $user->studentProfile()
            ->with('studyProgram')
            ->first();

        if (! $studentProfile) {
            return;
        }

        $this->hasProfile = true;
        $this->studentProfileId = $studentProfile->id;
        $this->semesterFilter = $studentProfile->current_semester;
        $this->studentInfo = [
            'name' => $user->name,
            'nim' => $studentProfile->nim,
            'study_program' => $studentProfile->studyProgram?->name ?? '-',
            'current_semester' => $studentProfile->current_semester,
        ];

        $activeAcademicYear = AcademicYear::query()
            ->where('is_active', true)
            ->latest('start_date')
            ->first();

        if (! $activeAcademicYear) {
            return;
        }

        $this->hasActiveAcademicYear = true;
        $this->activeAcademicYearId = $activeAcademicYear->id;
        $this->activeAcademicYearName = $activeAcademicYear->name;

        $this->loadActiveRegistration();
        $this->loadStudyPlanPeriod();
        $this->loadStudyPlan();
        $this->loadSelectedCourses();
        $this->loadAvailableOfferings();
    }

    public function updatedSemesterFilter(): void
    {
        $this->loadAvailableOfferings();
    }

    public function updatedShowRetakeOfferings(): void
    {
        $this->loadAvailableOfferings();
    }

    public function addCourse(int $offeringId): void
    {
        if (! $this->ensureApprovedRegistration()) {
            return;
        }

        if (! $this->canModifyStudyPlan) {
            session()->flash('error', 'KRS yang sudah disubmit tidak dapat diubah.');

            return;
        }

        $studyPlan = $this->ensureStudyPlanExists();

        if (! $studyPlan) {
            session()->flash('error', 'Study plan tidak tersedia.');

            return;
        }

        $offering = CourseOffering::query()
            ->with('course')
            ->whereKey($offeringId)
            ->where('academic_year_id', $this->activeAcademicYearId)
            ->where('status', 'Open')
            ->first();

        if (! $offering) {
            session()->flash('error', 'Course offering tidak ditemukan.');

            return;
        }

        if (! $this->registrationSemesterNo) {
            session()->flash('error', 'Semester registrasi belum tersedia.');

            return;
        }

        if (($offering->semester_no ?? 0) > $this->registrationSemesterNo) {
            session()->flash('error', 'Tidak boleh mengambil mata kuliah semester di atas semester registrasi.');

            return;
        }

        $alreadyExists = StudyPlanDetail::query()
            ->where('study_plan_id', $this->studyPlanId)
            ->where('course_offering_id', $offeringId)
            ->exists();

        if ($alreadyExists) {
            session()->flash('error', 'Mata kuliah sudah ada di KRS.');

            return;
        }

        $courseId = $offering->course_id;

        $alreadySelectedSameCourse = StudyPlanDetail::query()
            ->where('study_plan_id', $this->studyPlanId)
            ->whereHas('courseOffering', function ($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })
            ->exists();

        if ($alreadySelectedSameCourse) {
            session()->flash('error', 'Mata kuliah yang sama sudah dipilih di KRS ini.');

            return;
        }

        $isRetake = false;

        if (($offering->semester_no ?? 0) < $this->registrationSemesterNo) {
            $retakeAllowed = TranscriptEntry::query()
                ->where('student_profile_id', $this->studentProfileId)
                ->where('course_id', $courseId)
                ->whereIn('result_status', ['Failed', 'Incomplete'])
                ->exists();

            if (! $retakeAllowed) {
                session()->flash('error', 'Mata kuliah semester sebelumnya hanya bisa diambil ulang jika pernah Failed atau Incomplete.');

                return;
            }

            $isRetake = true;
        }

        StudyPlanDetail::create([
            'study_plan_id' => $studyPlan->id,
            'course_offering_id' => $offering->id,
            'credits' => $offering->credits ?? $offering->course?->credits,
            'status' => 'Draft',
            'is_repeat' => $isRetake,
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Mata kuliah berhasil ditambahkan ke KRS.');
        $this->loadSelectedCourses();
        $this->loadAvailableOfferings();
    }

    public function removeCourse(int $detailId): void
    {
        if (! $this->ensureApprovedRegistration()) {
            return;
        }

        if (! $this->canModifyStudyPlan) {
            session()->flash('error', 'KRS yang sudah disubmit tidak dapat diubah.');

            return;
        }

        if (! $this->studyPlanId) {
            return;
        }

        $detail = StudyPlanDetail::query()
            ->whereKey($detailId)
            ->where('study_plan_id', $this->studyPlanId)
            ->first();

        if (! $detail) {
            session()->flash('error', 'Detail KRS tidak ditemukan.');

            return;
        }

        $detail->update([
            'deleted_by' => auth()->id(),
        ]);
        $detail->delete();

        session()->flash('success', 'Mata kuliah berhasil dihapus dari KRS.');
        $this->loadStudyPlan();
        $this->loadSelectedCourses();
        $this->loadAvailableOfferings();
    }

    public function submitStudyPlan(): void
    {
        if (! $this->ensureApprovedRegistration()) {
            return;
        }

        if (! $this->studyPlanId || ! $this->activeAcademicYearId) {
            session()->flash('error', 'Study plan tidak tersedia.');

            return;
        }

        if (! $this->currentRegistrationId) {
            session()->flash('error', 'Registrasi semester belum tersedia.');

            return;
        }

        $this->loadStudyPlanPeriod();

        if (! $this->isStudyPlanPeriodOpen) {
            session()->flash('error', 'Periode submit KRS belum aktif.');

            return;
        }

        if (! in_array($this->studyPlanStatus, ['Draft', 'Rejected'], true)) {
            session()->flash('error', 'Status KRS saat ini tidak dapat disubmit.');

            return;
        }

        $detailsCount = StudyPlanDetail::query()
            ->where('study_plan_id', $this->studyPlanId)
            ->count();

        if ($detailsCount < 1) {
            session()->flash('error', 'Minimal pilih 1 mata kuliah sebelum submit KRS.');

            return;
        }

        StudyPlan::query()
            ->whereKey($this->studyPlanId)
            ->update([
                'status' => 'Submitted',
                'submitted_at' => now(),
                'updated_by' => auth()->id(),
            ]);

        session()->flash('success', 'KRS berhasil disubmit dan menunggu persetujuan.');
        $this->loadStudyPlan();
        $this->loadSelectedCourses();
        $this->loadAvailableOfferings();
    }

    public function reviseStudyPlan(): void
    {
        if (! $this->ensureApprovedRegistration()) {
            return;
        }

        if (! $this->studyPlanId) {
            return;
        }

        if ($this->studyPlanStatus !== 'Submitted') {
            session()->flash('error', 'Status KRS saat ini tidak bisa direvisi.');

            return;
        }

        StudyPlan::query()
            ->whereKey($this->studyPlanId)
            ->update([
                'status' => 'Draft',
                'updated_by' => auth()->id(),
            ]);

        session()->flash('success', 'KRS kembali ke Draft. Silakan lakukan revisi.');
        $this->loadStudyPlan();
        $this->loadSelectedCourses();
        $this->loadAvailableOfferings();
    }

    public function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'Approved' => 'bg-green-lt',
            'Submitted' => 'bg-blue-lt',
            'Draft' => 'bg-yellow-lt',
            'Rejected', 'Cancelled' => 'bg-red-lt',
            default => 'bg-secondary-lt',
        };
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Study Plan',
        ]);
    }

    private function loadStudyPlan(): void
    {
        if (! $this->studentProfileId || ! $this->activeAcademicYearId) {
            return;
        }

        if (! $this->isRegistrationApproved) {
            $this->studyPlanId = null;
            $this->studyPlanStatus = null;
            $this->submittedAt = null;
            $this->canModifyStudyPlan = false;

            return;
        }

        if (! $this->currentRegistrationId) {
            $this->studyPlanId = null;
            $this->studyPlanStatus = null;
            $this->submittedAt = null;
            $this->canModifyStudyPlan = false;

            return;
        }

        $studyPlan = StudyPlan::query()
            ->where('student_profile_id', $this->studentProfileId)
            ->where('academic_year_id', $this->activeAcademicYearId)
            ->first();

        if (! $studyPlan) {
            $this->studyPlanId = null;
            $this->studyPlanStatus = null;
            $this->submittedAt = null;
            $this->canModifyStudyPlan = true;

            if ($this->registrationSemesterNo !== null) {
                $this->semesterFilter = $this->registrationSemesterNo;
            }

            return;
        }

        $needsSync =
            $studyPlan->student_registration_id !== $this->currentRegistrationId ||
            ($this->registrationSemesterNo !== null && $studyPlan->semester_no !== $this->registrationSemesterNo);

        if ($needsSync) {
            $studyPlan->update([
                'student_registration_id' => $this->currentRegistrationId,
                'semester_no' => $this->registrationSemesterNo ?? $studyPlan->semester_no,
                'updated_by' => auth()->id(),
            ]);

            $studyPlan->refresh();
        }

        $this->studyPlanId = $studyPlan->id;
        $this->studyPlanStatus = $studyPlan->status;
        $this->submittedAt = $studyPlan->submitted_at?->format('d M Y H:i');
        $this->canModifyStudyPlan = in_array($studyPlan->status, ['Draft', 'Rejected'], true);
        $this->semesterFilter = $studyPlan->semester_no ?? $this->semesterFilter;
    }

    private function loadActiveRegistration(): void
    {
        if (! $this->studentProfileId || ! $this->activeAcademicYearId) {
            return;
        }

        $registration = StudentRegistration::query()
            ->where('student_profile_id', $this->studentProfileId)
            ->where('academic_year_id', $this->activeAcademicYearId)
            ->latest('id')
            ->first();

        if (! $registration) {
            $this->hasActiveRegistration = false;
            $this->currentRegistrationId = null;
            $this->registrationSemesterNo = null;
            $this->registrationStatus = null;
            $this->isRegistrationApproved = false;

            return;
        }

        $this->hasActiveRegistration = true;
        $this->currentRegistrationId = $registration->id;
        $this->registrationSemesterNo = $registration->semester_no;
        $this->registrationStatus = $registration->registration_status;
        $this->isRegistrationApproved = $registration->registration_status === 'Approved';

        if ($registration->semester_no) {
            $this->semesterFilter = $registration->semester_no;
        }
    }

    private function loadStudyPlanPeriod(): void
    {
        if (! $this->activeAcademicYearId) {
            $this->isStudyPlanPeriodOpen = false;

            return;
        }

        $period = AcademicPeriod::query()
            ->where('academic_year_id', $this->activeAcademicYearId)
            ->where('type', 'Study Plan')
            ->where('is_active', true)
            ->where('start_at', '<=', now())
            ->where('end_at', '>=', now())
            ->orderBy('start_at')
            ->first();

        $this->isStudyPlanPeriodOpen = (bool) $period;
        $this->studyPlanPeriodName = $period?->name;
        $this->studyPlanPeriodRange = $period
            ? $period->start_at?->format('d M Y H:i') . ' - ' . $period->end_at?->format('d M Y H:i')
            : null;
    }

    private function loadSelectedCourses(): void
    {
        if (! $this->studyPlanId) {
            $this->selectedCourses = [];
            $this->totalCredits = 0;

            return;
        }

        $details = StudyPlanDetail::query()
            ->with(['courseOffering.course'])
            ->where('study_plan_id', $this->studyPlanId)
            ->latest('id')
            ->get();

        $this->selectedCourses = $details
            ->map(function (StudyPlanDetail $detail) {
                $offering = $detail->courseOffering;
                $course = $offering?->course;

                return [
                    'detail_id' => $detail->id,
                    'code' => $course?->code ?? '-',
                    'name' => $course?->name ?? ($offering?->label ?? '-'),
                    'credits' => $detail->credits ?? $offering?->credits ?? $course?->credits ?? 0,
                    'is_repeat' => (bool) $detail->is_repeat,
                ];
            })
            ->values()
            ->all();

        $this->totalCredits = (int) collect($this->selectedCourses)->sum('credits');
    }

    private function loadAvailableOfferings(): void
    {
        if (! $this->activeAcademicYearId || ! $this->registrationSemesterNo) {
            $this->availableOfferings = [];

            return;
        }

        $selectedOfferingIds = $this->studyPlanId
            ? StudyPlanDetail::query()
                ->where('study_plan_id', $this->studyPlanId)
                ->pluck('course_offering_id')
            : collect();

        $retakeCourseIds = $this->eligibleRetakeCourseIds();

        $query = CourseOffering::query()
            ->with('course')
            ->where('academic_year_id', $this->activeAcademicYearId)
            ->where('status', 'Open')
            ->whereNotIn('id', $selectedOfferingIds)
            ->where('semester_no', '<=', $this->registrationSemesterNo)
            ->orderBy('semester_no')
            ->orderBy('id');

        $query->where(function ($builder) use ($retakeCourseIds) {
            $builder->where('semester_no', $this->registrationSemesterNo);

            if ($this->showRetakeOfferings && $retakeCourseIds->isNotEmpty()) {
                $builder->orWhere(function ($retakeQuery) use ($retakeCourseIds) {
                    $retakeQuery
                        ->where('semester_no', '<', $this->registrationSemesterNo)
                        ->whereIn('course_id', $retakeCourseIds->all());
                });
            }
        });

        $this->availableOfferings = $query
            ->limit(100)
            ->get()
            ->map(function (CourseOffering $offering) {
                return [
                    'id' => $offering->id,
                    'code' => $offering->course?->code ?? '-',
                    'name' => $offering->course?->name ?? '-',
                    'credits' => $offering->credits ?? $offering->course?->credits ?? 0,
                    'semester_no' => $offering->semester_no,
                    'label' => $offering->label,
                    'delivery_mode' => $offering->delivery_mode,
                    'is_retake' => ($offering->semester_no ?? 0) < $this->registrationSemesterNo,
                ];
            })
            ->values()
            ->all();
    }

    private function eligibleRetakeCourseIds()
    {
        return TranscriptEntry::query()
            ->where('student_profile_id', $this->studentProfileId)
            ->whereIn('result_status', ['Failed', 'Incomplete'])
            ->pluck('course_id')
            ->unique()
            ->values();
    }

    private function ensureApprovedRegistration(): bool
    {
        if ($this->isRegistrationApproved) {
            return true;
        }

        session()->flash('error', 'KRS hanya bisa diproses jika registrasi semester sudah Approved.');

        return false;
    }

    private function ensureStudyPlanExists(): ?StudyPlan
    {
        if (! $this->studentProfileId || ! $this->activeAcademicYearId || ! $this->currentRegistrationId) {
            return null;
        }

        $studyPlan = StudyPlan::query()->firstOrCreate(
            [
                'student_profile_id' => $this->studentProfileId,
                'academic_year_id' => $this->activeAcademicYearId,
            ],
            [
                'student_registration_id' => $this->currentRegistrationId,
                'semester_no' => $this->registrationSemesterNo ?? $this->studentInfo['current_semester'],
                'status' => 'Draft',
                'created_by' => auth()->id(),
            ]
        );

        $needsSync =
            $studyPlan->student_registration_id !== $this->currentRegistrationId ||
            ($this->registrationSemesterNo !== null && $studyPlan->semester_no !== $this->registrationSemesterNo);

        if ($needsSync) {
            $studyPlan->update([
                'student_registration_id' => $this->currentRegistrationId,
                'semester_no' => $this->registrationSemesterNo ?? $studyPlan->semester_no,
                'updated_by' => auth()->id(),
            ]);

            $studyPlan->refresh();
        }

        $this->loadStudyPlan();

        return $studyPlan;
    }
};
?>

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-danger">Profil mahasiswa belum tersedia. Hubungi admin akademik.</div>
    @elseif (! $hasActiveAcademicYear)
        <div class="alert alert-warning">Tahun akademik aktif belum tersedia.</div>
    @elseif (! $hasActiveRegistration)
        <div class="alert alert-warning">
            Registrasi semester untuk tahun akademik aktif belum tersedia. Silakan isi registrasi semester terlebih dahulu.
        </div>
    @elseif (! $isRegistrationApproved)
        <div class="alert alert-warning">
            Registrasi semester Anda belum <strong>Approved</strong>. KRS dapat diambil setelah registrasi disetujui.
        </div>
    @else
        <div class="row row-deck row-cards">
            <div class="col-lg-4">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-secondary mb-1">Mahasiswa</div>
                        <div class="font-weight-medium">{{ $studentInfo['name'] }}</div>
                        <div class="text-secondary small">{{ $studentInfo['nim'] }} · {{ $studentInfo['study_program'] }}</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-secondary mb-1">Tahun Akademik Aktif</div>
                        <div class="font-weight-medium">{{ $activeAcademicYearName }}</div>
                        <div class="text-secondary small mt-1">
                            Status KRS:
                            <span class="badge {{ $this->statusBadgeClass($studyPlanStatus) }}">{{ $studyPlanStatus ?? '-' }}</span>
                        </div>
                        <div class="text-secondary small mt-1">
                            Registration: {{ $registrationStatus ?? '-' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-secondary mb-1">Total SKS Dipilih</div>
                        <div class="h2 m-0">{{ number_format($totalCredits) }}</div>
                        <div class="text-secondary small mt-1">Submitted At: {{ $submittedAt ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-deck row-cards mt-1">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title">Available Course Offerings</h3>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" id="showRetakeOfferings" wire:model.live="showRetakeOfferings">
                            <label class="form-check-label text-secondary" for="showRetakeOfferings">
                                Tampilkan opsi retake
                            </label>
                        </div>
                    </div>
                    <div class="card-body border-bottom py-2">
                        <span class="text-secondary small">
                            Semester registrasi aktif: {{ $registrationSemesterNo ?? '-' }}.
                            Matkul semester atas tidak diperbolehkan.
                        </span>
                    </div>
                    <div class="card-body p-0">
                        @if (count($availableOfferings))
                            <div class="table-responsive">
                                <table class="table table-vcenter card-table">
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Mata Kuliah</th>
                                            <th>SKS</th>
                                            <th>Semester</th>
                                            <th>Mode</th>
                                            <th class="text-end">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($availableOfferings as $offering)
                                            <tr>
                                                <td>{{ $offering['code'] }}</td>
                                                <td>
                                                    <div class="font-weight-medium">{{ $offering['name'] }}</div>
                                                    <div class="text-secondary small">{{ $offering['label'] ?? '-' }}</div>
                                                </td>
                                                <td>{{ $offering['credits'] }}</td>
                                                <td>{{ $offering['semester_no'] ?? '-' }}</td>
                                                <td>{{ $offering['delivery_mode'] }}</td>
                                                <td class="text-end">
                                                    @if ($offering['is_retake'])
                                                        <span class="badge bg-yellow-lt me-2">Retake</span>
                                                    @endif
                                                    <button
                                                        type="button"
                                                        class="btn  btn-primary"
                                                        wire:click="addCourse({{ $offering['id'] }})"
                                                        @disabled(! $canModifyStudyPlan)
                                                    >
                                                        <i class="fas fa-plus me-1"></i> Ambil
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="p-3 text-secondary">Tidak ada course offering tersedia untuk filter saat ini.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title">Selected Courses (KRS)</h3>
                        <div class="d-flex gap-2">
                            @if ($studyPlanStatus === 'Submitted')
                                <button type="button" class="btn  btn-outline-secondary" wire:click="reviseStudyPlan">
                                    <i class="fas fa-rotate-left me-1"></i> Revisi
                                </button>
                            @endif

                            <button
                                type="button"
                                class="btn  btn-primary"
                                wire:click="submitStudyPlan"
                                @disabled(! $canModifyStudyPlan || ! $isStudyPlanPeriodOpen || count($selectedCourses) < 1)
                            >
                                <i class="fas fa-paper-plane me-1"></i> Submit KRS
                            </button>
                        </div>
                    </div>
                    <div class="card-body border-bottom py-2">
                        @if ($isStudyPlanPeriodOpen)
                            <span class="text-secondary small">
                                Periode aktif: {{ $studyPlanPeriodName }} ({{ $studyPlanPeriodRange }})
                            </span>
                        @else
                            <span class="text-danger small">Periode submit KRS belum aktif.</span>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        @if (count($selectedCourses))
                            <div class="table-responsive">
                                <table class="table table-vcenter card-table">
                                    <thead>
                                        <tr>
                                            <th>Mata Kuliah</th>
                                            <th>SKS</th>
                                            <th class="text-end">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($selectedCourses as $course)
                                            <tr>
                                                <td>
                                                    <div class="font-weight-medium">{{ $course['name'] }}</div>
                                                    <div class="text-secondary small">{{ $course['code'] }}</div>
                                                    @if ($course['is_repeat'])
                                                        <span class="badge bg-yellow-lt mt-1">Retake</span>
                                                    @endif
                                                </td>
                                                <td>{{ $course['credits'] }}</td>
                                                <td class="text-end">
                                                    <button
                                                        type="button"
                                                        class="btn  btn-outline-danger"
                                                        wire:click="removeCourse({{ $course['detail_id'] }})"
                                                        @disabled(! $canModifyStudyPlan)
                                                    >
                                                        <i class="fas fa-trash me-1"></i> Hapus
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th>Total SKS</th>
                                            <th colspan="2">{{ number_format($totalCredits) }}</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else
                            <div class="p-3 text-secondary">Belum ada mata kuliah yang dipilih.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
