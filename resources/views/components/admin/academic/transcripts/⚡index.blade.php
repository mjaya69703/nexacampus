<?php

use Livewire\Component;
use App\Models\Academic\StudentProfile;

new class extends Component {
    public int $totalTranscripts = 0;

    public int $withEntries = 0;

    public int $withoutEntries = 0;

    public int $totalSemesterResults = 0;

    public function mount(): void
    {
        $this->totalTranscripts = StudentProfile::count();
        $this->withEntries = StudentProfile::has('transcriptEntries')->count();
        $this->withoutEntries = $this->totalTranscripts - $this->withEntries;
        $this->totalSemesterResults = (int) StudentProfile::withCount('studyResults')->get()->sum('study_results_count');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic Management',
            'pages' => 'Transkrip Nilai',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.academic.header
        title="Transkrip Nilai Mahasiswa"
        description="Pantau rekapitulasi hasil studi (Semester Results), daftar seluruh nilai mata kuliah (Transcript Entries), serta Indeks Prestasi Kumulatif (IPK)."
        icon="certificate"
    >
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @include('templates.import.academic.grid-actions')
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-list fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Mhs</div>
                        <div class="fw-bold">{{ number_format($totalTranscripts) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Ada Transkrip</div>
                        <div class="fw-bold">{{ number_format($withEntries) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Belum Ada</div>
                        <div class="fw-bold">{{ number_format($withoutEntries) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-chart-bar fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Semester Result</div>
                        <div class="fw-bold">{{ number_format($totalSemesterResults) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.academic.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Transkrip</h4>
                <div class="text-muted small">Statistik singkat transkrip nilai mahasiswa berdasarkan ketersediaan data dan hasil studi.</div>
            </div>
            <div class="text-muted small">Gunakan filter tabel untuk penelusuran per prodi atau hasil studi.</div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Mahasiswa</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($totalTranscripts) }}</div>
                            <i class="fa fa-list fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Ada Transkrip</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-success lh-1">{{ number_format($withEntries) }}</div>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Belum Ada</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-secondary lh-1">{{ number_format($withoutEntries) }}</div>
                            <i class="fa fa-clock fs-4 text-secondary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Semester Result</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-info lh-1">{{ number_format($totalSemesterResults) }}</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Data Transkrip Mahasiswa</h4>
                    <span class="text-muted small">Gunakan filter tabel untuk menelusuri berdasarkan prodi atau hasil studi.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:academic.transcript-table />
        </div>
    </div>
</div>
