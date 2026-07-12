<?php

use App\Models\Alumni\TracerStudyCampaign;
use Livewire\Component;

new class extends Component
{
    public int $totalCampaigns = 0;
    public int $activeCampaigns = 0;
    public int $totalSent = 0;
    public float $avgResponseRate = 0.0;

    public function mount(): void
    {
        $this->totalCampaigns = TracerStudyCampaign::count();
        $this->activeCampaigns = TracerStudyCampaign::where('status', 'active')->count();
        $this->totalSent = (int) TracerStudyCampaign::sum('total_sent');
        
        $campaignsWithSent = TracerStudyCampaign::where('total_sent', '>', 0)->get();
        if ($campaignsWithSent->isNotEmpty()) {
            $rates = $campaignsWithSent->map(fn ($c) => $c->responseRate());
            $this->avgResponseRate = round($rates->avg(), 1);
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni Management',
            'pages' => 'Kampanye Tracer Study',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.alumni.header
        title="Manajemen Kampanye Tracer Study"
        description="Kelola survei pelacakan lulusan untuk pemetaan karir alumni, evaluasi kurikulum prodi, serta pelaporan IKU perguruan tinggi."
        icon="clipboard-check"
    >
        @activecan('tracer-study-campaign.create')
            <a href="{{ route('admin.alumni.tracer-study.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Buat Kampanye Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-layer-group fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Kampanye</div>
                        <div class="fw-bold">{{ $totalCampaigns }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Kampanye Aktif</div>
                        <div class="fw-bold">{{ $activeCampaigns }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-paper-plane fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Undangan Terkirim</div>
                        <div class="fw-bold">{{ number_format($totalSent, 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-chart-line fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Rata-Rata Respon (Rate)</div>
                        <div class="fw-bold">{{ $avgResponseRate }}%</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.alumni.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Pelaksanaan Survei Lulusan</h4>
                <div class="text-muted small">Pemantauan statistik keterisian kuesioner pelacakan di berbagai tahun lulusan.</div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Kampanye Survei</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $totalCampaigns }}</div>
                            <i class="fa fa-folder-open fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Survei Sedang Berjalan (Aktif)</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $activeCampaigns }}</div>
                            <i class="fa fa-sync fa-spin fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Undangan Kuesioner Terkirim</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($totalSent, 0, ',', '.') }}</div>
                            <i class="fa fa-envelope-open-text fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Rata-Rata Tingkat Partisipasi</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $avgResponseRate }}%</div>
                            <i class="fa fa-percent fs-4 text-warning opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Kampanye Tracer Study</h4>
                    <span class="text-muted small">Judul kampanye, sasaran tahun akademik lulusan, periode survei, serta statistik response rate.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:alumni.tracer-study-campaign-table />
        </div>
    </div>
</div>
