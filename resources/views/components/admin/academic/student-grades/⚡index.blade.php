<?php

use Livewire\Component;
use App\Models\Academic\StudentGrade;

new class extends Component {
    public int $totalGrades = 0;

    public int $publishedGrades = 0;

    public int $draftGrades = 0;

    public int $passedGrades = 0;

    public int $failedGrades = 0;

    public function mount(): void
    {
        $this->totalGrades = StudentGrade::count();
        $this->publishedGrades = StudentGrade::where('grade_status', 'Published')->count();
        $this->draftGrades = StudentGrade::where('grade_status', 'Draft')->count();
        $this->passedGrades = StudentGrade::where('result_status', 'Passed')->count();
        $this->failedGrades = StudentGrade::where('result_status', 'Failed')->count();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic Management',
            'pages' => 'Daftar Nilai Mahasiswa',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.academic.header
        title="Nilai Mahasiswa (KHS)"
        description="Kelola dan pantau hasil studi, nilai akhir (Final Score), grade huruf, serta status publikasi Kartu Hasil Studi mahasiswa."
        icon="graduation-cap"
    >
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @include('templates.import.academic.grid-actions')
            @activecan('student-grade.create')
                <a href="{{ route('admin.academic.student-grades.create') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                    <i class="fa fa-plus-circle"></i>
                    <span>Input Nilai</span>
                </a>
            @endactivecan
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-list fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total</div>
                        <div class="fw-bold">{{ number_format($totalGrades) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Published</div>
                        <div class="fw-bold">{{ number_format($publishedGrades) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Draft</div>
                        <div class="fw-bold">{{ number_format($draftGrades) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-thumbs-up fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Lulus</div>
                        <div class="fw-bold">{{ number_format($passedGrades) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-thumbs-down fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tidak Lulus</div>
                        <div class="fw-bold">{{ number_format($failedGrades) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.academic.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Nilai Mahasiswa</h4>
                <div class="text-muted small">Statistik singkat nilai berdasarkan status publikasi dan hasil kelulusan mata kuliah.</div>
            </div>
            <div class="text-muted small">Gunakan filter tabel untuk penelusuran per tahun akademik, prodi, atau grade.</div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Nilai</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($totalGrades) }}</div>
                            <i class="fa fa-list fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Published</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-success lh-1">{{ number_format($publishedGrades) }}</div>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Draft</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-secondary lh-1">{{ number_format($draftGrades) }}</div>
                            <i class="fa fa-clock fs-4 text-secondary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Lulus</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-success lh-1">{{ number_format($passedGrades) }}</div>
                            <i class="fa fa-thumbs-up fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Tidak Lulus</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-danger lh-1">{{ number_format($failedGrades) }}</div>
                            <i class="fa fa-thumbs-down fs-4 text-danger opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Pass Rate</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">
                                @if($totalGrades > 0)
                                    {{ round(($passedGrades / $totalGrades) * 100) }}%
                                @else
                                    -
                                @endif
                            </div>
                            <i class="fa fa-chart-bar fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Data Nilai Mahasiswa</h4>
                    <span class="text-muted small">Gunakan filter tabel untuk menelusuri berdasarkan mata kuliah, kelas, atau status nilai.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:academic.student-grade-table />
        </div>
    </div>
</div>
