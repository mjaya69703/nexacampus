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

@push('styles')
    <style>
        .modern-card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            background: white;
        }

        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }

        .hero-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 15s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .sks-counter {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            position: relative;
        }

        .sks-counter-inner {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .course-card {
            padding: 1rem;
            border-radius: 12px;
            background: #f8fafc;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .course-card:hover {
            background: #f1f5f9;
            border-color: #667eea;
            transform: translateX(4px);
        }

        .selected-course-card {
            padding: 1rem;
            border-radius: 12px;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }

        .selected-course-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .filter-badge {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
    </style>
@endpush

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
        {{-- Hero Section --}}
        <div class="card modern-card hero-gradient mb-4" style="color: white;">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-start gap-3">
                            <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; backdrop-filter: blur(10px);">
                                <i class="fas fa-list-check"></i>
                            </div>

                            <div>
                                <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Kartu Rencana Studi (KRS)</div>
                                <h1 class="h2 mb-2" style="font-weight: 700;">{{ $studentInfo['name'] }}</h1>
                                <div style="opacity: 0.9; margin-bottom: 1rem;">
                                    <i class="fas fa-id-card me-2"></i>{{ $studentInfo['nim'] }} • {{ $studentInfo['study_program'] }}
                                </div>

                                <div class="d-flex flex-wrap gap-2">
                                    <span class="filter-badge">
                                        <i class="fas fa-calendar me-2"></i>{{ $activeAcademicYearName ?? '-' }}
                                    </span>
                                    <span class="filter-badge">
                                        <i class="fas fa-graduation-cap me-2"></i>Semester {{ $registrationSemesterNo ?? '-' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 text-center">
                        <div class="sks-counter" style="background: conic-gradient(#fbbf24 {{ min(($totalCredits / 24) * 100, 100) }}%, rgba(255,255,255,0.2) 0%);">
                            <div class="sks-counter-inner">
                                <div style="font-size: 2.5rem; font-weight: 700; color: #f59e0b;">{{ $totalCredits }}</div>
                                <div style="font-size: 0.85rem; color: #6b7280;">Total SKS</div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge {{ $this->statusBadgeClass($studyPlanStatus) }}" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                                Status: {{ $studyPlanStatus ?? '-' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Content --}}
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card modern-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                        <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;"><i class="fas fa-book me-2" style="color: #3b82f6;"></i>Mata Kuliah Tersedia</h3>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" id="showRetakeOfferings" wire:model.live="showRetakeOfferings">
                            <label class="form-check-label text-secondary" for="showRetakeOfferings" style="font-size: 0.85rem;">
                                <i class="fas fa-redo me-1"></i>Tampilkan opsi retake
                            </label>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div style="padding: 0.75rem; border-radius: 8px; background: #dbeafe; margin-bottom: 1rem;">
                            <span style="font-size: 0.85rem; color: #1e40af;">
                                <i class="fas fa-info-circle me-1"></i>
                                Semester registrasi aktif: <strong>{{ $registrationSemesterNo ?? '-' }}</strong>.
                                Matkul semester atas tidak diperbolehkan.
                            </span>
                        </div>

                        @if (count($availableOfferings))
                            @foreach ($availableOfferings as $offering)
                                <div class="course-card">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; color: #1f2937; margin-bottom: 0.25rem;">{{ $offering['name'] }}</div>
                                            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.5rem;">
                                                <i class="fas fa-hashtag me-1" style="color: #667eea;"></i>{{ $offering['code'] }}
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-layer-group me-1" style="color: #10b981;"></i>{{ $offering['credits'] }} SKS
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-calendar me-1" style="color: #f59e0b;"></i>Semester {{ $offering['semester_no'] ?? '-' }}
                                            </div>
                                            <div style="font-size: 0.8rem; color: #9ca3af;">
                                                <i class="fas fa-tag me-1"></i>{{ $offering['label'] ?? '-' }}
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-wifi me-1"></i>{{ $offering['delivery_mode'] }}
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            @if ($offering['is_retake'])
                                                <span class="badge bg-yellow-lt text-yellow mb-2" style="font-size: 0.75rem; padding: 0.35rem 0.65rem;">
                                                    <i class="fas fa-redo me-1"></i>Retake
                                                </span>
                                            @endif
                                            <button
                                                type="button"
                                                class="action-btn"
                                                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;"
                                                wire:click="addCourse({{ $offering['id'] }})"
                                                @disabled(! $canModifyStudyPlan)
                                            >
                                                <i class="fas fa-plus me-1"></i> Ambil
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="p-4 text-center text-secondary">
                                <i class="fas fa-inbox" style="font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 0.5rem;"></i>
                                Tidak ada course offering tersedia untuk filter saat ini.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card modern-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                        <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;"><i class="fas fa-check-circle me-2" style="color: #10b981;"></i>KRS Saya</h3>
                        <div class="d-flex gap-2">
                            @if ($studyPlanStatus === 'Submitted')
                                <button type="button" class="btn btn-outline-secondary action-btn" wire:click="reviseStudyPlan" style="border-radius: 8px;">
                                    <i class="fas fa-rotate-left me-1"></i> Revisi
                                </button>
                            @endif

                            <button
                                type="button"
                                class="action-btn"
                                style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border-radius: 8px;"
                                wire:click="submitStudyPlan"
                                @disabled(! $canModifyStudyPlan || ! $isStudyPlanPeriodOpen || count($selectedCourses) < 1)
                            >
                                <i class="fas fa-paper-plane me-1"></i> Submit KRS
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        @if ($isStudyPlanPeriodOpen)
                            <div style="padding: 0.75rem; border-radius: 8px; background: #d1fae5; margin-bottom: 1rem;">
                                <span style="font-size: 0.85rem; color: #065f46;">
                                    <i class="fas fa-clock me-1"></i>
                                    Periode aktif: <strong>{{ $studyPlanPeriodName }}</strong><br>
                                    {{ $studyPlanPeriodRange }}
                                </span>
                            </div>
                        @else
                            <div style="padding: 0.75rem; border-radius: 8px; background: #fee2e2; margin-bottom: 1rem;">
                                <span style="font-size: 0.85rem; color: #991b1b;">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    Periode submit KRS belum aktif.
                                </span>
                            </div>
                        @endif

                        @if (count($selectedCourses))
                            @foreach ($selectedCourses as $course)
                                <div class="selected-course-card">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; color: #1f2937; margin-bottom: 0.25rem;">{{ $course['name'] }}</div>
                                            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.5rem;">
                                                <i class="fas fa-hashtag me-1" style="color: #667eea;"></i>{{ $course['code'] }}
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-layer-group me-1" style="color: #10b981;"></i>{{ $course['credits'] }} SKS
                                            </div>
                                            @if ($course['is_repeat'])
                                                <span class="badge bg-yellow-lt text-yellow" style="font-size: 0.75rem; padding: 0.35rem 0.65rem;">
                                                    <i class="fas fa-redo me-1"></i>Retake
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-end">
                                            <button
                                                type="button"
                                                class="action-btn"
                                                style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white;"
                                                wire:click="removeCourse({{ $course['detail_id'] }})"
                                                @disabled(! $canModifyStudyPlan)
                                            >
                                                <i class="fas fa-trash me-1"></i> Hapus
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            {{-- Total SKS Summary --}}
                            <div style="margin-top: 1rem; padding: 1rem; border-radius: 12px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); text-align: center;">
                                <div style="font-size: 0.85rem; color: #1e40af; margin-bottom: 0.25rem;">Total SKS Dipilih</div>
                                <div style="font-size: 2rem; font-weight: 700; color: #1e40af;">{{ number_format($totalCredits) }}</div>
                                <div style="font-size: 0.75rem; color: #3b82f6; margin-top: 0.25rem;">
                                    <i class="fas fa-info-circle me-1"></i>Maksimal 24 SKS per semester
                                </div>
                            </div>
                        @else
                            <div class="p-4 text-center text-secondary">
                                <i class="fas fa-inbox" style="font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 0.5rem;"></i>
                                Belum ada mata kuliah yang dipilih.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
