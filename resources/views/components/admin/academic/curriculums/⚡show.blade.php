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

<div>
    <x-alert />

    <x-admin.academic.header
        title="Detail Kurikulum: {{ $curriculum->name }}"
        description="Pratinjau lengkap spesifikasi kurikulum, periode berlaku, dan sebaran mata kuliah tiap semester."
        icon="book-open"
    >
        <div class="d-flex gap-2">
            @activecan('curriculum.update')
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-2 shadow-sm d-flex align-items-center gap-1" wire:click="editCurriculum">
                    <i class="fas fa-edit"></i> Edit Kurikulum
                </button>
            @endactivecan

            <button type="button" class="btn btn-sm btn-light text-secondary rounded-pill px-3 py-2 border shadow-sm d-flex align-items-center gap-1" wire:click="backToIndex">
                <i class="fas fa-arrow-left"></i> Kembali
            </button>
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-layer-group fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Mata Kuliah</div>
                        <div class="fw-bold text-white">{{ number_format($this->totalCourses()) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-graduation-cap fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Kredit (SKS)</div>
                        <div class="fw-bold text-white">{{ number_format($this->totalCredits()) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-alt fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Periode Berlaku</div>
                        <div class="fw-bold text-white">{{ $curriculum->start_year ?? '-' }} - {{ $curriculum->end_year ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.academic.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white p-4 border-bottom">
            <h5 class="card-title fw-bold mb-0">Informasi Umum</h5>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-md-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light">
                        <div class="text-muted small fw-semibold mb-1">Program Studi</div>
                        <div class="fw-bold fs-5 text-dark">{{ $curriculum->studyProgram?->name ?? '-' }}</div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light">
                        <div class="text-muted small fw-semibold mb-1">Nama Kurikulum</div>
                        <div class="fw-bold fs-5 text-dark">{{ $curriculum->name }}</div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light">
                        <div class="text-muted small fw-semibold mb-1">Kode Kurikulum</div>
                        <div class="fw-semibold fs-5 text-dark">{{ $curriculum->code ?: '-' }}</div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light">
                        <div class="text-muted small fw-semibold mb-1">Status Kurikulum</div>
                        <div>
                            <span class="badge {{ $curriculum->is_active ? 'bg-success' : 'bg-secondary' }} rounded-pill px-3 py-2">
                                {{ $curriculum->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="border rounded-4 p-3 bg-light">
                        <div class="text-muted small fw-semibold mb-1">Deskripsi & Landasan</div>
                        <div class="text-dark lh-lg">
                            {{ $curriculum->desc ?: 'Belum ada deskripsi untuk kurikulum ini.' }}
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
        <div class="row g-4">
            @foreach($groupedCourses as $semester => $courses)
                <div class="col-12 col-lg-6 col-xxl-4">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                        <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title fw-bold mb-0 text-primary">
                                    {{ is_numeric($semester) ? 'Semester ' . $semester : $semester }}
                                </h5>
                                <div class="text-muted small mt-1">
                                    {{ count($courses) }} mata kuliah • {{ $this->semesterCredits($courses) }} SKS
                                </div>
                            </div>

                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-bold">
                                {{ count($courses) }} MK
                            </span>
                        </div>

                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                @foreach($courses as $item)
                                    <div class="list-group-item p-3 border-bottom-0 border-top">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div class="flex-fill">
                                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                    <span class="badge bg-light text-dark border">{{ $item->course->code }}</span>

                                                    @if($item->is_required)
                                                        <span class="badge bg-primary bg-opacity-10 text-primary">Wajib</span>
                                                    @else
                                                        <span class="badge bg-warning bg-opacity-10 text-warning">Pilihan</span>
                                                    @endif

                                                    @if($item->is_active)
                                                        <span class="badge bg-success bg-opacity-10 text-success">Aktif</span>
                                                    @else
                                                        <span class="badge bg-secondary bg-opacity-10 text-secondary">Nonaktif</span>
                                                    @endif
                                                </div>

                                                <div class="fw-bold text-dark">{{ $item->course->name }}</div>

                                                <div class="text-muted small mt-1 d-flex flex-wrap gap-3">
                                                    <span>Bobot: <strong>{{ $item->credits_override ?? $item->course->credits }} SKS</strong></span>
                                                    <span>Urutan: <strong>{{ $item->sort_order }}</strong></span>
                                                </div>

                                                @if($item->notes)
                                                    <div class="small bg-light rounded-3 p-2 mt-2 text-muted border-start border-3 border-primary">
                                                        <i class="fa fa-info-circle me-1"></i> {{ $item->notes }}
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
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
            <div class="text-muted mb-3"><i class="fa fa-layer-group fs-1 text-opacity-50"></i></div>
            <h6 class="fw-bold text-muted">Belum ada mata kuliah dalam kurikulum ini</h6>
            <p class="small text-muted mb-0">Silakan masuk ke halaman Edit Kurikulum untuk mulai memetakan mata kuliah pada tiap semester.</p>
        </div>
    @endif
</div>
