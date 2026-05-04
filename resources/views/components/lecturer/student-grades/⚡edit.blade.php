<?php

use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudentGradeComponent;
use App\Models\Academic\StudyPlanDetail;
use App\Support\StudentGradeCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public int $studyPlanDetailId;
    public StudyPlanDetail $detail;
    public StudentGrade $studentGrade;
    public array $gradeForm = [];
    public array $components = [];
    public array $allowedGradeStatuses = [];

    public function mount(int $id): void
    {
        $this->studyPlanDetailId = $id;

        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        $lecturerProfile = $user->lecturerProfile()->first();

        if (! $lecturerProfile) {
            abort(403);
        }

        $this->detail = StudyPlanDetail::query()
            ->with([
                'studyPlan.studentProfile.user',
                'studyPlan.academicYear',
                'courseOffering.course',
            ])
            ->findOrFail($this->studyPlanDetailId);

        $allowed = CourseOfferingLecturer::query()
            ->where('course_offering_id', $this->detail->course_offering_id)
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->where('is_active', true)
            ->exists();

        if (! $allowed) {
            abort(403);
        }

        $this->studentGrade = StudentGrade::query()->firstOrCreate(
            ['study_plan_detail_id' => $this->detail->id],
            [
                'grade_status' => 'Draft',
                'graded_by' => auth()->id(),
                'created_by' => auth()->id(),
            ],
        );

        $this->studentGrade->load('components');
        $this->allowedGradeStatuses = $this->resolveAllowedGradeStatuses($this->studentGrade->grade_status);

        $this->gradeForm = [
            'notes' => $this->studentGrade->notes,
            'grade_status' => $this->studentGrade->grade_status ?? 'Draft',
        ];

        $this->components = $this->studentGrade->components
            ->sortBy('sort_order')
            ->values()
            ->map(fn (StudentGradeComponent $component) => $this->mapComponent($component))
            ->all();

        if (count($this->components) === 0) {
            $this->components = $this->defaultComponents();
        }
    }

    public function addComponentRow(): void
    {
        $this->components[] = [
            'id' => null,
            'name' => '',
            'weight_percentage' => null,
            'score' => null,
            'sort_order' => count($this->components) + 1,
            'notes' => null,
        ];
    }

    public function removeComponentRow(int $index): void
    {
        if (! isset($this->components[$index])) {
            return;
        }

        unset($this->components[$index]);
        $this->components = array_values($this->components);
    }

    public function saveGrade(): void
    {
        $this->allowedGradeStatuses = $this->resolveAllowedGradeStatuses($this->studentGrade->grade_status);

        $this->validate([
            'gradeForm.notes' => ['nullable', 'string'],
            'gradeForm.grade_status' => ['required', Rule::in($this->allowedGradeStatuses)],
            'components' => ['required', 'array', 'min:1'],
            'components.*.name' => ['required', 'string', 'max:255'],
            'components.*.weight_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'components.*.score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'components.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'components.*.notes' => ['nullable', 'string'],
        ]);

        $totalWeight = collect($this->components)->sum(fn (array $component) => (float) ($component['weight_percentage'] ?? 0));

        if ($totalWeight > 100) {
            $this->addError('components', 'Total bobot komponen tidak boleh melebihi 100%.');

            return;
        }

        if ($this->gradeForm['grade_status'] === 'Finalized' && abs($totalWeight - 100.0) > 0.0001) {
            $this->addError('gradeForm.grade_status', 'Untuk status Finalized, total bobot komponen harus tepat 100%.');

            return;
        }

        DB::transaction(function () {
            $this->studentGrade->update([
                'notes' => $this->gradeForm['notes'] ?: null,
                'grade_status' => $this->gradeForm['grade_status'],
                'graded_by' => auth()->id(),
                'graded_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            $existingIds = $this->studentGrade->components()->pluck('id')->all();
            $savedIds = [];

            foreach ($this->components as $index => $componentRow) {
                $payload = [
                    'name' => $componentRow['name'],
                    'weight_percentage' => $componentRow['weight_percentage'] !== '' ? $componentRow['weight_percentage'] : null,
                    'score' => $componentRow['score'] !== '' ? $componentRow['score'] : null,
                    'sort_order' => $componentRow['sort_order'] !== '' ? $componentRow['sort_order'] : $index + 1,
                    'notes' => $componentRow['notes'] !== '' ? $componentRow['notes'] : null,
                    'updated_by' => auth()->id(),
                ];

                if (! empty($componentRow['id'])) {
                    $component = $this->studentGrade->components()->find($componentRow['id']);

                    if ($component) {
                        $component->update($payload);
                        $savedIds[] = $component->id;
                    }

                    continue;
                }

                $newComponent = $this->studentGrade->components()->create(array_merge($payload, [
                    'created_by' => auth()->id(),
                ]));

                $savedIds[] = $newComponent->id;
            }

            $idsToDelete = array_diff($existingIds, $savedIds);

            if (! empty($idsToDelete)) {
                $this->studentGrade->components()->whereIn('id', $idsToDelete)->delete();
            }

            $this->studentGrade->load('components');

            $calculator = new StudentGradeCalculator();
            $snapshot = $calculator->buildSnapshot($this->studentGrade);

            $this->studentGrade->update(array_merge($snapshot, [
                'updated_by' => auth()->id(),
            ]));
        });

        $this->studentGrade->refresh();
        $this->studentGrade->load('components');
        $this->allowedGradeStatuses = $this->resolveAllowedGradeStatuses($this->studentGrade->grade_status);
        $this->gradeForm['grade_status'] = $this->studentGrade->grade_status;

        $this->components = $this->studentGrade->components
            ->sortBy('sort_order')
            ->values()
            ->map(fn (StudentGradeComponent $component) => $this->mapComponent($component))
            ->all();

        session()->flash('success', 'Nilai mahasiswa berhasil disimpan.');
    }

    public function getTotalWeightProperty(): float
    {
        return round(collect($this->components)->sum(fn (array $component) => (float) ($component['weight_percentage'] ?? 0)), 2);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Edit Student Grade',
        ]);
    }

    private function mapComponent(StudentGradeComponent $component): array
    {
        return [
            'id' => $component->id,
            'name' => $component->name,
            'weight_percentage' => $component->weight_percentage,
            'score' => $component->score,
            'sort_order' => $component->sort_order ?? 0,
            'notes' => $component->notes,
        ];
    }

    private function defaultComponents(): array
    {
        return [
            ['id' => null, 'name' => 'Tugas', 'weight_percentage' => 20, 'score' => null, 'sort_order' => 1, 'notes' => null],
            ['id' => null, 'name' => 'Kuis', 'weight_percentage' => 20, 'score' => null, 'sort_order' => 2, 'notes' => null],
            ['id' => null, 'name' => 'UTS', 'weight_percentage' => 30, 'score' => null, 'sort_order' => 3, 'notes' => null],
            ['id' => null, 'name' => 'UAS', 'weight_percentage' => 30, 'score' => null, 'sort_order' => 4, 'notes' => null],
        ];
    }

    private function resolveAllowedGradeStatuses(?string $currentStatus): array
    {
        if ($currentStatus === 'Published') {
            return ['Published'];
        }

        return ['Draft', 'Finalized'];
    }
};
?>

