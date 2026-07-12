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

<div>
    <x-alert />

    <x-admin.academic.header
        title="Buat Nilai Mahasiswa (KHS)"
        description="Pilih mata kuliah yang diambil mahasiswa pada KRS untuk memulai pencatatan dan penghitungan komponen nilai."
        icon="graduation-cap"
    >
        <button type="button" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2" wire:click="cancel">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </button>
    </x-admin.academic.header>

    <form wire:submit.prevent="save">
        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-file-signature fs-5"></i>
                            </div>
                            <div>
                                <h4 class="card-title fw-bold mb-1 text-dark">Formulir Header Nilai</h4>
                                <div class="text-muted small">Pilih detail KRS mahasiswa dan dosen penilai yang bertanggung jawab.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Study Plan Detail (KRS) <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model="studyPlanDetailId" required>
                                    <option value="">Pilih Study Plan Detail</option>
                                    @foreach($studyPlanDetails as $detail)
                                        <option value="{{ $detail['id'] }}">{{ $detail['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('studyPlanDetailId') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Dosen Penilai (Graded By)</label>
                                <select class="form-select" wire:model="gradedBy" @disabled(! $studyPlanDetailId)>
                                    <option value="">Pilih Penilai (Dosen Offering)</option>
                                    @foreach($graders as $grader)
                                        <option value="{{ $grader['id'] }}">{{ $grader['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('gradedBy') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                @if (! $studyPlanDetailId)
                                    <small class="text-muted d-block mt-1">Pilih study plan detail terlebih dahulu untuk memuat daftar dosen pengajar.</small>
                                @endif
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status Lifecycle</label>
                                <input type="text" class="form-control bg-light" value="Draft" readonly>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Catatan Tambahan</label>
                                <textarea class="form-control" rows="3" wire:model="notes" placeholder="Catatan atau keterangan evaluasi..."></textarea>
                                @error('notes') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-calculator fs-5"></i>
                            </div>
                            <div>
                                <h5 class="card-title fw-bold mb-1 text-dark">Simpan & Lanjutkan</h5>
                                <div class="text-muted small">Buat kerangka nilai awal.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted small mb-4">Setelah header nilai dibuat, Anda akan diarahkan ke halaman pengisian rincian komponen nilai (seperti Tugas, UTS, UAS, atau Praktikum).</p>
                        <div class="d-flex justify-content-end gap-2 border-top pt-3">
                            <button type="button" class="btn btn-light rounded-pill px-3 py-2" wire:click="cancel">
                                <i class="fa fa-times me-1"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-semibold">
                                <i class="fa fa-save me-1"></i> Buat & Isi Komponen
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-light">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-2 text-dark"><i class="fa fa-info-circle text-primary me-2"></i>Penghitungan Otomatis</h6>
                        <p class="text-muted small mb-0">Nilai akhir (Final Score, Letter Grade, Grade Point, dan Result Status) dihitung secara otomatis dan dinamis berdasarkan bobot persentase dari setiap komponen nilai yang dimasukkan.</p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
