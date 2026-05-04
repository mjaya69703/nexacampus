<?php

use Livewire\Component;
use App\Models\Academic\Curriculum;

new class extends Component {
    public Curriculum $curriculum;

    public function mount($id): void
    {
        $this->curriculum = Curriculum::with([
            'studyProgram',
            'curriculumCourses.course',
        ])->findOrFail($id);
    }

    public function backToIndex(): void
    {
        $this->redirect(route('admin.academic.curriculums.index'));
    }

    public function editCurriculum(): void
    {
        $this->redirect(route('admin.academic.curriculums.edit', ['id' => $this->curriculum->id]));
    }

    public function groupedCourses()
    {
        $grouped = $this->curriculum->curriculumCourses
            ->filter(fn ($item) => $item->course !== null)
            ->sortBy([
                fn ($item) => $item->semester_no ?? 999,
                fn ($item) => $item->sort_order ?? 999,
                fn ($item) => $item->course->name ?? '',
            ])
            ->groupBy(fn ($item) => $item->semester_no ?? 'Tanpa Semester');

        $sorted = collect();

        $numericKeys = $grouped->keys()
            ->filter(fn ($key) => is_numeric($key))
            ->sortBy(fn ($key) => (int) $key);

        foreach ($numericKeys as $key) {
            $sorted->put($key, $grouped->get($key));
        }

        if ($grouped->has('Tanpa Semester')) {
            $sorted->put('Tanpa Semester', $grouped->get('Tanpa Semester'));
        }

        return $sorted;
    }

    public function semesterCredits($courses): int
    {
        return collect($courses)
            ->sum(fn ($item) => (int) ($item->credits_override ?? ($item->course->credits ?? 0)));
    }

    public function totalCourses(): int
    {
        return $this->curriculum->curriculumCourses
            ->filter(fn ($item) => $item->course !== null)
            ->count();
    }

    public function totalCredits(): int
    {
        return $this->curriculum->curriculumCourses
            ->filter(fn ($item) => $item->course !== null)
            ->sum(fn ($item) => (int) ($item->credits_override ?? $item->course->credits));
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Detail Kurikulum',
        ]);
    }
};
?>
@push('styles')
<style>
    .curriculum-semester-card .list-group-item {
        transition: background-color .15s ease;
    }

    .curriculum-semester-card .list-group-item:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }
</style>
@endpush

<div class="row">
    <div class="col-12">
        <x-alert />

        <div class="card mb-4">
            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <div class="text-secondary text-uppercase small fw-bold">Academic / Curriculum</div>
                    <h3 class="card-title mb-0">Detail Kurikulum</h3>
                </div>

                <div class="d-flex gap-2">
                    @activecan('curriculum.update')
                        <button type="button" class="btn btn-warning" wire:click="editCurriculum">
                            <i class="fas fa-edit me-1"></i> Edit Kurikulum
                        </button>
                    @endactivecan

                    <button type="button" class="btn btn-outline-secondary" wire:click="backToIndex">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </button>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100 bg-body-tertiary">
                            <div class="text-secondary small mb-1">Program Studi</div>
                            <div class="fw-bold fs-4">{{ $curriculum->studyProgram?->name ?? '-' }}</div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100 bg-body-tertiary">
                            <div class="text-secondary small mb-1">Nama Kurikulum</div>
                            <div class="fw-bold fs-4">{{ $curriculum->name }}</div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-2">
                        <div class="border rounded-3 p-3 h-100 bg-body-tertiary">
                            <div class="text-secondary small mb-1">Kode</div>
                            <div class="fw-semibold">{{ $curriculum->code ?: '-' }}</div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-2">
                        <div class="border rounded-3 p-3 h-100 bg-body-tertiary">
                            <div class="text-secondary small mb-1">Periode</div>
                            <div class="fw-semibold">
                                {{ $curriculum->start_year ?? '-' }} - {{ $curriculum->end_year ?? '-' }}
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-2">
                        <div class="border rounded-3 p-3 h-100 bg-body-tertiary">
                            <div class="text-secondary small mb-1">Status</div>
                            <div>
                                <span class="badge {{ $curriculum->is_active ? 'bg-green-lt text-green' : 'bg-secondary-lt text-secondary' }}">
                                    {{ $curriculum->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="text-secondary small mb-1">Total Mata Kuliah</div>
                            <div class="fw-bold fs-2">{{ $this->totalCourses() }}</div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="text-secondary small mb-1">Total Kredit</div>
                            <div class="fw-bold fs-2">{{ $this->totalCredits() }}</div>
                        </div>
                    </div>

                    <div class="col-md-12 col-xl-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="text-secondary small mb-1">Deskripsi</div>
                            <div class="text-muted lh-lg">
                                {{ $curriculum->desc ?: 'Belum ada deskripsi kurikulum.' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @php
            $groupedCourses = $this->groupedCourses();
        @endphp

        @if($groupedCourses->isNotEmpty())
            <div class="row g-3">
                @foreach($groupedCourses as $semester => $courses)
                    <div class="col-12 col-lg-6 col-xxl-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="card-title mb-0">
                                        {{ is_numeric($semester) ? 'Semester ' . $semester : $semester }}
                                    </h3>
                                    <div class="text-secondary small mt-1">
                                        {{ count($courses) }} mata kuliah • {{ $this->semesterCredits($courses) }} SKS
                                    </div>
                                </div>

                                <span class="badge bg-primary-lt text-primary">
                                    {{ count($courses) }} MK
                                </span>
                            </div>

                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    @foreach($courses as $item)
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-start gap-3">
                                                <div class="flex-fill">
                                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                                        <span class="badge bg-azure-lt text-azure">{{ $item->course->code }}</span>

                                                        @if($item->is_required)
                                                            <span class="badge bg-green-lt text-green">Wajib</span>
                                                        @else
                                                            <span class="badge bg-yellow-lt text-yellow">Pilihan</span>
                                                        @endif

                                                        @if($item->is_active)
                                                            <span class="badge bg-success-lt text-success">Aktif</span>
                                                        @else
                                                            <span class="badge bg-secondary-lt text-secondary">Nonaktif</span>
                                                        @endif
                                                    </div>

                                                    <div class="fw-semibold">{{ $item->course->name }}</div>

                                                    <div class="text-secondary small mt-1 d-flex flex-wrap gap-3">
                                                        <span>SKS: <strong>{{ $item->credits_override ?? $item->course->credits }}</strong></span>
                                                        <span>Urutan: <strong>{{ $item->sort_order }}</strong></span>
                                                    </div>

                                                    @if($item->notes)
                                                        <div class="text-muted small mt-2">
                                                            {{ $item->notes }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="alert alert-light border text-center py-4">
                <i class="fas fa-info-circle me-2"></i>
                Belum ada mata kuliah yang dipetakan pada kurikulum ini.
            </div>
        @endif
    </div>
</div>
