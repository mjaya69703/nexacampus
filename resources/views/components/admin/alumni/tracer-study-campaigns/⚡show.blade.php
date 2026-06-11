<?php

use App\Enums\CampaignStatus;
use App\Models\Alumni\TracerStudyCampaign;
use Livewire\Component;

new class extends Component
{
    public TracerStudyCampaign $campaign;

    public function mount($id): void
    {
        $this->campaign = TracerStudyCampaign::query()
            ->with(['academicYear'])
            ->withCount('responses')
            ->findOrFail($id);
    }

    public function goBack(): void
    {
        $this->redirectRoute('admin.alumni.tracer-study.index');
    }

    public function render()
    {
        $statuses = CampaignStatus::options();

        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni',
            'pages' => 'Detail Tracer Study',
            'statuses' => $statuses,
        ]);
    }
};
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Detail Kampanye Tracer Study</h5>
                <div>
                    @activecan('tracer-study-campaign.update')
                    <a href="{{ route('admin.alumni.tracer-study.edit', ['id' => $campaign->id]) }}" class="btn btn-warning">
                        <i class="fas fa-pencil me-1"></i> Edit
                    </a>
                    @endactivecan
                    <a href="{{ route('admin.alumni.tracer-study.responses', ['id' => $campaign->id]) }}" class="btn btn-info">
                        <i class="fas fa-file-lines me-1"></i> Respon
                    </a>
                    <button class="btn btn-secondary" wire:click="goBack">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Judul</label>
                        <div class="h6 mb-0">{{ $campaign->title }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Tahun Akademik</label>
                        <div class="h6 mb-0">{{ $campaign->academicYear?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Status</label>
                        <div><span class="badge {{ $campaign->status === 'active' ? 'bg-success' : ($campaign->status === 'closed' ? 'bg-secondary' : 'bg-warning text-dark') }}">{{ $statuses[$campaign->status] ?? $campaign->status }}</span></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Periode</label>
                        <div class="h6 mb-0">{{ $campaign->start_date?->format('d M Y') }} - {{ $campaign->end_date?->format('d M Y') }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Terkirim / Direspon</label>
                        <div class="h6 mb-0">{{ $campaign->total_sent }} / {{ $campaign->total_responded }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Response Rate</label>
                        <div class="h6 mb-0">{{ $campaign->responseRate() }}%</div>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Deskripsi</label>
                        <div class="p-2 bg-light rounded">{!! nl2br(e($campaign->description ?? '-')) !!}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Pertanyaan Survey ({{ count($campaign->questions ?? []) }})</h5>
            </div>
            <div class="card-body">
                @if (empty($campaign->questions))
                    <p class="text-muted">Belum ada pertanyaan.</p>
                @else
                    @foreach ($campaign->questions as $i => $q)
                        <div class="mb-3 p-3 border rounded">
                            <strong>{{ $i + 1 }}. {{ $q['text'] }}</strong>
                            <span class="badge bg-secondary ms-2">{{ $q['type'] }}</span>
                            @if (!empty($q['options']))
                                <ul class="mt-2 mb-0">
                                    @foreach ($q['options'] as $opt)
                                        <li>{{ $opt }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
