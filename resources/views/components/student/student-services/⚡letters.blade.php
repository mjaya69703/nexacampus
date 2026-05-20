<?php

use App\Models\StudentService\ServiceLetterRequest;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;

    public $requests;

    public array $summary = [
        'total' => 0,
        'in_progress' => 0,
        'issued' => 0,
        'rejected' => 0,
    ];

    public function mount(): void
    {
        $studentProfile = auth()->user()?->studentProfile;
        $this->requests = collect();

        if (! $studentProfile) {
            return;
        }

        $this->hasProfile = true;
        $this->requests = ServiceLetterRequest::query()
            ->with('letterType')
            ->where('student_profile_id', $studentProfile->id)
            ->orderByDesc('created_at')
            ->get();

        $this->summary = [
            'total' => $this->requests->count(),
            'in_progress' => $this->requests->whereIn('status', ['submitted', 'under_review', 'revision_requested', 'approved'])->count(),
            'issued' => $this->requests->where('status', 'issued')->count(),
            'rejected' => $this->requests->where('status', 'rejected')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Student Services',
            'pages' => 'Letter Requests',
        ]);
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'issued' => 'bg-green-lt text-green',
            'approved' => 'bg-blue-lt text-blue',
            'under_review' => 'bg-indigo-lt text-indigo',
            'revision_requested' => 'bg-yellow-lt text-yellow',
            'rejected', 'cancelled' => 'bg-red-lt text-red',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'revision_requested' => 'Perlu Perbaikan',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }
};
?>

@push('styles')
    <style>
        .service-card {
            border: 0;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            background: white;
            transition: all 0.3s ease;
        }
        .service-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        .hero-gradient {
            border-radius: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        .hero-gradient::before {
            content: '';
            position: absolute;
            inset: -60% -30% auto auto;
            width: 420px;
            height: 420px;
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
        }
        .metric-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }
        .info-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.42rem 0.75rem;
            background: rgba(255,255,255,0.18);
            border-radius: 8px;
            color: white;
            font-size: 0.84rem;
            backdrop-filter: blur(10px);
        }
        .request-card {
            border: 2px solid transparent;
            border-radius: 16px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1.25rem;
            transition: all 0.3s ease;
        }
        .request-card:hover {
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
        }
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 10px;
            padding: 0.6rem 1rem;
            border: 0;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card service-card hero-gradient mb-4">
        <div class="card-body p-4 p-lg-5" style="position: relative;">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="d-flex align-items-start gap-3">
                        <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                            <i class="fas fa-hands-helping"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Student Services</div>
                            <h1 class="h2 mb-2" style="font-weight: 800;">Layanan Surat Mahasiswa</h1>
                            <div style="opacity: 0.9; margin-bottom: 1rem;">Ajukan surat administrasi, pantau review, dan download surat yang sudah diterbitkan.</div>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="info-badge"><i class="fas fa-file-lines me-2"></i>{{ $summary['total'] }} request</span>
                                <span class="info-badge"><i class="fas fa-clock me-2"></i>{{ $summary['in_progress'] }} diproses</span>
                                <span class="info-badge"><i class="fas fa-circle-check me-2"></i>{{ $summary['issued'] }} terbit</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="{{ route('student.student-services.letters.create') }}" class="action-btn" style="background: rgba(255,255,255,0.94); color: #4f46e5;">
                        <i class="fas fa-plus"></i> Ajukan Surat
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([['label' => 'Total Request', 'value' => $summary['total'], 'icon' => 'fa-file-lines', 'color' => '#3b82f6'], ['label' => 'Diproses', 'value' => $summary['in_progress'], 'icon' => 'fa-clock', 'color' => '#f59e0b'], ['label' => 'Terbit', 'value' => $summary['issued'], 'icon' => 'fa-circle-check', 'color' => '#10b981'], ['label' => 'Ditolak', 'value' => $summary['rejected'], 'icon' => 'fa-circle-xmark', 'color' => '#ef4444']] as $card)
            <div class="col-md-3">
                <div class="card service-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="metric-icon" style="background: {{ $card['color'] }}22; color: {{ $card['color'] }};">
                            <i class="fas {{ $card['icon'] }}"></i>
                        </span>
                        <div>
                            <div class="text-muted small">{{ $card['label'] }}</div>
                            <div class="h3 mb-0 fw-bold">{{ $card['value'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card service-card">
        <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 w-100">
            <div>
                <h3 class="card-title mb-0">Riwayat Pengajuan</h3>
                <small class="text-muted">Semua request surat administrasi kamu.</small>
            </div>
                <span class="badge bg-indigo-lt text-indigo px-3 py-2">{{ $summary['total'] }} request</span>
            </div>
        </div>
        <div class="card-body p-4">
            @if (! $hasProfile)
                <div class="text-center text-muted py-5">Student profile belum tersedia.</div>
            @elseif ($requests->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                    <div class="h5">Belum ada pengajuan surat.</div>
                    <a href="{{ route('student.student-services.letters.create') }}" class="btn btn-primary mt-2">Ajukan Surat Pertama</a>
                </div>
            @else
                <div class="d-grid gap-3">
                    @foreach ($requests as $request)
                        <div class="request-card">
                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                <div class="d-flex gap-3">
                                    <div class="metric-icon" style="width: 48px; height: 48px; background: #ede9fe; color: #7c3aed;">
                                        <i class="fas fa-file-signature"></i>
                                    </div>
                                    <div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                            <span class="badge {{ $this->statusClass($request->status) }} px-3 py-2">{{ $this->statusLabel($request->status) }}</span>
                                            <span class="badge bg-secondary-lt text-secondary px-3 py-2">{{ str($request->letterType?->fulfillment_mode)->replace('_', ' ')->title() }}</span>
                                        </div>
                                        <h3 class="h4 mb-1" style="font-weight: 800; color: #111827;">{{ $request->letterType?->name }}</h3>
                                        <div class="text-secondary small">
                                            <i class="fas fa-hashtag me-1"></i>{{ $request->request_number }}
                                            <span class="mx-2">/</span>
                                            <i class="fas fa-calendar me-1"></i>{{ $request->created_at?->format('d M Y H:i') }}
                                        </div>
                                        <div class="small mt-2 text-secondary">{{ str($request->purpose)->limit(150) }}</div>
                                    </div>
                                </div>
                                <a href="{{ route('student.student-services.letters.show', ['id' => $request->id]) }}" class="action-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                    <i class="fas fa-eye"></i> Detail
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
