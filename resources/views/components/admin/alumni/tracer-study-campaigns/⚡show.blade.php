<?php

use App\Enums\CampaignStatus;
use App\Models\Alumni\TracerStudyCampaign;
use Livewire\Component;

new class extends Component
{
    public TracerStudyCampaign $campaign;
    public array $statuses = [];

    public function mount($id): void
    {
        $this->campaign = TracerStudyCampaign::query()
            ->with(['academicYear'])
            ->withCount('responses')
            ->findOrFail($id);
        $this->statuses = CampaignStatus::options();
    }

    public function goBack(): void
    {
        $this->redirectRoute('admin.alumni.tracer-study.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni Management',
            'pages' => 'Detail Tracer Study',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.alumni.header
        title="Detail Kampanye: {{ $campaign->title }}"
        description="Informasi sasaran tahun akademik lulusan, rentang periode pelaksanaan, serta instrumen kuesioner survei."
        icon="clipboard-list"
    >
        @activecan('tracer-study-campaign.update')
            <a href="{{ route('admin.alumni.tracer-study.edit', ['id' => $campaign->id]) }}" class="btn btn-sm btn-light text-warning fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-edit"></i> <span>Edit Kampanye</span>
            </a>
        @endactivecan
        <a href="{{ route('admin.alumni.tracer-study.responses', ['id' => $campaign->id]) }}" class="btn btn-sm btn-light text-info fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-file-lines"></i> <span>Lihat Hasil Respon</span>
        </a>
        <button type="button" wire:click="goBack" class="btn btn-sm btn-outline-light text-white border-opacity-50 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </button>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Sasaran Tahun</div>
                        <div class="fw-bold">{{ $campaign->academicYear?->name ?? 'Semua Angkatan' }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Periode Survei</div>
                        <div class="fw-bold">{{ $campaign->start_date?->format('d M Y') }} - {{ $campaign->end_date?->format('d M Y') }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-users fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Direspon / Terkirim</div>
                        <div class="fw-bold">{{ $campaign->total_responded }} / {{ $campaign->total_sent }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-chart-pie fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tingkat Respon</div>
                        <div class="fw-bold">{{ $campaign->responseRate() }}%</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.alumni.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa fa-info-circle me-2 text-primary"></i>Informasi Umum Kampanye</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Judul Kampanye</label>
                            <div class="fw-bold fs-6 text-dark">{{ $campaign->title }}</div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Sasaran Tahun Lulusan</label>
                            <div class="fw-bold fs-6 text-dark">{{ $campaign->academicYear?->name ?? 'Semua Angkatan / Umum' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Status Kampanye</label>
                            <div>
                                @php
                                    $statusColors = [
                                        'active' => 'bg-success bg-opacity-10 text-success border border-success border-opacity-25',
                                        'closed' => 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25',
                                        'draft' => 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25'
                                    ];
                                @endphp
                                <span class="badge rounded-pill px-3 py-1.5 {{ $statusColors[$campaign->status] ?? 'bg-secondary' }}">{{ $statuses[$campaign->status] ?? ucfirst($campaign->status) }}</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Tanggal Mulai</label>
                            <div class="fw-bold fs-6 text-dark">{{ $campaign->start_date?->format('d M Y') ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Batas Akhir</label>
                            <div class="fw-bold fs-6 text-dark">{{ $campaign->end_date?->format('d M Y') ?? '-' }}</div>
                        </div>
                        <div class="col-12 mt-3">
                            <label class="form-label text-muted small mb-1">Deskripsi / Instruksi Survei</label>
                            <div class="p-3 bg-light rounded-3 text-dark lh-lg">{!! nl2br(e($campaign->description ?? 'Tidak ada pengantar atau deskripsi rinci.')) !!}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa fa-clipboard-question me-2 text-success"></i>Instrumen Kuesioner ({{ count($campaign->questions ?? []) }} Butir)</h5>
                </div>
                <div class="card-body p-4">
                    @if (empty($campaign->questions))
                        <div class="text-center py-4 text-muted">
                            <i class="fa fa-folder-open fs-2 mb-2 opacity-50"></i>
                            <p class="mb-0 small">Belum ada butir pertanyaan yang disusun untuk kampanye survei ini.</p>
                        </div>
                    @else
                        <div class="d-grid gap-3">
                            @foreach ($campaign->questions as $i => $q)
                                <div class="border rounded-4 p-3 bg-white shadow-sm">
                                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                        <div class="fw-bold text-dark fs-6">
                                            <span class="text-primary me-2">#{{ $i + 1 }}.</span> {{ $q['text'] }}
                                        </div>
                                        <span class="badge bg-light text-dark border px-2.5 py-1 text-uppercase small">{{ $q['type'] }}</span>
                                    </div>
                                    @if (!empty($q['options']) && is_array($q['options']))
                                        <div class="mt-2 ps-4">
                                            <div class="small text-muted mb-1">Opsi Jawaban Pilihan:</div>
                                            <div class="d-flex flex-wrap gap-2">
                                                @foreach ($q['options'] as $opt)
                                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-2.5 py-1 fw-normal">{{ $opt }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden bg-white p-4 text-center">
                <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 shadow-sm" style="width: 80px; height: 80px;">
                    <i class="fa fa-chart-pie fs-1"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Tingkat Partisipasi</h5>
                <p class="text-muted small mb-3">Dari total {{ $campaign->total_sent }} alumni yang menerima undangan.</p>

                <div class="fs-1 fw-bold text-dark lh-1 mb-2">{{ $campaign->responseRate() }}%</div>
                <div class="progress rounded-pill mb-3" style="height: 12px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ $campaign->responseRate() }}%;" aria-valuenow="{{ $campaign->responseRate() }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                <div class="d-flex justify-content-between text-muted small border-top pt-3">
                    <span>Sudah Diisi: <strong class="text-dark">{{ $campaign->total_responded }}</strong></span>
                    <span>Belum Respon: <strong class="text-dark">{{ max(0, $campaign->total_sent - $campaign->total_responded) }}</strong></span>
                </div>

                <div class="mt-4">
                    <a href="{{ route('admin.alumni.tracer-study.responses', ['id' => $campaign->id]) }}" class="btn btn-primary rounded-pill w-100 py-2.5 fw-semibold shadow-sm d-flex align-items-center justify-content-center gap-2">
                        <i class="fa fa-file-lines"></i> <span>Daftar Laporan & Jawaban Alumni</span>
                    </a>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <h6 class="fw-bold mb-2 text-dark"><i class="fa fa-share-nodes me-2 text-primary"></i>Tindak Lanjut Survei</h6>
                    <p class="text-muted small mb-0">Hasil pengisian survei oleh alumni dapat diunduh (eksport) melalui halaman hasil respon untuk analisis kemajuan karir serta pelaporan borang akreditasi.</p>
                </div>
            </div>
        </div>
    </div>
</div>
