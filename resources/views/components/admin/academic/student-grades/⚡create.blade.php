<?php

use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudentGrade;
use App\Support\StudentGradeCalculator;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public $studyPlanDetailId = '';
    public $gradedBy = '';
    public $notes = '';

    public array $studyPlanDetails = [];
    public array $graders = [];

    public function mount(): void
    {
        $this->studyPlanDetails = StudyPlanDetail::query()
            ->whereDoesntHave('studentGrade')
            ->with([
                'studyPlan.studentProfile.user',
                'studyPlan.studentProfile.studyProgram',
                'studyPlan.academicYear',
                'courseOffering.course',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (StudyPlanDetail $detail) {
                return [
                    'id' => $detail->id,
                    'label' => ($detail->studyPlan?->studentProfile?->user?->name ?? '-') .
                        ' (' . ($detail->studyPlan?->studentProfile?->nim ?? '-') . ') - ' .
                        ($detail->courseOffering?->course?->code ?? '-') . ' - ' .
                        ($detail->courseOffering?->course?->name ?? '-'),
                ];
            })
            ->toArray();

        $this->loadEligibleGraders();
    }

    public function updatedStudyPlanDetailId(): void
    {
        $this->loadEligibleGraders();
    }

    protected function loadEligibleGraders(): void
    {
        if (! $this->studyPlanDetailId) {
            $this->graders = [];
            $this->gradedBy = '';

            return;
        }

        $studyPlanDetail = StudyPlanDetail::find($this->studyPlanDetailId);

        if (! $studyPlanDetail?->course_offering_id) {
            $this->graders = [];
            $this->gradedBy = '';

            return;
        }

        $this->graders = CourseOfferingLecturer::query()
            ->where('course_offering_id', $studyPlanDetail->course_offering_id)
            ->where('is_active', true)
            ->with('lecturerProfile.user')
            ->orderBy('sort_order')
            ->get()
            ->map(function (CourseOfferingLecturer $lecturer) {
                return [
                    'id' => $lecturer->lecturerProfile?->user_id,
                    'label' => $lecturer->lecturerProfile?->user?->name,
                ];
            })
            ->filter(fn (array $grader) => ! empty($grader['id']) && ! empty($grader['label']))
            ->unique('id')
            ->values()
            ->toArray();

        $eligibleIds = collect($this->graders)->pluck('id')->map(fn ($id) => (string) $id)->all();

        if ($this->gradedBy !== '' && ! in_array((string) $this->gradedBy, $eligibleIds, true)) {
            $this->gradedBy = '';
        }
    }

    protected function isEligibleGrader(int $userId, int $studyPlanDetailId): bool
    {
        $studyPlanDetail = StudyPlanDetail::find($studyPlanDetailId);

        if (! $studyPlanDetail?->course_offering_id) {
            return false;
        }

        return CourseOfferingLecturer::query()
            ->where('course_offering_id', $studyPlanDetail->course_offering_id)
            ->where('is_active', true)
            ->whereHas('lecturerProfile', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->exists();
    }

    public function save(): void
    {
        $this->validate([
            'studyPlanDetailId' => [
                'required',
                'exists:study_plan_details,id',
                Rule::unique('student_grades', 'study_plan_detail_id')->whereNull('deleted_at'),
            ],
            'gradedBy' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ], [
            'studyPlanDetailId.unique' => 'Study plan detail ini sudah punya nilai.',
        ]);

        if (
            $this->gradedBy !== ''
            && ! $this->isEligibleGrader((int) $this->gradedBy, (int) $this->studyPlanDetailId)
        ) {
            $this->addError('gradedBy', 'Penilai harus dosen pengajar pada course offering ini.');

            return;
        }

        try {
            $grade = StudentGrade::create([
                'study_plan_detail_id' => $this->studyPlanDetailId,
                'grade_status' => 'Draft',
                'graded_by' => $this->gradedBy !== '' ? $this->gradedBy : null,
                'notes' => $this->notes !== '' ? $this->notes : null,
                'created_by' => auth()->id(),
            ]);

            $calculator = new StudentGradeCalculator();
            $snapshot = $calculator->buildSnapshot($grade->load('components'));
            $grade->update(array_merge($snapshot, [
                'graded_at' => $grade->graded_by ? now() : null,
            ]));

            session()->flash('success', 'Header nilai berhasil dibuat. Lengkapi komponen dan finalize saat total bobot sudah 100%.');

            $this->redirectRoute('admin.academic.student-grades.edit', ['id' => $grade->id]);
        } catch (\Throwable $th) {
            session()->flash('error', 'Terjadi kesalahan: ' . $th->getMessage());
        }
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.student-grades.index');
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Buat Nilai Mahasiswa',
            ]);
    }
};
?>

<div class="row">
    <div class="col-12">
        <x-alert />

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Form Header Nilai Mahasiswa</h5>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Study Plan Detail <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="studyPlanDetailId" required>
                                <option value="">Pilih Study Plan Detail</option>
                                @foreach($studyPlanDetails as $detail)
                                    <option value="{{ $detail['id'] }}">{{ $detail['label'] }}</option>
                                @endforeach
                            </select>
                            @error('studyPlanDetailId') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Graded By</label>
                            <select class="form-select" wire:model="gradedBy" @disabled(! $studyPlanDetailId)>
                                <option value="">Pilih Penilai (Dosen Offering)</option>
                                @foreach($graders as $grader)
                                    <option value="{{ $grader['id'] }}">{{ $grader['label'] }}</option>
                                @endforeach
                            </select>
                            @error('gradedBy') <span class="text-danger">{{ $message }}</span> @enderror
                            @if (! $studyPlanDetailId)
                                <small class="text-muted">Pilih study plan detail terlebih dahulu untuk memuat dosen pengajar.</small>
                            @endif
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status Lifecycle</label>
                            <input type="text" class="form-control" value="Draft" readonly>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Catatan</label>
                            <textarea class="form-control" rows="3" wire:model="notes"></textarea>
                            @error('notes') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Nilai akhir (final score, letter grade, grade point, result status) dihitung otomatis dari komponen nilai.
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Simpan dan Lanjut Edit Komponen
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
