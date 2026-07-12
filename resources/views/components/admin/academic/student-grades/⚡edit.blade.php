<?php

use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudentGradeComponent;
use App\Models\Academic\StudyPlanDetail;
use App\Support\ActivePermission;
use App\Support\StudentGradeCalculator;
use App\Support\StudentGradePublicationService;
use App\Support\TranscriptSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public StudentGrade $studentGrade;

    public array $gradeForm = [];

    public array $componentForm = [];

    public array $studyPlanDetails = [];

    public array $graders = [];

    public ?int $editingComponentId = null;

    public bool $showComponentForm = false;

    public function mount($id): void
    {
        $this->studentGrade = StudentGrade::with([
            'studyPlanDetail.studyPlan.studentProfile.user',
            'studyPlanDetail.studyPlan.studentProfile.studyProgram',
            'studyPlanDetail.studyPlan.academicYear',
            'studyPlanDetail.courseOffering.course',
            'gradedBy',
            'components',
        ])->findOrFail($id);

        $this->gradeForm = [
            'study_plan_detail_id' => $this->studentGrade->study_plan_detail_id,
            'graded_by' => $this->studentGrade->graded_by,
            'notes' => $this->studentGrade->notes,
        ];

        $this->studyPlanDetails = StudyPlanDetail::query()
            ->where(function ($query) {
                $query->whereDoesntHave('studentGrade')
                    ->orWhere('id', $this->studentGrade->study_plan_detail_id);
            })
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
                    'label' => ($detail->studyPlan?->studentProfile?->user?->name ?? '-').
                        ' ('.($detail->studyPlan?->studentProfile?->nim ?? '-').') - '.
                        ($detail->courseOffering?->course?->code ?? '-').' - '.
                        ($detail->courseOffering?->course?->name ?? '-'),
                ];
            })
            ->toArray();

        $this->loadEligibleGraders();
        $this->resetComponentForm();
        $this->recalculateSnapshot(false);
    }

    public function updatedGradeFormStudyPlanDetailId(): void
    {
        $this->loadEligibleGraders();
    }

    protected function loadEligibleGraders(): void
    {
        $studyPlanDetailId = $this->gradeForm['study_plan_detail_id'] ?? null;

        if (! $studyPlanDetailId) {
            $this->graders = [];
            $this->gradeForm['graded_by'] = null;

            return;
        }

        $studyPlanDetail = StudyPlanDetail::find($studyPlanDetailId);

        if (! $studyPlanDetail?->course_offering_id) {
            $this->graders = [];
            $this->gradeForm['graded_by'] = null;

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
        $currentGrader = $this->gradeForm['graded_by'] ?? null;

        if ($currentGrader !== null && ! in_array((string) $currentGrader, $eligibleIds, true)) {
            $this->gradeForm['graded_by'] = null;
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

    protected function calculator(): StudentGradeCalculator
    {
        return new StudentGradeCalculator;
    }

    protected function recalculateSnapshot(bool $setDraftWhenFinalized = true): void
    {
        $this->studentGrade->loadMissing('components');
        $calculator = $this->calculator();
        $snapshot = $calculator->buildSnapshot($this->studentGrade);

        $payload = array_merge($snapshot, [
            'graded_at' => $this->studentGrade->graded_by ? now() : null,
        ]);

        if ($setDraftWhenFinalized && $this->studentGrade->grade_status === 'Finalized') {
            $payload['grade_status'] = 'Draft';
        }

        $this->studentGrade->update($payload);
        $this->studentGrade->refresh();
    }

    public function getTotalWeightProperty(): float
    {
        return $this->calculator()->calculateTotalWeight($this->studentGrade->loadMissing('components'));
    }

    public function getRemainingWeightProperty(): float
    {
        return round(100 - $this->totalWeight, 2);
    }

    public function getCanFinalizeProperty(): bool
    {
        return abs($this->remainingWeight) < 0.0001;
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.student-grades.index');
    }

    public function updateGradeHeader(): void
    {
        $validatedData = $this->validate([
            'gradeForm.study_plan_detail_id' => [
                'required',
                'exists:study_plan_details,id',
                Rule::unique('student_grades', 'study_plan_detail_id')
                    ->whereNull('deleted_at')
                    ->ignore($this->studentGrade->id),
            ],
            'gradeForm.graded_by' => 'nullable|exists:users,id',
            'gradeForm.notes' => 'nullable|string',
        ]);

        if (
            ! empty($validatedData['gradeForm']['graded_by'])
            && ! $this->isEligibleGrader(
                (int) $validatedData['gradeForm']['graded_by'],
                (int) $validatedData['gradeForm']['study_plan_detail_id']
            )
        ) {
            $this->addError('gradeForm.graded_by', 'Penilai harus dosen pengajar pada course offering ini.');

            return;
        }

        DB::beginTransaction();

        try {
            $this->studentGrade->update([
                'study_plan_detail_id' => $validatedData['gradeForm']['study_plan_detail_id'],
                'graded_by' => $validatedData['gradeForm']['graded_by'] ?: null,
                'notes' => $validatedData['gradeForm']['notes'] ?: null,
                'updated_by' => auth()->id(),
            ]);

            $this->recalculateSnapshot(false);
            DB::commit();

            session()->flash('success', 'Header nilai berhasil diperbarui.');
        } catch (Throwable $th) {
            DB::rollBack();
            session()->flash('error', 'Terjadi kesalahan: '.$th->getMessage());
        }
    }

    public function finalizeGrade(): void
    {
        if (! $this->canFinalize) {
            $this->addError('finalize', 'Total bobot harus tepat 100% untuk finalisasi nilai.');

            return;
        }

        $this->recalculateSnapshot(false);

        $this->studentGrade->update([
            'grade_status' => 'Finalized',
            'graded_at' => now(),
            'graded_by' => $this->gradeForm['graded_by'] ?: null,
            'updated_by' => auth()->id(),
        ]);

        $studentProfileId = (int) ($this->studentGrade->studyPlanDetail?->studyPlan?->student_profile_id ?? 0);
        $academicYearId = (int) ($this->studentGrade->studyPlanDetail?->studyPlan?->academic_year_id ?? 0);

        if ($studentProfileId > 0) {
            $service = new TranscriptSyncService;
            $service->syncStudent($studentProfileId, $academicYearId > 0 ? $academicYearId : null);
        }

        $this->studentGrade->refresh();
        session()->flash('success', 'Nilai berhasil difinalisasi.');
    }

    public function publishGrade(StudentGradePublicationService $publicationService): void
    {
        if (! ActivePermission::check('student-grade.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk publish nilai mahasiswa.');

            return;
        }

        if ($this->studentGrade->grade_status !== 'Finalized') {
            $this->addError('publish', 'Hanya nilai berstatus Finalized yang bisa dipublikasikan.');

            return;
        }

        if (! $publicationService->publish($this->studentGrade, auth()->id())) {
            $this->addError('publish', 'Nilai tidak bisa dipublikasikan dari status saat ini.');

            return;
        }

        $this->studentGrade->refresh();
        session()->flash('success', 'Nilai berhasil dipublikasikan dan sudah bisa dilihat mahasiswa.');
    }

    public function startCreateComponent(): void
    {
        if (! ActivePermission::check('student-grade.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengelola komponen nilai.');

            return;
        }

        $this->editingComponentId = null;
        $this->showComponentForm = true;
        $this->resetComponentForm();
    }

    public function startEditComponent(int $id): void
    {
        if (! ActivePermission::check('student-grade.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengelola komponen nilai.');

            return;
        }

        $component = $this->studentGrade->components()->findOrFail($id);

        $this->editingComponentId = $component->id;
        $this->showComponentForm = true;
        $this->componentForm = [
            'name' => $component->name,
            'weight_percentage' => $component->weight_percentage,
            'score' => $component->score,
            'sort_order' => $component->sort_order,
            'notes' => $component->notes,
        ];
    }

    public function cancelComponentForm(): void
    {
        $this->showComponentForm = false;
        $this->editingComponentId = null;
        $this->resetComponentForm();
    }

    public function saveComponent(): void
    {
        if (! ActivePermission::check('student-grade.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengelola komponen nilai.');

            return;
        }

        $validatedData = $this->validate([
            'componentForm.name' => 'required|string|max:255',
            'componentForm.weight_percentage' => 'nullable|numeric|min:0|max:100',
            'componentForm.score' => 'nullable|numeric|min:0|max:100',
            'componentForm.sort_order' => 'nullable|integer|min:0',
            'componentForm.notes' => 'nullable|string',
        ]);

        $newWeight = (float) ($validatedData['componentForm']['weight_percentage'] ?? 0);
        $currentTotalWithoutEditing = $this->studentGrade->components()
            ->when($this->editingComponentId, fn ($query) => $query->where('id', '!=', $this->editingComponentId))
            ->get()
            ->sum(fn ($component) => (float) ($component->weight_percentage ?? 0));

        $projectedTotal = round($currentTotalWithoutEditing + $newWeight, 2);

        if ($projectedTotal > 100) {
            $this->addError('componentForm.weight_percentage', 'Total bobot komponen tidak boleh melebihi 100%. Total saat ini akan menjadi '.number_format($projectedTotal, 2).'%.');

            return;
        }

        $payload = [
            'student_grade_id' => $this->studentGrade->id,
            'name' => $validatedData['componentForm']['name'],
            'weight_percentage' => $validatedData['componentForm']['weight_percentage'] !== '' ? $validatedData['componentForm']['weight_percentage'] : null,
            'score' => $validatedData['componentForm']['score'] !== '' ? $validatedData['componentForm']['score'] : null,
            'sort_order' => $validatedData['componentForm']['sort_order'] !== '' ? $validatedData['componentForm']['sort_order'] : 0,
            'notes' => $validatedData['componentForm']['notes'] !== '' ? $validatedData['componentForm']['notes'] : null,
        ];

        if ($this->editingComponentId) {
            $component = $this->studentGrade->components()->findOrFail($this->editingComponentId);
            $component->update(array_merge($payload, ['updated_by' => auth()->id()]));
            session()->flash('success', 'Komponen nilai berhasil diperbarui.');
        } else {
            StudentGradeComponent::create(array_merge($payload, ['created_by' => auth()->id()]));
            session()->flash('success', 'Komponen nilai berhasil ditambahkan.');
        }

        $this->studentGrade->refresh();
        $this->recalculateSnapshot();
        $this->cancelComponentForm();
    }

    public function confirmDeleteComponent(int $id): void
    {
        if (! ActivePermission::check('student-grade.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengelola komponen nilai.');

            return;
        }

        $component = $this->studentGrade->components()->find($id);

        if ($component) {
            $this->js('
                Swal.fire({
                    title: "Hapus komponen nilai?",
                    text: "'.$component->name.' - Data tidak bisa dikembalikan!",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ya hapus",
                    cancelButtonText: "Batal"
                }).then((result) => {
                    if (result.isConfirmed) {
                        Livewire.dispatch("deleteComponentConfirmed", { id: '.$id.' })
                    }
                });
            ');
        }
    }

    #[On('deleteComponentConfirmed')]
    public function deleteComponentConfirmed($id = null): void
    {
        if (! ActivePermission::check('student-grade.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengelola komponen nilai.');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id komponen tidak ditemukan.');

            return;
        }

        $component = $this->studentGrade->components()->find($id);

        if ($component) {
            $component->update(['deleted_by' => auth()->id()]);
            $component->delete();
            $this->studentGrade->refresh();
            $this->recalculateSnapshot();

            $this->js('
                Swal.fire({
                    title: "Data dihapus",
                    text: "Komponen nilai berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function getGradeComponentsProperty()
    {
        return $this->studentGrade->components()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    protected function resetComponentForm(): void
    {
        $this->componentForm = [
            'name' => null,
            'weight_percentage' => null,
            'score' => null,
            'sort_order' => 0,
            'notes' => null,
        ];
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Edit Nilai Mahasiswa',
            ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Edit & Penilaian Evaluasi (KHS)"
        description="Kelola informasi header evaluasi, atur bobot persentase, dan masukkan skor untuk setiap komponen penilaian."
        icon="graduation-cap"
    >
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.academic.student-grades.show', ['id' => $studentGrade->id]) }}" class="btn btn-sm btn-light text-info fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2 border">
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
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-file-signature fs-5"></i>
                            </div>
                            <div>
                                <h4 class="card-title fw-bold mb-1 text-dark">Section A - Header Nilai & Evaluasi Akhir</h4>
                                <div class="text-muted small">Kelola kaitan mata kuliah (KRS), dosen penilai, dan status finalisasi hasil studi.</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-success rounded-pill px-3 py-2 shadow-sm fw-semibold d-inline-flex align-items-center gap-2" wire:click="finalizeGrade" @disabled(! $this->canFinalize)>
                                <i class="fas fa-check"></i> <span>Finalize Nilai</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-2 shadow-sm fw-semibold d-inline-flex align-items-center gap-2" wire:click="publishGrade" @disabled($studentGrade->grade_status !== 'Finalized')>
                                <i class="fas fa-bullhorn"></i> <span>Publish</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form wire:submit.prevent="updateGradeHeader">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Study Plan Detail (KRS) <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model="gradeForm.study_plan_detail_id" required>
                                    <option value="">Pilih Study Plan Detail</option>
                                    @foreach($studyPlanDetails as $detail)
                                        <option value="{{ $detail['id'] }}">{{ $detail['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('gradeForm.study_plan_detail_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Dosen Penilai (Graded By)</label>
                                <select class="form-select" wire:model="gradeForm.graded_by">
                                    <option value="">Pilih Penilai (Dosen Offering)</option>
                                    @foreach($graders as $grader)
                                        <option value="{{ $grader['id'] }}">{{ $grader['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('gradeForm.graded_by') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status Lifecycle</label>
                                <div>
                                    @php
                                        $statusClass = match($studentGrade->grade_status) {
                                            'Finalized' => 'bg-success',
                                            'Published' => 'bg-primary',
                                            default => 'bg-secondary'
                                        };
                                    @endphp
                                    <span class="badge rounded-pill px-3 py-2 {{ $statusClass }}">{{ $studentGrade->grade_status }}</span>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="text-muted small d-block mb-1">Total Bobot Komponen</label>
                                <div class="fw-bold fs-5 text-dark">{{ number_format($this->totalWeight, 2) }}%</div>
                            </div>

                            <div class="col-md-3">
                                <label class="text-muted small d-block mb-1">Skor Akhir (Final Score)</label>
                                <div class="fw-bold fs-5 text-primary">{{ $studentGrade->final_score ?? '-' }}</div>
                            </div>

                            <div class="col-md-3">
                                <label class="text-muted small d-block mb-1">Nilai Huruf (Letter Grade)</label>
                                <div class="fw-bold fs-5 text-success">{{ $studentGrade->letter_grade ?? '-' }}</div>
                            </div>

                            <div class="col-md-3">
                                <label class="text-muted small d-block mb-1">Indeks Nilai & Status</label>
                                <div class="fw-semibold text-dark">{{ $studentGrade->grade_point ?? '-' }} <span class="badge bg-light text-dark border ms-2">{{ $studentGrade->result_status ?? '-' }}</span></div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Catatan Evaluasi</label>
                                <textarea class="form-control" rows="2" wire:model="gradeForm.notes" placeholder="Catatan evaluasi pengajar..."></textarea>
                                @error('gradeForm.notes') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12">
                                @if ($this->canFinalize)
                                    <div class="alert alert-success border-0 shadow-sm rounded-3 mb-0 d-flex align-items-center gap-3 p-3">
                                        <i class="fas fa-check-circle fs-5"></i>
                                        <div>Total bobot komponen sudah tepat <strong>100%</strong>. Nilai dapat difinalisasi dan dikunci.</div>
                                    </div>
                                @else
                                    <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-0 d-flex align-items-center gap-3 p-3">
                                        <i class="fas fa-exclamation-triangle fs-5"></i>
                                        <div>Total bobot saat ini <strong>{{ number_format($this->totalWeight, 2) }}%</strong>. Sisa bobot menuju 100%: <strong>{{ number_format($this->remainingWeight, 2) }}%</strong>.</div>
                                    </div>
                                @endif
                                @error('finalize') <span class="text-danger small d-block mt-2">{{ $message }}</span> @enderror
                                @error('publish') <span class="text-danger small d-block mt-2">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12 border-top pt-3 d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-light rounded-pill px-3 py-2" wire:click="cancel">
                                    <i class="fas fa-times me-1"></i> Batal
                                </button>
                                <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-semibold">
                                    <i class="fas fa-save me-1"></i> Simpan Header
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
                                <i class="fa fa-list-ol fs-5"></i>
                            </div>
                            <div>
                                <h4 class="card-title fw-bold mb-1 text-dark">Section B - Komponen Penilaian</h4>
                                <div class="text-muted small">Rincian bobot dan skor evaluasi (Tugas, Kuis, UTS, UAS, Praktikum, dll).</div>
                            </div>
                        </div>
                        @if (ActivePermission::check('student-grade.update'))
                            <button type="button" class="btn btn-sm btn-success rounded-pill px-3 py-2 shadow-sm fw-semibold d-inline-flex align-items-center gap-2" wire:click="startCreateComponent">
                                <i class="fas fa-plus"></i> <span>Tambah Komponen</span>
                            </button>
                        @endif
                    </div>
                </div>
                <div class="card-body p-4">
                    @if ($showComponentForm)
                        <div class="card border border-primary bg-light rounded-4 p-4 mb-4">
                            <h6 class="fw-bold mb-3 text-dark"><i class="fa fa-edit text-primary me-2"></i>{{ $editingComponentId ? 'Edit Komponen Nilai' : 'Tambah Komponen Nilai Baru' }}</h6>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Nama Komponen <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" wire:model="componentForm.name" placeholder="misal: Tugas 1 / Kuis / UTS / UAS">
                                    @error('componentForm.name') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label small fw-semibold">Bobot (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control" wire:model="componentForm.weight_percentage" placeholder="0 - 100">
                                    @error('componentForm.weight_percentage') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label small fw-semibold">Skor (0-100)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control" wire:model="componentForm.score" placeholder="Skor">
                                    @error('componentForm.score') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label small fw-semibold">Urutan</label>
                                    <input type="number" min="0" class="form-control" wire:model="componentForm.sort_order">
                                    @error('componentForm.sort_order') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="d-flex gap-2 w-100">
                                        <button type="button" class="btn btn-primary rounded-pill px-3 py-2 flex-grow-1 shadow-sm fw-semibold" wire:click="saveComponent">
                                            <i class="fas fa-save"></i>
                                        </button>
                                        <button type="button" class="btn btn-light rounded-pill px-3 py-2 border" wire:click="cancelComponentForm">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Catatan Komponen</label>
                                    <input type="text" class="form-control" wire:model="componentForm.notes" placeholder="Keterangan opsional mengenai komponen ini...">
                                    @error('componentForm.notes') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($this->gradeComponents->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-3 px-3" style="width: 50px;">No</th>
                                        <th class="py-3 px-3">Komponen</th>
                                        <th class="py-3 px-3 text-center">Bobot (%)</th>
                                        <th class="py-3 px-3 text-center">Skor</th>
                                        <th class="py-3 px-3 text-center">Urutan</th>
                                        <th class="py-3 px-3">Catatan</th>
                                        <th class="py-3 px-3 text-end" style="width: 120px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->gradeComponents as $index => $component)
                                        <tr>
                                            <td class="px-3 fw-semibold text-muted">{{ $index + 1 }}</td>
                                            <td class="px-3 fw-bold text-dark">{{ $component->name }}</td>
                                            <td class="px-3 text-center fw-semibold text-primary">{{ $component->weight_percentage ? number_format($component->weight_percentage, 2).'%' : '-' }}</td>
                                            <td class="px-3 text-center fw-bold text-success">{{ $component->score ?? '-' }}</td>
                                            <td class="px-3 text-center">{{ $component->sort_order }}</td>
                                            <td class="px-3 text-muted small">{{ $component->notes ?? '-' }}</td>
                                            <td class="px-3 text-end">
                                                @if (ActivePermission::check('student-grade.update'))
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-light text-warning border rounded-start-pill px-2 py-1" wire:click="startEditComponent({{ $component->id }})">
                                                            <i class="fas fa-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-light text-danger border rounded-end-pill px-2 py-1" wire:click="confirmDeleteComponent({{ $component->id }})">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="2" class="text-end py-3 px-3 fw-bold">Total Bobot:</th>
                                        <th class="text-center py-3 px-3 fw-bold text-primary fs-6">{{ number_format($this->totalWeight, 2) }}%</th>
                                        <th colspan="4" class="py-3 px-3 fw-semibold text-muted">Sisa Menuju 100%: <span class="badge {{ $this->canFinalize ? 'bg-success' : 'bg-warning text-dark' }} px-2 py-1">{{ number_format($this->remainingWeight, 2) }}%</span></th>
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
                                <h6 class="fw-bold mb-1">Belum Ada Komponen Nilai</h6>
                                <div class="small">Silakan klik tombol <strong>"Tambah Komponen"</strong> di atas untuk memasukkan bobot dan skor evaluasi mahasiswa.</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
