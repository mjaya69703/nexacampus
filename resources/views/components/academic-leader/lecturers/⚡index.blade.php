<?php

use App\Support\Organization\AcademicLeaderOversightService;
use Livewire\Component;

new class extends Component
{
    public string $search = '';
    public string $status = 'all';

    public function statusStyle(?string $status): string
    {
        return match ($status) {
            'approved' => 'background:#dcfce7;color:#15803d;',
            'in_approval' => 'background:#fef3c7;color:#b45309;',
            'revision' => 'background:#ffedd5;color:#ea580c;',
            'rejected' => 'background:#fee2e2;color:#dc2626;',
            default => 'background:#eef2ff;color:#4338ca;',
        };
    }

    public function scoreStyle($score): string
    {
        if ($score === null) {
            return 'background:#f1f5f9;color:#64748b;';
        }

        $score = (float) $score;

        if ($score >= 80) return 'background:#dcfce7;color:#15803d;';
        if ($score >= 60) return 'background:#fef3c7;color:#b45309;';

        return 'background:#fee2e2;color:#dc2626;';
    }

    public function render()
    {
        $service = app(AcademicLeaderOversightService::class);
        $allRows = $service->lecturerRows();
        $needle = strtolower(trim($this->search));
        $rows = $allRows
            ->filter(function (array $row) use ($needle) {
                $haystack = strtolower($row['name'].' '.$row['nidn'].' '.$row['program'].' '.$row['faculty']);
                $matchesSearch = $needle === '' || str_contains($haystack, $needle);
                $matchesStatus = $this->status === 'all' || $row['workload_status'] === $this->status;

                return $matchesSearch && $matchesStatus;
            })
            ->values();

        return $this->view([
            'rows' => $rows,
            'total' => $rows->count(),
            'stats' => [
                'lecturers' => $allRows->count(),
                'classes' => $allRows->sum('active_classes'),
                'pending' => $allRows->whereIn('workload_status', ['none', 'draft', 'revision', 'in_approval'])->count(),
                'avg_score' => $allRows->filter(fn ($row) => $row['performance_score'] !== null)->avg('performance_score') ?: 0,
            ],
        ])->layout('layouts.app', ['menus' => 'Pemantauan Akademik', 'pages' => 'Dosen Dalam Scope']);
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
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.75rem;"><i class="fas fa-user-tie"></i></span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Pemantauan Akademik</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">Dosen Dalam Scope</h1>
                        <div style="opacity:.9;">Pusat navigasi dosen sesuai fakultas atau program studi yang dipimpin.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-user-tie"></i>{{ $stats['lecturers'] }} dosen</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-chalkboard"></i>{{ $stats['classes'] }} kelas aktif</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-star-half-stroke"></i>{{ number_format($stats['avg_score'], 2) }} performa</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('academic-leader.reports.export', ['type' => 'lecturers', 'format' => 'xlsx']) }}" class="assignment-action" style="background:rgba(255,255,255,.95);color:#047857;"><i class="fas fa-file-excel"></i>Export</a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Dosen</div><div class="h2 fw-bold mb-0">{{ $stats['lecturers'] }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Kelas Aktif</div><div class="h2 fw-bold text-primary mb-0">{{ $stats['classes'] }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">BKD Perlu Tindak Lanjut</div><div class="h2 fw-bold text-warning mb-0">{{ $stats['pending'] }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Rata-rata Performa</div><div class="h2 fw-bold text-success mb-0">{{ number_format($stats['avg_score'], 2) }}</div></div></div>
    </div>

    <div class="card assignment-card">
        <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-list me-2 text-primary"></i>Daftar Dosen</h3>
            <div class="d-flex gap-2 flex-wrap">
                <input type="search" class="form-control" style="width:260px;border-radius:12px;" wire:model.live.debounce.400ms="search" placeholder="Cari nama, NIDN, prodi...">
                <select class="form-select" style="width:190px;border-radius:12px;" wire:model.live="status">
                    <option value="all">Semua status BKD</option>
                    <option value="none">Belum ada BKD</option>
                    <option value="draft">Draf</option>
                    <option value="revision">Perlu revisi</option>
                    <option value="in_approval">Menunggu approval</option>
                    <option value="approved">Disetujui</option>
                </select>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="assignment-shell">
                @forelse ($rows as $row)
                    <div class="assignment-list-item">
                        <div class="row g-3 align-items-center">
                            <div class="col-xl-5">
                                <div class="d-flex gap-3">
                                    <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-user-tie"></i></span>
                                    <div>
                                        <a href="{{ route('academic-leader.lecturers.show', $row['id']) }}" class="fw-bold text-decoration-none">{{ $row['name'] }}</a>
                                        <div class="text-secondary small">{{ $row['nidn'] }} / {{ $row['program'] }}</div>
                                        <div class="text-secondary small">{{ $row['faculty'] }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2"><span class="assignment-pill"><i class="fas fa-chalkboard"></i>{{ $row['active_classes'] }} kelas</span></div>
                            <div class="col-xl-3">
                                <span class="assignment-pill" style="{{ $this->statusStyle($row['workload_status']) }}">{{ $row['workload_status_label'] }}</span>
                                <div class="text-secondary small mt-1">{{ $row['workload_sks'] }} SKS / {{ $row['workload_period'] }}</div>
                            </div>
                            <div class="col-xl-2 text-xl-end">
                                <span class="assignment-pill" style="{{ $this->scoreStyle($row['performance_score']) }}">Performa {{ $row['performance_score'] !== null ? number_format((float) $row['performance_score'], 2) : '-' }}</span>
                                <div class="text-secondary small mt-1">EDOM {{ $row['edom_score'] !== null ? number_format((float) $row['edom_score'], 2) : '-' }}</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-secondary py-5"><i class="fas fa-inbox fa-3x mb-3"></i><div>Data dosen tidak ditemukan.</div></div>
                @endforelse
            </div>
        </div>
    </div>
</div>
