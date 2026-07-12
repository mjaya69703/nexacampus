<?php

use App\Models\Alumni\TracerStudyCampaign;
use App\Models\Alumni\TracerStudyResponse;
use Livewire\Component;

new class extends Component
{
    public ?int $campaignId = null;
    public string $campaignTitle = '';
    public int $totalResponses = 0;
    public int $employedCount = 0;
    public int $furtherStudyCount = 0;
    public int $highRelevanceCount = 0;

    public function mount($id = null): void
    {
        if ($id !== null && $id !== '') {
            $campaign = TracerStudyCampaign::find($id);
            if ($campaign) {
                $this->campaignId = (int) $id;
                $this->campaignTitle = $campaign->title;
                $query = TracerStudyResponse::where('tracer_study_campaign_id', $this->campaignId);
            } else {
                $this->campaignId = null;
                $this->campaignTitle = 'Seluruh Kampanye';
                $query = TracerStudyResponse::query();
            }
        } else {
            $this->campaignId = null;
            $this->campaignTitle = 'Seluruh Kampanye';
            $query = TracerStudyResponse::query();
        }

        $this->totalResponses = (clone $query)->count();
        $this->employedCount = (clone $query)->whereIn('employment_status', ['employed', 'entrepreneur', 'freelance'])->count();
        $this->furtherStudyCount = (clone $query)->where('further_study', true)->count();
        $this->highRelevanceCount = (clone $query)->whereIn('job_relevance', ['highly_related', 'related'])->count();
    }

    public function goBack(): void
    {
        if ($this->campaignId) {
            $this->redirectRoute('admin.alumni.tracer-study.show', ['id' => $this->campaignId]);
        } else {
            $this->redirectRoute('admin.alumni.tracer-study.index');
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni Management',
            'pages' => $this->campaignId ? 'Respon Tracer Study: ' . $this->campaignTitle : 'Seluruh Respon Tracer Study',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.alumni.header
        title="Daftar Respon: {{ $campaignTitle }}"
        description="Pantau dan kelola {{ $campaignId ? 'jawaban kuesioner dari alumni untuk kampanye ini.' : 'seluruh jawaban kuesioner pelacakan karir lulusan dari semua kampanye survei.' }}"
        icon="file-lines"
    >
        @if ($campaignId)
            <button type="button" wire:click="goBack" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke Detail Kampanye</span>
            </button>
        @else
            <button type="button" wire:click="goBack" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-arrow-left"></i> <span>Daftar Kampanye Tracer Study</span>
            </button>
        @endif

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-users fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Respon Masuk</div>
                        <div class="fw-bold">{{ $totalResponses }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-briefcase fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Bekerja / Usaha</div>
                        <div class="fw-bold">{{ $employedCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-graduation-cap fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Studi Lanjut</div>
                        <div class="fw-bold">{{ $furtherStudyCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-double fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Relevansi Bidang</div>
                        <div class="fw-bold">{{ $highRelevanceCount }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.alumni.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Karir & Relevansi Lulusan</h4>
                <div class="text-muted small">Pemetaan singkat status pekerjaan dan kesesuaian keahlian prodi dari responden yang masuk.</div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Respon Masuk</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $totalResponses }}</div>
                            <i class="fa fa-file-contract fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Bekerja / Wiraswasta</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $employedCount }}</div>
                            <i class="fa fa-user-check fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Melanjutkan Studi (S2/S3)</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $furtherStudyCount }}</div>
                            <i class="fa fa-user-graduate fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Relevansi Pekerjaan Tinggi</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $highRelevanceCount }}</div>
                            <i class="fa fa-award fs-4 text-warning opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Laporan Respon Alumni</h4>
                    <span class="text-muted small">Nama alumni, NIM, program studi, tahun lulus, status kerja terkini, relevansi, serta waktu pengisian survei.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:alumni.tracer-study-response-table :campaign-id="$campaignId" />
        </div>
    </div>
</div>
