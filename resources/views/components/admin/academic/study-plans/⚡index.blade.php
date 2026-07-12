<?php

use Livewire\Component;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\AcademicYear;

new class extends Component {
    public int $totalPlans = 0;

    public int $activePlans = 0;

    public int $pendingPlans = 0;

    public int $approvedPlans = 0;

    public int $totalCredits = 0;

    public function mount(): void
    {
        $this->totalPlans = StudyPlan::count();
        $this->activePlans = StudyPlan::where('status', 'active')->count();
        $this->pendingPlans = StudyPlan::where('status', 'pending')->count();
        $this->approvedPlans = StudyPlan::where('status', 'approved')->count();
        $this->totalCredits = (int) StudyPlan::withSum('details as total_credits', 'credits')->get()->sum('total_credits');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic Management',
            'pages' => 'Daftar KRS',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.academic.header
        title="Kartu Rencana Studi (KRS)"
        description="Kelola dan pantau pengambilan mata kuliah, beban SKS, serta persetujuan rancangan studi mahasiswa."
        icon="book-open"
    >
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @include('templates.import.academic.grid-actions')
            @activecan('study-plan.create')
                <a href="{{ route('admin.academic.study-plans.create') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                    <i class="fa fa-plus-circle"></i>
                    <span>Buat KRS</span>
                </a>
            @endactivecan
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-list fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total KRS</div>
                        <div class="fw-bold">{{ number_format($totalPlans) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Approved</div>
                        <div class="fw-bold">{{ number_format($approvedPlans) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Pending</div>
                        <div class="fw-bold">{{ number_format($pendingPlans) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-graduation-cap fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total SKS Diambil</div>
                        <div class="fw-bold">{{ number_format($totalCredits) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.academic.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan KRS</h4>
                <div class="text-muted small">Statistik singkat Kartu Rencana Studi berdasarkan status dan beban SKS mahasiswa.</div>
            </div>
            <div class="text-muted small">Gunakan filter tabel untuk penelusuran per tahun akademik dan prodi.</div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total KRS</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($totalPlans) }}</div>
                            <i class="fa fa-list fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Approved</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-success lh-1">{{ number_format($approvedPlans) }}</div>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Pending</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-warning lh-1">{{ number_format($pendingPlans) }}</div>
                            <i class="fa fa-clock fs-4 text-warning opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total SKS</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-info lh-1">{{ number_format($totalCredits) }}</div>
                            <i class="fa fa-graduation-cap fs-4 text-info opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Data KRS Mahasiswa</h4>
                    <span class="text-muted small">Gunakan filter tabel untuk menelusuri berdasarkan tahun akademik, program studi, atau status KRS.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:academic.study-plan-table />
        </div>
    </div>
</div>
