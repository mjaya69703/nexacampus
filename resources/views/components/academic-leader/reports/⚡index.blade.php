<?php

use App\Support\Organization\AcademicLeaderOversightService;
use Livewire\Component;

new class extends Component
{
    public function render()
    {
        $service = app(AcademicLeaderOversightService::class);

        return $this->view([
            'stats' => $service->dashboardStats(),
            'alerts' => $service->alerts()->take(8),
        ])->layout('layouts.app', ['menus' => 'Pemantauan Akademik', 'pages' => 'Laporan']);
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
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.75rem;"><i class="fas fa-file-export"></i></span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Pemantauan Akademik</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">Laporan</h1>
                        <div style="opacity:.9;">Export laporan dosen, kelas, kehadiran, dan alert sesuai scope jabatan aktif.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-user-tie"></i>{{ $stats['lecturers'] }} dosen</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-chalkboard"></i>{{ $stats['classes'] }} kelas</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-bell"></i>{{ $stats['alerts'] }} alert</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('academic-leader.dashboard.index') }}" class="assignment-action" style="background:rgba(255,255,255,.95);color:#4f46e5;"><i class="fas fa-arrow-left"></i>Dashboard</a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        @foreach ([
            ['lecturers', 'Dosen Dalam Scope', 'fas fa-user-tie', 'Data dosen, BKD terakhir, kelas aktif, dan performa.'],
            ['classes', 'Kelas & Kehadiran', 'fas fa-chalkboard', 'Data kelas, dosen pengampu, pertemuan, dan kehadiran.'],
            ['alerts', 'Alert Akademik', 'fas fa-bell', 'Daftar kelas atau dosen yang perlu perhatian pimpinan.'],
        ] as [$type, $title, $icon, $description])
            <div class="col-lg-4">
                <div class="assignment-panel h-100">
                    <div class="d-flex gap-3 mb-3">
                        <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="{{ $icon }}"></i></span>
                        <div>
                            <div class="fw-bold">{{ $title }}</div>
                            <div class="text-secondary small">{{ $description }}</div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('academic-leader.reports.export', ['type' => $type, 'format' => 'csv']) }}" class="assignment-action" style="background:#f8fafc;color:#334155;border:1px solid #e2e8f0;"><i class="fas fa-file-csv"></i>CSV</a>
                        <a href="{{ route('academic-leader.reports.export', ['type' => $type, 'format' => 'xlsx']) }}" class="assignment-action" style="background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;"><i class="fas fa-file-excel"></i>Excel</a>
                        <a href="{{ route('academic-leader.reports.export', ['type' => $type, 'format' => 'pdf']) }}" class="assignment-action" style="background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;"><i class="fas fa-file-pdf"></i>PDF</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card assignment-card mt-4">
        <div class="card-header py-3"><h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-bell me-2 text-primary"></i>Alert Terbaru</h3></div>
        <div class="card-body p-4">
            <div class="assignment-shell">
                @forelse ($alerts as $alert)
                    <div class="assignment-list-item">
                        <div class="d-flex justify-content-between gap-3">
                            <div>
                                <span class="assignment-pill mb-2" style="{{ match($alert['level']) {
                                    'danger' => 'background:#fee2e2;color:#dc2626;',
                                    'warning' => 'background:#fef3c7;color:#b45309;',
                                    default => 'background:#e0f2fe;color:#0369a1;',
                                } }}">{{ ucfirst($alert['level']) }}</span>
                                <div class="fw-bold">{{ $alert['title'] }}</div>
                                <div class="text-secondary small">{{ $alert['description'] }}</div>
                            </div>
                            <span class="assignment-pill">{{ $alert['target'] }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-secondary py-5"><i class="fas fa-circle-check fa-3x mb-3"></i><div>Tidak ada alert aktif.</div></div>
                @endforelse
            </div>
        </div>
    </div>
</div>