@push('styles')
    <style>
        .lecturer-grade-card {
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
        }

        .lecturer-grade-hero {
            background:
                radial-gradient(circle at top right, rgba(32, 107, 196, 0.12), transparent 30%),
                linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .lecturer-grade-label {
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .lecturer-grade-metric {
            border-radius: 16px;
            background: #f8fafc;
            padding: 14px 16px;
            height: 100%;
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card lecturer-grade-card lecturer-grade-hero mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="lecturer-grade-label mb-2">Input Nilai Mahasiswa</div>
                    <h2 class="mb-2">{{ $detail->studyPlan?->studentProfile?->user?->name ?? '-' }}</h2>
                    <div class="text-secondary mb-3">
                        {{ $detail->studyPlan?->studentProfile?->nim ?? '-' }} •
                        {{ $detail->courseOffering?->course?->code ?? '-' }} - {{ $detail->courseOffering?->course?->name ?? '-' }}
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-blue-lt text-blue">{{ $detail->courseOffering?->label ?? '-' }}</span>
                        <span class="badge bg-azure-lt text-azure">{{ $detail->studyPlan?->academicYear?->name ?? '-' }}</span>
                        <span class="badge bg-green-lt text-green">Total Bobot {{ number_format($this->totalWeight, 2) }}%</span>
                    </div>
                </div>

                <div class="col-lg-4 text-lg-end">
                    <a href="{{ route('lecturer.student-grades.index') }}" class="btn btn-outline-secondary">
                        Kembali ke Daftar Nilai
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards mb-4">
        <div class="col-md-3">
            <div class="card lecturer-grade-card">
                <div class="card-body">
                    <div class="lecturer-grade-label">Nilai Akhir</div>
                    <div class="h2 mb-0 mt-2">{{ $studentGrade->final_score !== null ? number_format((float) $studentGrade->final_score, 2) : '-' }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card lecturer-grade-card">
                <div class="card-body">
                    <div class="lecturer-grade-label">Nilai Huruf</div>
                    <div class="h2 mb-0 mt-2">{{ $studentGrade->letter_grade ?? '-' }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card lecturer-grade-card">
                <div class="card-body">
                    <div class="lecturer-grade-label">Grade Point</div>
                    <div class="h2 mb-0 mt-2">{{ $studentGrade->grade_point !== null ? number_format((float) $studentGrade->grade_point, 2) : '-' }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card lecturer-grade-card">
                <div class="card-body">
                    <div class="lecturer-grade-label">Hasil</div>
                    <div class="h2 mb-0 mt-2">{{ $studentGrade->result_status ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card lecturer-grade-card mb-4">
        <div class="card-header">
            <h3 class="card-title mb-0">Header Nilai</h3>
        </div>
        <div class="card-body">
            @if ($studentGrade->grade_status === 'Published')
                <div class="alert alert-warning mb-4">
                    Nilai ini sudah berstatus <strong>Published</strong>. Dosen hanya dapat melihat hasil akhir dan tidak dianjurkan mengubah lifecycle publikasinya dari halaman ini.
                </div>
            @else
                <div class="alert alert-info mb-4">
                    Dari sisi dosen, workflow nilai dibatasi sampai <strong>Finalized</strong>. Tahap publikasi sebaiknya dilanjutkan lewat alur akademik yang terpusat.
                </div>
            @endif

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Status Nilai</label>
                    <select class="form-select" wire:model="gradeForm.grade_status">
                        @foreach ($allowedGradeStatuses as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </select>
                    @error('gradeForm.grade_status')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-8">
                    <label class="form-label">Catatan</label>
                    <input type="text" class="form-control" wire:model="gradeForm.notes" placeholder="Tambahkan catatan bila diperlukan">
                    @error('gradeForm.notes')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card lecturer-grade-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Komponen Nilai</h3>
            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="addComponentRow">Tambah Komponen</button>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Komponen</th>
                        <th>Bobot (%)</th>
                        <th>Skor</th>
                        <th>Urutan</th>
                        <th>Catatan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($components as $index => $component)
                        <tr>
                            <td>
                                <input type="text" class="form-control" wire:model="components.{{ $index }}.name" placeholder="Tugas / Kuis / UTS / UAS">
                                @error('components.' . $index . '.name')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </td>
                            <td>
                                <input type="number" min="0" max="100" step="0.01" class="form-control" wire:model="components.{{ $index }}.weight_percentage">
                                @error('components.' . $index . '.weight_percentage')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </td>
                            <td>
                                <input type="number" min="0" max="100" step="0.01" class="form-control" wire:model="components.{{ $index }}.score">
                                @error('components.' . $index . '.score')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </td>
                            <td>
                                <input type="number" min="0" class="form-control" wire:model="components.{{ $index }}.sort_order">
                                @error('components.' . $index . '.sort_order')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </td>
                            <td>
                                <input type="text" class="form-control" wire:model="components.{{ $index }}.notes" placeholder="Opsional">
                                @error('components.' . $index . '.notes')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-danger btn-sm" wire:click="removeComponentRow({{ $index }})">Hapus</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @error('components')
            <div class="card-footer text-danger small">{{ $message }}</div>
        @enderror

        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="text-secondary small">
                Pastikan total bobot tidak lebih dari 100%. Untuk menyimpan sebagai <strong>Finalized</strong>, total bobot harus tepat 100%.
            </div>
            <button type="button" class="btn btn-primary" wire:click="saveGrade">Simpan Nilai</button>
        </div>
    </div>
</div>
