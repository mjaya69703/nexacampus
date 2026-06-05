<?php

use App\Models\Organization\EdomPeriod;
use App\Support\Organization\EdomService;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public array $targets = [];
    public ?string $periodName = null;

    public function mount(): void
    {
        $period = EdomPeriod::query()->where('status', 'open')->latest('starts_at')->first();
        $this->periodName = $period?->name;
        $this->targets = app(EdomService::class)->eligibleTargetsForStudent(auth()->user(), $period)->all();
    }

    public function render(): View
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Academic', 'pages' => 'Evaluasi Dosen']);
    }
};
?>

@include('components.lecturer.assignments.assignment-styles')

<div>
    <x-alert />

    <div class="card assignment-card assignment-hero mb-4">
        <div class="card-body p-4 p-lg-5" style="position:relative;">
            <div class="d-flex justify-content-between gap-3 flex-wrap">
                <div class="d-flex gap-3">
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.75rem;"><i class="fas fa-star"></i></span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Ruang Belajar Mahasiswa</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">Evaluasi Dosen</h1>
                        <div style="opacity:.9;">Isi evaluasi untuk kelas yang kamu ambil. Dosen hanya melihat hasil agregat, bukan identitas pengisi.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-list-check"></i>{{ count($targets) }} evaluasi</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-check-circle"></i>{{ collect($targets)->where('completed', true)->count() }} terkirim</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-clock"></i>{{ collect($targets)->where('completed', false)->count() }} belum diisi</span>
                        </div>
                    </div>
                </div>
                @if ($periodName)
                    <div class="assignment-panel" style="min-width:min(100%, 280px);background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.28);color:white;">
                        <div style="opacity:.86;font-weight:700;">Periode Aktif</div>
                        <div class="h4 mb-0" style="font-weight:900;">{{ $periodName }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="assignment-panel h-100"><div class="text-secondary">Tersedia</div><div class="h2 fw-bold mb-0">{{ count($targets) }}</div></div></div>
        <div class="col-md-4"><div class="assignment-panel h-100"><div class="text-secondary">Belum Diisi</div><div class="h2 fw-bold mb-0 text-warning">{{ collect($targets)->where('completed', false)->count() }}</div></div></div>
        <div class="col-md-4"><div class="assignment-panel h-100"><div class="text-secondary">Terkirim</div><div class="h2 fw-bold mb-0 text-success">{{ collect($targets)->where('completed', true)->count() }}</div></div></div>
    </div>

    <div class="card assignment-card">
        <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-list-check me-2 text-primary"></i>Daftar Evaluasi</h3>
            <span class="assignment-pill">{{ collect($targets)->where('completed', false)->count() }} perlu diisi</span>
        </div>
        <div class="card-body p-4">
            <div class="assignment-shell">
                @forelse ($targets as $target)
                    <div class="assignment-list-item">
                        <div class="row g-3 align-items-center">
                            <div class="col-xl-7">
                                <div class="d-flex gap-3">
                                    <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-user-check"></i></span>
                                    <div>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            @if ($target['completed'])
                                                <span class="assignment-pill" style="background:#dcfce7;color:#15803d;"><i class="fas fa-check-circle"></i>Terkirim</span>
                                            @else
                                                <span class="assignment-pill" style="background:#fef3c7;color:#b45309;"><i class="fas fa-clock"></i>Belum diisi</span>
                                            @endif
                                            <span class="assignment-pill"><i class="fas fa-calendar"></i>{{ $target['academic_year'] }}</span>
                                        </div>
                                        <div class="fw-bold">{{ $target['course'] }}</div>
                                        <div class="text-secondary small">{{ $target['label'] }}</div>
                                        <div class="text-secondary small mt-1"><i class="fas fa-chalkboard-teacher me-1"></i>{{ $target['lecturer'] }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3">
                                <div class="assignment-panel">
                                    <div class="text-secondary small">{{ $target['completed'] ? 'Waktu Kirim' : 'Status' }}</div>
                                @if ($target['completed'])
                                        <div class="fw-bold text-success">{{ $target['submitted_at'] }}</div>
                                @else
                                        <div class="fw-bold text-warning">Menunggu jawaban</div>
                                        <div class="text-secondary small">Butuh beberapa menit</div>
                                @endif
                                </div>
                            </div>
                            <div class="col-xl-2 text-xl-end">
                                @if (! $target['completed'])
                                    <a class="assignment-action" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;" href="{{ route('student.edom.show', [$target['period_id'], $target['course_offering_id'], $target['lecturer_profile_id']]) }}"><i class="fas fa-pen"></i>Isi Evaluasi</a>
                                @else
                                    <span class="assignment-pill" style="background:#f1f5f9;color:#64748b;"><i class="fas fa-lock"></i>Selesai</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-secondary py-5">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <div>Belum ada evaluasi yang tersedia.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
