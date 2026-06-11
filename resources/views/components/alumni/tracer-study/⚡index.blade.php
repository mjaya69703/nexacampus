<?php

use App\Enums\CampaignStatus;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\TracerStudyCampaign;
use App\Models\Alumni\TracerStudyResponse;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;
    public $campaigns;
    public array $responseStatus = [];

    public function mount(): void
    {
        $user = auth()->user();
        $profile = AlumniProfile::query()->where('user_id', $user->id)->first();

        if (! $profile) {
            return;
        }

        $this->hasProfile = true;

        $this->campaigns = TracerStudyCampaign::query()
            ->where('status', 'active')
            ->orderByDesc('start_date')
            ->get();

        // Check which campaigns this alumni has already responded to
        $respondedIds = TracerStudyResponse::query()
            ->where('alumni_profile_id', $profile->id)
            ->pluck('tracer_study_campaign_id')
            ->toArray();

        foreach ($this->campaigns as $campaign) {
            $this->responseStatus[$campaign->id] = in_array($campaign->id, $respondedIds);
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Tracer Study',
            'pages' => 'Tracer Study',
        ]);
    }
};
?>

<div>
    <x-alert />

    {{-- Hero --}}
    <div class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 16px;">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-start gap-3">
                <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div>
                    <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Portal Alumni</div>
                    <h1 class="h2 mb-2" style="font-weight: 800;">Tracer Study</h1>
                    <div style="opacity: 0.9;">Bantu kampus meningkatkan kualitas pendidikan dengan mengisi survei tracer study.</div>
                </div>
            </div>
        </div>
    </div>

    @if (! $hasProfile)
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                <h3>Profil Alumni Belum Tersedia</h3>
                <p class="text-muted">Hubungi administrator untuk mengaktifkan profil alumni kamu.</p>
            </div>
        </div>
    @elseif ($campaigns->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h4>Tidak ada tracer study aktif</h4>
                <p class="text-muted">Saat ini belum ada kampanye tracer study yang aktif untuk kamu.</p>
            </div>
        </div>
    @else
        <div class="d-grid gap-3">
            @foreach ($campaigns as $campaign)
                <div class="card">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    @if ($responseStatus[$campaign->id] ?? false)
                                        <span class="badge bg-green-lt text-green px-3 py-2">
                                            <i class="fas fa-check-circle me-1"></i>Sudah Diisi
                                        </span>
                                    @else
                                        <span class="badge bg-warning-lt text-warning px-3 py-2">
                                            <i class="fas fa-clock me-1"></i>Belum Diisi
                                        </span>
                                    @endif
                                </div>
                                <h4 class="fw-bold mb-1">{{ $campaign->title }}</h4>
                                @if ($campaign->description)
                                    <div class="text-muted small mb-2">{{ str($campaign->description)->limit(150) }}</div>
                                @endif
                                <div class="text-muted small">
                                    <i class="fas fa-calendar me-1"></i>
                                    {{ $campaign->start_date->format('d M Y') }} - {{ $campaign->end_date->format('d M Y') }}
                                    @if ($campaign->academicYear)
                                        &middot; <i class="fas fa-graduation-cap me-1"></i>{{ $campaign->academicYear->name }}
                                    @endif
                                </div>
                            </div>
                            <div>
                                @if ($responseStatus[$campaign->id] ?? false)
                                    <a href="{{ route('alumni.tracer-study.show', ['id' => $campaign->id]) }}" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i> Lihat Detail
                                    </a>
                                @else
                                    <a href="{{ route('alumni.tracer-study.fill', ['id' => $campaign->id]) }}" class="btn btn-primary">
                                        <i class="fas fa-pen me-1"></i> Isi Survei
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
