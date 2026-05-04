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

        $validated = $this->validate([
            'form.semester_no' => ['nullable', 'integer', 'min:1', 'max:14'],
            'form.academic_status' => ['required', Rule::in(['Aktif', 'Cuti', 'Nonaktif', 'Lulus', 'Drop Out', 'Keluar'])],
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
        .student-registration-card {
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
        }

        .student-registration-hero {
            background:
                radial-gradient(circle at top right, rgba(32, 107, 196, 0.12), transparent 28%),
                linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .student-registration-label {
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .student-registration-meta {
            border-radius: 16px;
            background: #f8fafc;
            padding: 14px 16px;
            height: 100%;
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
        <div class="card student-registration-card student-registration-hero mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <div class="student-registration-label mb-2">Registrasi Semester</div>
                        <h2 class="mb-2">{{ $studentInfo['name'] }}</h2>
                        <div class="text-secondary mb-3">
                            {{ $studentInfo['study_program'] }} • {{ $studentInfo['faculty'] }}
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-blue-lt text-blue">NIM {{ $studentInfo['nim'] }}</span>
                            <span class="badge bg-azure-lt text-azure">Semester {{ $studentInfo['current_semester'] ?? '-' }}</span>
                            <span class="badge bg-green-lt text-green">{{ $activeAcademicYearName }}</span>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="student-registration-meta">
                                    <div class="student-registration-label">Status Registrasi</div>
                                    <div class="mt-2">
                                        <span class="badge {{ $this->statusBadgeClass($currentRegistrationStatus) }}">
                                            {{ $currentRegistrationStatus ?? '-' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="student-registration-meta">
                                    <div class="student-registration-label">Status Akademik</div>
                                    <div class="mt-2 fw-semibold">{{ $currentAcademicStatus ?? $form['academic_status'] ?? '-' }}</div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="student-registration-meta">
                                    <div class="student-registration-label">Periode Aktif</div>
                                    @if ($canRegister)
                                        <div class="fw-semibold mt-2">{{ $activePeriodName }}</div>
                                        <div class="text-secondary small">{{ $activePeriodRange }}</div>
                                    @else
                                        <div class="text-secondary mt-2">Periode registrasi belum aktif atau sudah berakhir.</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cards">
            <div class="col-lg-8">
                <div class="card student-registration-card">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Form Registrasi Semester</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert {{ $canRegister ? 'alert-success' : 'alert-warning' }} mb-4">
                            <div class="fw-semibold mb-1">Tahun akademik aktif: {{ $activeAcademicYearName }}</div>
                            <div class="small">
                                @if ($canRegister)
                                    Registrasi dapat diajukan pada periode {{ $activePeriodName }} ({{ $activePeriodRange }}).
                                @else
                                    Registrasi masih bisa disimpan sebagai draft, tetapi pengajuan menunggu periode aktif.
                                @endif
                            </div>
                        </div>

                        <form wire:submit.prevent="saveDraft">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Semester</label>
                                    <input
                                        type="number"
                                        class="form-control"
                                        min="1"
                                        max="14"
                                        wire:model="form.semester_no"
                                        @disabled(! $canEditRegistration)
                                    >
                                    @error('form.semester_no')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status Akademik</label>
                                    <select class="form-select" wire:model="form.academic_status" @disabled(! $canEditRegistration)>
                                        <option value="Aktif">Aktif</option>
                                        <option value="Cuti">Cuti</option>
                                        <option value="Nonaktif">Nonaktif</option>
                                        <option value="Lulus">Lulus</option>
                                        <option value="Drop Out">Drop Out</option>
                                        <option value="Keluar">Keluar</option>
                                    </select>
                                    @error('form.academic_status')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Catatan</label>
                                <textarea
                                    class="form-control"
                                    rows="4"
                                    wire:model="form.notes"
                                    @disabled(! $canEditRegistration)
                                    placeholder="Tambahkan catatan jika dibutuhkan"
                                ></textarea>
                                @error('form.notes')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-secondary" @disabled(! $canEditRegistration)>
                                    Simpan Draft
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    wire:click="submitRegistration"
                                    @disabled(! $canEditRegistration || ! $canRegister)
                                >
                                    Ajukan Registrasi
                                </button>

                                @if (in_array($currentRegistrationStatus, ['Draft', 'Submitted'], true))
                                    <button type="button" class="btn btn-outline-danger" wire:click="cancelSubmission">
                                        Batalkan Pengajuan
                                    </button>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card student-registration-card">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Status Saat Ini</h3>
                    </div>
                    <div class="card-body">
                        <div class="student-registration-label">Registrasi</div>
                        <div class="mb-3 mt-2">
                            <span class="badge {{ $this->statusBadgeClass($currentRegistrationStatus) }}">
                                {{ $currentRegistrationStatus ?? '-' }}
                            </span>
                        </div>

                        <div class="student-registration-label">Status Akademik</div>
                        <div class="fw-semibold mb-3 mt-2">{{ $currentAcademicStatus ?? '-' }}</div>

                        <div class="student-registration-label">Diajukan Pada</div>
                        <div class="mb-3 mt-2">{{ $submittedAt ?? '-' }}</div>

                        <div class="student-registration-label">Disetujui Pada</div>
                        <div class="mt-2">{{ $approvedAt ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card student-registration-card mt-4">
            <div class="card-header">
                <h3 class="card-title mb-0">Riwayat Registrasi</h3>
            </div>
            <div class="card-body p-0">
                @if (count($registrationHistory))
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
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
                                        <td>{{ $item['academic_year'] }}</td>
                                        <td>{{ $item['semester_no'] ?? '-' }}</td>
                                        <td>
                                            <span class="badge {{ $this->statusBadgeClass($item['registration_status']) }}">
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
                    <div class="p-4 text-secondary">Belum ada riwayat registrasi semester.</div>
                @endif
            </div>
        </div>
    @endif
</div>
