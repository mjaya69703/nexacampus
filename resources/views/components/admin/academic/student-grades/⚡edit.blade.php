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

<div class="row">
    <div class="col-12">
        <x-alert />

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Section A - Header Nilai</h5>
                <a href="{{ route('admin.academic.student-grades.show', ['id' => $studentGrade->id]) }}" class="btn btn-info">
                    <i class="fas fa-eye me-1"></i> Lihat Detail
                </a>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="updateGradeHeader">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Study Plan Detail <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="gradeForm.study_plan_detail_id" required>
                                <option value="">Pilih Study Plan Detail</option>
                                @foreach($studyPlanDetails as $detail)
                                    <option value="{{ $detail['id'] }}">{{ $detail['label'] }}</option>
                                @endforeach
                            </select>
                            @error('gradeForm.study_plan_detail_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Graded By</label>
                            <select class="form-select" wire:model="gradeForm.graded_by">
                                <option value="">Pilih Penilai (Dosen Offering)</option>
                                @foreach($graders as $grader)
                                    <option value="{{ $grader['id'] }}">{{ $grader['label'] }}</option>
                                @endforeach
                            </select>
                            @error('gradeForm.graded_by') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status Lifecycle</label>
                            <input type="text" class="form-control" value="{{ $studentGrade->grade_status }}" readonly>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Total Bobot</label>
                            <input type="text" class="form-control" value="{{ number_format($this->totalWeight, 2) }}%" readonly>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Final Score</label>
                            <input type="text" class="form-control" value="{{ $studentGrade->final_score ?? '-' }}" readonly>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Letter Grade</label>
                            <input type="text" class="form-control" value="{{ $studentGrade->letter_grade ?? '-' }}" readonly>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Grade Point</label>
                            <input type="text" class="form-control" value="{{ $studentGrade->grade_point ?? '-' }}" readonly>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Result Status</label>
                            <input type="text" class="form-control" value="{{ $studentGrade->result_status ?? '-' }}" readonly>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Catatan</label>
                            <textarea class="form-control" rows="2" wire:model="gradeForm.notes"></textarea>
                            @error('gradeForm.notes') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-12 mb-3">
                            @if ($this->canFinalize)
                                <div class="alert alert-success mb-0">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Total bobot sudah tepat 100%. Nilai dapat difinalisasi.
                                </div>
                            @else
                                <div class="alert alert-warning mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Total bobot saat ini {{ number_format($this->totalWeight, 2) }}%.
                                    Sisa bobot menuju 100%: {{ number_format($this->remainingWeight, 2) }}%.
                                </div>
                            @endif
                            @error('finalize') <span class="text-danger d-block mt-2">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Simpan Header
                            </button>
                            <button type="button" class="btn btn-success" wire:click="finalizeGrade" @disabled(! $this->canFinalize)>
                                <i class="fas fa-check me-1"></i> Finalize
                            </button>
                            <button type="button" class="btn btn-primary" wire:click="publishGrade" @disabled($studentGrade->grade_status !== 'Finalized')>
                                <i class="fas fa-bullhorn me-1"></i> Publish
                            </button>
                            <button type="button" class="btn btn-secondary" wire:click="cancel">
                                <i class="fas fa-arrow-left me-1"></i> Kembali
                            </button>
                            @error('publish') <span class="text-danger d-block mt-2">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Section B - Komponen Nilai</h5>
                @if (ActivePermission::check('student-grade.update'))
                    <button class="btn btn-success" wire:click="startCreateComponent">
                        <i class="fas fa-plus me-1"></i> Tambah Komponen
                    </button>
                @endif
            </div>
            <div class="card-body">
                @if ($showComponentForm)
                    <div class="border rounded p-3 mb-3 ">
                        <h6 class="mb-3">{{ $editingComponentId ? 'Edit Komponen Nilai' : 'Tambah Komponen Nilai' }}</h6>

                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Nama Komponen <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" wire:model="componentForm.name" placeholder="Assignment / Quiz / UTS / UAS">
                                @error('componentForm.name') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-2 mb-2">
                                <label class="form-label">Bobot (%)</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" wire:model="componentForm.weight_percentage">
                                @error('componentForm.weight_percentage') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-2 mb-2">
                                <label class="form-label">Skor</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" wire:model="componentForm.score">
                                @error('componentForm.score') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-2 mb-2">
                                <label class="form-label">Urutan</label>
                                <input type="number" min="0" class="form-control form-control-sm" wire:model="componentForm.sort_order">
                                @error('componentForm.sort_order') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-2 mb-2">
                                <label class="form-label">Aksi</label>
                                <div>
                                    <button type="button" class="btn btn-primary" wire:click="saveComponent">
                                        <i class="fas fa-save"></i>
                                    </button>
                                    <button type="button" class="btn btn-secondary" wire:click="cancelComponentForm">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-12 mb-2">
                                <label class="form-label">Catatan</label>
                                <input type="text" class="form-control form-control-sm" wire:model="componentForm.notes">
                                @error('componentForm.notes') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                @endif

                @if ($this->gradeComponents->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Komponen</th>
                                    <th>Bobot (%)</th>
                                    <th>Skor</th>
                                    <th>Urutan</th>
                                    <th>Catatan</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->gradeComponents as $index => $component)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $component->name }}</td>
                                        <td>{{ $component->weight_percentage ?? '-' }}</td>
                                        <td>{{ $component->score ?? '-' }}</td>
                                        <td>{{ $component->sort_order }}</td>
                                        <td>{{ $component->notes ?? '-' }}</td>
                                        <td>
                                            @if (ActivePermission::check('student-grade.update'))
                                                <button class="btn btn-warning" wire:click="startEditComponent({{ $component->id }})">
                                                    <i class="fas fa-pencil"></i>
                                                </button>
                                                <button class="btn btn-danger" wire:click="confirmDeleteComponent({{ $component->id }})">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="2" class="text-end">Total Bobot</th>
                                    <th>{{ number_format($this->totalWeight, 2) }}%</th>
                                    <th colspan="4">Sisa {{ number_format($this->remainingWeight, 2) }}%</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i> Belum ada komponen nilai.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
