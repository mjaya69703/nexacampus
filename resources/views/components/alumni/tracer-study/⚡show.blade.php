<?php

use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\TracerStudyCampaign;
use App\Models\Alumni\TracerStudyResponse;
use Livewire\Component;

new class extends Component
{
    public TracerStudyCampaign $campaign;
    public ?TracerStudyResponse $response = null;
    public bool $hasResponded = false;

    public function mount($id): void
    {
        $this->campaign = TracerStudyCampaign::query()
            ->with('academicYear')
            ->findOrFail($id);

        $profile = AlumniProfile::query()->where('user_id', auth()->id())->first();
        abort_unless($profile, 404);

        $this->response = TracerStudyResponse::query()
            ->where('tracer_study_campaign_id', $this->campaign->id)
            ->where('alumni_profile_id', $profile->id)
            ->first();

        $this->hasResponded = (bool) $this->response;
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Tracer Study',
            'pages' => $this->campaign->title,
        ]);
    }
};
?>

<div>
    <x-alert />

    <a href="{{ route('alumni.tracer-study.index') }}" class="btn btn-outline-secondary mb-4">
        <i class="fas fa-arrow-left me-1"></i> Kembali
    </a>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @if ($hasResponded)
                            <span class="badge bg-green-lt text-green px-3 py-2">
                                <i class="fas fa-check-circle me-1"></i>Sudah Diisi
                            </span>
                        @else
                            <span class="badge bg-warning-lt text-warning px-3 py-2">
                                <i class="fas fa-clock me-1"></i>Belum Diisi
                            </span>
                        @endif
                    </div>

                    <h1 class="h2 fw-bold mb-2">{{ $campaign->title }}</h1>

                    @if ($campaign->description)
                        <div class="text-muted mb-4" style="white-space: pre-wrap; line-height: 1.8;">{{ $campaign->description }}</div>
                    @endif

                    <hr class="my-4">

                    <h4 class="fw-bold mb-3">Pertanyaan Survei</h4>
                    <div class="text-muted small mb-3">Berikut daftar pertanyaan yang perlu dijawab:</div>

                    @if ($campaign->questions)
                        <div class="d-grid gap-3">
                            @foreach ($campaign->questions as $i => $question)
                                <div class="border rounded p-3">
                                    <div class="fw-semibold mb-1">{{ $i + 1 }}. {{ $question['text'] ?? '-' }}</div>
                                    <span class="badge bg-secondary-lt text-secondary small">
                                        {{ match ($question['type'] ?? 'text') {
                                            'radio' => 'Pilihan Ganda',
                                            'select' => 'Dropdown',
                                            'textarea' => 'Teks Panjang',
                                            default => 'Teks',
                                        } }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-muted">Belum ada pertanyaan yang ditambahkan.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-3">Informasi Kampanye</h4>
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 45%;">Periode</td>
                            <td>{{ $campaign->start_date->format('d M Y') }} - {{ $campaign->end_date->format('d M Y') }}</td>
                        </tr>
                        @if ($campaign->academicYear)
                            <tr>
                                <td class="text-muted">Tahun Akademik</td>
                                <td>{{ $campaign->academicYear->name }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="text-muted">Jumlah Pertanyaan</td>
                            <td>{{ count($campaign->questions ?? []) }}</td>
                        </tr>
                        @if ($hasResponded && $response)
                            <tr>
                                <td class="text-muted">Waktu Submit</td>
                                <td class="fw-semibold text-success">{{ $response->submitted_at->format('d M Y, H:i') }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>

            @if (! $hasResponded && $campaign->status === 'active')
                <a href="{{ route('alumni.tracer-study.fill', ['id' => $campaign->id]) }}" class="btn btn-primary btn-lg w-100">
                    <i class="fas fa-pen me-2"></i> Isi Survei Sekarang
                </a>
            @elseif ($hasResponded)
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>Terima kasih! Kamu sudah mengisi survei ini.
                </div>
            @elseif ($campaign->status !== 'active')
                <div class="alert alert-secondary">
                    <i class="fas fa-info-circle me-2"></i>Survei ini sudah tidak aktif.
                </div>
            @endif
        </div>
    </div>
</div>
