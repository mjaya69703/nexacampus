<?php

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentRegistration;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public ?int $studentProfileId = null;
    public ?int $activeAcademicYearId = null;
    public ?string $activeAcademicYearName = null;
    public ?string $activePeriodName = null;
    public ?string $activePeriodRange = null;
    public bool $canRegister = false;
    public bool $hasProfile = false;
    public bool $hasActiveAcademicYear = false;
    public bool $canEditRegistration = true;
    public array $form = [];
    public array $studentInfo = [];
    public ?int $currentRegistrationId = null;
    public ?string $currentRegistrationStatus = null;
    public ?string $currentAcademicStatus = null;
    public ?string $submittedAt = null;
    public ?string $approvedAt = null;
    public array $registrationHistory = [];

    public function mount(): void
    {
        $this->initializeDefaults();

        $user = auth()->user();

        if (! $user) {
            return;
        }

        $studentProfile = $user->studentProfile()
            ->with(['studyProgram.faculty'])
            ->first();

        if (! $studentProfile) {
            return;
        }

        $this->hasProfile = true;
        $this->studentProfileId = $studentProfile->id;
        $this->form['academic_status'] = $studentProfile->academic_status;
        $this->form['semester_no'] = $studentProfile->current_semester;

        $this->studentInfo = [
            'name' => $user->name,
            'nim' => $studentProfile->nim,
            'study_program' => $studentProfile->studyProgram?->name ?? '-',
            'faculty' => $studentProfile->studyProgram?->faculty?->name ?? '-',
            'current_semester' => $studentProfile->current_semester,
        ];

        $activeAcademicYear = AcademicYear::query()
            ->where('is_active', true)
            ->latest('start_date')
            ->first();

        if (! $activeAcademicYear) {
            $this->loadHistory();

            return;
        }

        $this->hasActiveAcademicYear = true;
        $this->activeAcademicYearId = $activeAcademicYear->id;
        $this->activeAcademicYearName = $activeAcademicYear->name;

        $this->loadCurrentRegistration();
        $this->loadActiveRegistrationPeriod();
        $this->loadHistory();
    }

    public function saveDraft(): void
    {
        $this->upsertRegistration('Draft');
    }

    public function submitRegistration(): void
    {
        $this->upsertRegistration('Submitted');
    }

    public function cancelSubmission(): void
    {
        if (! $this->currentRegistrationId) {
            return;
        }

        $registration = StudentRegistration::query()
            ->whereKey($this->currentRegistrationId)
            ->where('student_profile_id', $this->studentProfileId)
            ->first();

        if (! $registration) {
            return;
        }

        if (! in_array($registration->registration_status, ['Draft', 'Submitted'], true)) {
            session()->flash('error', 'Status registrasi saat ini tidak dapat dibatalkan.');

            return;
        }

        $registration->update([
            'registration_status' => 'Cancelled',
            'is_active' => false,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Registrasi semester berhasil dibatalkan.');
        $this->loadCurrentRegistration();
        $this->loadHistory();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Semester Registration',
        ]);
    }

    public function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'Approved' => 'bg-green-lt text-green',
            'Submitted' => 'bg-blue-lt text-blue',
            'Draft' => 'bg-yellow-lt text-yellow',
            'Rejected', 'Cancelled' => 'bg-red-lt text-red',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    private function initializeDefaults(): void
    {
        $this->form = [
            'semester_no' => null,
            'academic_status' => 'Aktif',
            'notes' => '',
        ];

        $this->studentInfo = [
            'name' => '-',
            'nim' => '-',
            'study_program' => '-',
            'faculty' => '-',
            'current_semester' => null,
        ];
    }

    private function loadCurrentRegistration(): void
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
            $this->currentRegistrationId = null;
            $this->currentRegistrationStatus = null;
            $this->currentAcademicStatus = null;
            $this->submittedAt = null;
            $this->approvedAt = null;
            $this->canEditRegistration = true;

            return;
        }

        $this->currentRegistrationId = $registration->id;
        $this->currentRegistrationStatus = $registration->registration_status;
        $this->currentAcademicStatus = $registration->academic_status;
        $this->submittedAt = $registration->submitted_at?->format('d M Y H:i');
        $this->approvedAt = $registration->approved_at?->format('d M Y H:i');

        $this->form['semester_no'] = $registration->semester_no;
        $this->form['academic_status'] = $registration->academic_status;
        $this->form['notes'] = $registration->notes ?? '';

        $this->canEditRegistration = in_array($registration->registration_status, ['Draft', 'Rejected'], true);
    }

    private function loadActiveRegistrationPeriod(): void
    {
        if (! $this->activeAcademicYearId) {
            return;
        }

        $now = now();

        $period = AcademicPeriod::query()
            ->where('academic_year_id', $this->activeAcademicYearId)
            ->where('type', 'Student Registration')
            ->where('is_active', true)
            ->where('start_at', '<=', $now)
            ->where('end_at', '>=', $now)
            ->orderBy('start_at')
            ->first();

        $this->canRegister = (bool) $period;

        if (! $period) {
            return;
        }

        $this->activePeriodName = $period->name;
        $this->activePeriodRange = $period->start_at?->format('d M Y H:i') . ' - ' . $period->end_at?->format('d M Y H:i');
    }

    private function loadHistory(): void
    {
        if (! $this->studentProfileId) {
            $this->registrationHistory = [];

            return;
        }

        $this->registrationHistory = StudentRegistration::query()
            ->with('academicYear')
            ->where('student_profile_id', $this->studentProfileId)
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function (StudentRegistration $registration) {
                return [
                    'academic_year' => $registration->academicYear?->name ?? '-',
                    'semester_no' => $registration->semester_no,
                    'registration_status' => $registration->registration_status,
                    'academic_status' => $registration->academic_status,
                    'registration_date' => $registration->registration_date?->format('d M Y') ?? '-',
                    'submitted_at' => $registration->submitted_at?->format('d M Y H:i') ?? '-',
                ];
            })
            ->all();
    }

    private function upsertRegistration(string $status): void
    {
        if (! $this->hasProfile) {
            session()->flash('error', 'Profil mahasiswa belum tersedia.');

            return;
        }

        if (! $this->hasActiveAcademicYear || ! $this->activeAcademicYearId) {
            session()->flash('error', 'Tahun akademik aktif belum tersedia.');

            return;
        }

        if ($status === 'Submitted' && ! $this->canRegister) {
            session()->flash('error', 'Periode registrasi semester belum aktif.');

            return;
        }

        if ($this->currentRegistrationId && ! $this->canEditRegistration) {
            session()->flash('error', 'Registrasi saat ini tidak dapat diubah.');

            return;
        }

        if (($this->form['academic_status'] ?? null) === 'Cuti') {
            session()->flash('error', 'Pengajuan cuti akademik dilakukan melalui menu Layanan > Cuti Akademik.');

            return;
        }

        $validated = $this->validate([
            'form.semester_no' => ['nullable', 'integer', 'min:1', 'max:14'],
            'form.academic_status' => ['required', Rule::in(['Aktif', 'Nonaktif', 'Lulus', 'Drop Out', 'Keluar'])],
            'form.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payload = [
            'student_profile_id' => $this->studentProfileId,
            'academic_year_id' => $this->activeAcademicYearId,
            'semester_no' => $validated['form']['semester_no'],
            'academic_status' => $validated['form']['academic_status'],
            'registration_status' => $status,
            'registration_date' => Carbon::today(),
            'submitted_at' => $status === 'Submitted' ? now() : null,
            'notes' => $validated['form']['notes'] ?: null,
            'is_active' => true,
            'updated_by' => auth()->id(),
        ];

        if ($this->currentRegistrationId) {
            StudentRegistration::query()
                ->whereKey($this->currentRegistrationId)
                ->update($payload);
        } else {
            $payload['created_by'] = auth()->id();
            StudentRegistration::create($payload);
        }

        session()->flash(
            'success',
            $status === 'Draft'
                ? 'Draft registrasi semester berhasil disimpan.'
                : 'Registrasi semester berhasil diajukan.',
        );

        $this->loadCurrentRegistration();
        $this->loadHistory();
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

        .info-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 0.8rem;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            font-size: 0.85rem;
            color: white;
        }

        .stat-card {
            border-radius: 16px;
            padding: 1.5rem;
            background: white;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .form-card {
            border-radius: 16px;
            padding: 2rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .status-box {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .period-box {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border-radius: 16px;
            padding: 1.5rem;
        }

        .action-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .history-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .history-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .history-table th:first-child {
            border-radius: 12px 0 0 0;
        }

        .history-table th:last-child {
            border-radius: 0 12px 0 0;
        }

        .history-table td {
            padding: 1rem;
            background: white;
            border-bottom: 1px solid #e5e7eb;
        }

        .history-table tr:last-child td:first-child {
            border-radius: 0 0 0 12px;
        }

        .history-table tr:last-child td:last-child {
            border-radius: 0 0 12px 0;
        }

        .history-table tr:hover td {
            background: #f8fafc;
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-danger">
            Profil mahasiswa belum ditemukan. Hubungi admin akademik untuk sinkronisasi data mahasiswa.
        </div>
    @elseif (! $hasActiveAcademicYear)
        <div class="alert alert-warning">
            Tahun akademik aktif belum ditentukan. Registrasi semester belum dapat dilakukan.
        </div>
    @else
        {{-- Hero Section with Gradient --}}
        <div class="card modern-card hero-gradient mb-4" style="color: white;">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-start gap-3">
                            <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; backdrop-filter: blur(10px);">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div>
                                <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Registrasi Semester Mahasiswa</div>
                                <h1 class="h2 mb-2" style="font-weight: 700;">{{ $studentInfo['name'] }}</h1>
                                <div style="opacity: 0.9; margin-bottom: 1rem;">
                                    <i class="fas fa-university me-2"></i>{{ $studentInfo['study_program'] }} • {{ $studentInfo['faculty'] }}
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="info-badge">
                                        <i class="fas fa-id-card me-2"></i>NIM {{ $studentInfo['nim'] }}
                                    </span>
                                    <span class="info-badge">
                                        <i class="fas fa-calendar me-2"></i>Semester {{ $studentInfo['current_semester'] ?? '-' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="row g-3">
                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 0.5rem;">Status Registrasi</div>
                                    <span class="badge bg-white text-primary">{{ $currentRegistrationStatus ?? '-' }}</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 0.5rem;">Status Akademik</div>
                                    <div style="font-weight: 600;">{{ $currentAcademicStatus ?? $form['academic_status'] ?? '-' }}</div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 0.5rem;">Tahun Akademik</div>
                                    <div style="font-weight: 600;">{{ $activeAcademicYearName }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cards">
            <div class="col-lg-8">
                {{-- Form Card --}}
                <div class="modern-card form-card mb-4">
                    <div style="margin-bottom: 1.5rem;">
                        <h3 style="font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">
                            <i class="fas fa-edit me-2" style="color: #667eea;"></i>Form Registrasi Semester
                        </h3>
                        <p style="color: #6b7280; font-size: 0.9rem;">Lengkapi form berikut untuk melakukan registrasi semester</p>
                    </div>

                    {{-- Period Alert --}}
                    <div class="alert {{ $canRegister ? 'bg-green-lt' : 'bg-yellow-lt' }} mb-4" style="border-radius: 12px; border: none;">
                        <div style="display: flex; align-items: start; gap: 0.75rem;">
                            <i class="fas {{ $canRegister ? 'fa-check-circle' : 'fa-info-circle' }}" style="font-size: 1.25rem; color: {{ $canRegister ? '#10b981' : '#f59e0b' }};"></i>
                            <div>
                                <div style="font-weight: 600; color: #1f2937; margin-bottom: 0.25rem;">Tahun akademik aktif: {{ $activeAcademicYearName }}</div>
                                <div style="font-size: 0.85rem; color: #6b7280;">
                                    @if ($canRegister)
                                        Registrasi dapat diajukan pada periode {{ $activePeriodName }} ({{ $activePeriodRange }}).
                                    @else
                                        Registrasi masih bisa disimpan sebagai draft, tetapi pengajuan menunggu periode aktif.
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <form wire:submit.prevent="saveDraft">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-weight: 600; color: #374151;">
                                    <i class="fas fa-layer-group me-2" style="color: #667eea;"></i>Semester
                                </label>
                                <input
                                    type="number"
                                    class="form-control"
                                    min="1"
                                    max="14"
                                    wire:model="form.semester_no"
                                    @disabled(! $canEditRegistration)
                                    style="border-radius: 10px; padding: 0.75rem;"
                                >
                                @error('form.semester_no')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-weight: 600; color: #374151;">
                                    <i class="fas fa-user-check me-2" style="color: #667eea;"></i>Status Akademik
                                </label>
                                <select class="form-select" wire:model="form.academic_status" @disabled(! $canEditRegistration) style="border-radius: 10px; padding: 0.75rem;">
                                    <option value="Aktif">Aktif</option>
                                    @if (($form['academic_status'] ?? null) === 'Cuti')
                                        <option value="Cuti" disabled>Cuti - melalui layanan cuti akademik</option>
                                    @endif
                                    <option value="Nonaktif">Nonaktif</option>
                                    <option value="Lulus">Lulus</option>
                                    <option value="Drop Out">Drop Out</option>
                                    <option value="Keluar">Keluar</option>
                                </select>
                                <small class="text-muted d-block mt-1">
                                    Pengajuan cuti sekarang lewat
                                    <a href="{{ route('student.student-services.leaves') }}" class="fw-semibold">Layanan &gt; Cuti Akademik</a>
                                    agar bisa direview dengan lampiran dan history.
                                </small>
                                @error('form.academic_status')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" style="font-weight: 600; color: #374151;">
                                <i class="fas fa-sticky-note me-2" style="color: #667eea;"></i>Catatan
                            </label>
                            <textarea
                                class="form-control"
                                rows="4"
                                wire:model="form.notes"
                                @disabled(! $canEditRegistration)
                                placeholder="Tambahkan catatan jika dibutuhkan"
                                style="border-radius: 10px; padding: 0.75rem;"
                            ></textarea>
                            @error('form.notes')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="action-btn" @disabled(! $canEditRegistration)
                                    style="background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); color: white;">
                                <i class="fas fa-save"></i> Simpan Draft
                            </button>

                            <button type="button" class="action-btn" wire:click="submitRegistration"
                                    @disabled(! $canEditRegistration || ! $canRegister)
                                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                <i class="fas fa-paper-plane"></i> Ajukan Registrasi
                            </button>

                            @if (in_array($currentRegistrationStatus, ['Draft', 'Submitted'], true))
                                <button type="button" class="action-btn" wire:click="cancelSubmission"
                                        style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #dc2626;">
                                    <i class="fas fa-times-circle"></i> Batalkan Pengajuan
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                {{-- Status Box --}}
                <div class="status-box">
                    <div style="font-size: 0.85rem; color: #1e40af; margin-bottom: 1rem; font-weight: 600;">
                        <i class="fas fa-info-circle me-2"></i>Status Saat Ini
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; color: #3b82f6; margin-bottom: 0.25rem;">Registrasi</div>
                        <span class="badge {{ $this->statusBadgeClass($currentRegistrationStatus) }}" style="padding: 0.5rem 0.75rem;">
                            {{ $currentRegistrationStatus ?? '-' }}
                        </span>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; color: #3b82f6; margin-bottom: 0.25rem;">Status Akademik</div>
                        <div style="font-weight: 600; color: #1f2937;">{{ $currentAcademicStatus ?? '-' }}</div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; color: #3b82f6; margin-bottom: 0.25rem;">Diajukan Pada</div>
                        <div style="font-weight: 600; color: #1f2937;">{{ $submittedAt ?? '-' }}</div>
                    </div>

                    <div>
                        <div style="font-size: 0.8rem; color: #3b82f6; margin-bottom: 0.25rem;">Disetujui Pada</div>
                        <div style="font-weight: 600; color: #1f2937;">{{ $approvedAt ?? '-' }}</div>
                    </div>
                </div>

                {{-- Period Box --}}
                <div class="period-box">
                    <div style="font-size: 0.85rem; color: #065f46; margin-bottom: 0.5rem; font-weight: 600;">
                        <i class="fas fa-clock me-2"></i>Periode Aktif
                    </div>
                    @if ($canRegister)
                        <div style="font-weight: 600; color: #047857; margin-bottom: 0.5rem;">{{ $activePeriodName }}</div>
                        <div style="font-size: 0.85rem; color: #065f46;">{{ $activePeriodRange }}</div>
                    @else
                        <div style="font-size: 0.85rem; color: #065f46;">Periode registrasi belum aktif atau sudah berakhir.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- History Table --}}
        <div class="modern-card mt-4">
            <div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
                <h3 style="font-weight: 700; color: #1f2937; margin: 0;">
                    <i class="fas fa-history me-2" style="color: #667eea;"></i>Riwayat Registrasi
                </h3>
            </div>
            <div style="padding: 0;">
                @if (count($registrationHistory))
                    <div class="table-responsive">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Tahun Akademik</th>
                                    <th>Semester</th>
                                    <th>Status Registrasi</th>
                                    <th>Status Akademik</th>
                                    <th>Tanggal Registrasi</th>
                                    <th>Diajukan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($registrationHistory as $item)
                                    <tr>
                                        <td style="font-weight: 500;">{{ $item['academic_year'] }}</td>
                                        <td>{{ $item['semester_no'] ?? '-' }}</td>
                                        <td>
                                            <span class="badge {{ $this->statusBadgeClass($item['registration_status']) }}" style="padding: 0.5rem 0.75rem;">
                                                {{ $item['registration_status'] }}
                                            </span>
                                        </td>
                                        <td>{{ $item['academic_status'] }}</td>
                                        <td>{{ $item['registration_date'] }}</td>
                                        <td>{{ $item['submitted_at'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div style="padding: 3rem; text-align: center; color: #9ca3af;">
                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                        <div>Belum ada riwayat registrasi semester.</div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
