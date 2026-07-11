<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Support\Notifications\NotificationDispatchService;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public $studentProfileId = '';
    public $academicYearId = '';
    public $studentRegistrationId = '';
    public $semesterNo = '';
    public $status = 'Draft';
    public $notes = '';

    public $studentProfiles = [];
    public $academicYears = [];
    public $studentRegistrations = [];

    public function mount(): void
    {
        $this->studentProfiles = StudentProfile::with('user', 'studyProgram')
            ->orderByDesc('created_at')
            ->get();

        $this->academicYears = AcademicYear::orderByDesc('created_at')->get();
    }

    public function updatedStudentProfileId(): void
    {
        $this->loadStudentRegistrations();
    }

    public function updatedAcademicYearId(): void
    {
        $this->loadStudentRegistrations();
    }

    public function loadStudentRegistrations(): void
    {
        if (! $this->studentProfileId || ! $this->academicYearId) {
            $this->studentRegistrations = [];
            $this->studentRegistrationId = '';

            return;
        }

        $this->studentRegistrations = StudentRegistration::query()
            ->where('student_profile_id', $this->studentProfileId)
            ->where('academic_year_id', $this->academicYearId)
            ->orderByDesc('created_at')
            ->get();

        if ($this->studentRegistrations->isEmpty()) {
            $this->studentRegistrationId = '';
        }
    }

    public function save(): void
    {
        $this->validate([
            'studentProfileId' => [
                'required',
                'exists:student_profiles,id',
                Rule::unique('study_plans', 'student_profile_id')
                    ->where('academic_year_id', $this->academicYearId)
                    ->whereNull('deleted_at'),
            ],
            'academicYearId' => 'required|exists:academic_years,id',
            'studentRegistrationId' => 'nullable|exists:student_registrations,id',
            'semesterNo' => 'nullable|integer|min:1|max:14',
            'status' => 'required|in:Draft,Submitted,Approved,Rejected,Cancelled',
            'notes' => 'nullable|string',
        ], [
            'studentProfileId.unique' => 'KRS untuk mahasiswa dan tahun akademik ini sudah ada.',
        ]);

        try {
            $studyPlan = StudyPlan::create([
                'student_profile_id' => $this->studentProfileId,
                'academic_year_id' => $this->academicYearId,
                'student_registration_id' => $this->studentRegistrationId ?: null,
                'semester_no' => $this->semesterNo ?: null,
                'status' => $this->status,
                'notes' => $this->notes ?: null,
                'submitted_at' => $this->status === 'Submitted' ? now() : null,
                'approved_at' => $this->status === 'Approved' ? now() : null,
                'approved_by' => $this->status === 'Approved' ? auth()->id() : null,
                'created_by' => auth()->id(),
            ]);

            if (in_array($studyPlan->status, ['Submitted', 'Approved', 'Rejected', 'Cancelled'], true)) {
                app(NotificationDispatchService::class)->studyPlanStatusUpdated($studyPlan, $studyPlan->notes);
            }

            session()->flash('success', 'Header KRS berhasil dibuat. Silakan lengkapi detail mata kuliah.');

            $this->redirectRoute('admin.academic.study-plans.edit', ['id' => $studyPlan->id]);
        } catch (\Throwable $th) {
            session()->flash('error', 'Terjadi kesalahan: ' . $th->getMessage());
        }
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.study-plans.index');
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Buat KRS Baru',
            ]);
    }
};
?>

<div class="row">
    <div class="col-12">
        <x-alert />

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Form Header KRS</h5>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mahasiswa <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="studentProfileId" required>
                                <option value="">Pilih Mahasiswa</option>
                                @foreach($studentProfiles as $profile)
                                    <option value="{{ $profile->id }}">
                                        {{ $profile->user?->name }} ({{ $profile->nim }}) - {{ $profile->studyProgram?->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('studentProfileId') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tahun Akademik <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="academicYearId" required>
                                <option value="">Pilih Tahun Akademik</option>
                                @foreach($academicYears as $year)
                                    <option value="{{ $year->id }}">{{ $year->name }}</option>
                                @endforeach
                            </select>
                            @error('academicYearId') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Student Registration</label>
                            <select class="form-select" wire:model="studentRegistrationId">
                                <option value="">Pilih Registration (Opsional)</option>
                                @foreach($studentRegistrations as $registration)
                                    <option value="{{ $registration->id }}">
                                        #{{ $registration->id }} - {{ $registration->registration_status }}
                                    </option>
                                @endforeach
                            </select>
                            @error('studentRegistrationId') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Semester</label>
                            <input type="number" min="1" max="14" class="form-control" wire:model="semesterNo" placeholder="Opsional">
                            @error('semesterNo') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="status" required>
                                <option value="Draft">Draft</option>
                                <option value="Submitted">Submitted</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                            @error('status') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Catatan</label>
                            <textarea class="form-control" rows="3" wire:model="notes" placeholder="Opsional"></textarea>
                            @error('notes') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan dan Lanjut Edit Detail
                        </button>
                        <button type="button" class="btn btn-secondary" wire:click="cancel">
                            <i class="fas fa-times me-1"></i> Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
