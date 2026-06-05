<?php

use App\Support\Organization\AcademicLeaderOversightService;
use Livewire\Component;

new class extends Component
{
    public string $search = '';
    public string $risk = 'all';

    public function riskStyle(string $risk): string
    {
        return match ($risk) {
            'danger' => 'background:#fee2e2;color:#dc2626;',
            'warning' => 'background:#fef3c7;color:#b45309;',
            default => 'background:#dcfce7;color:#15803d;',
        };
    }

    public function riskLabel(string $risk): string
    {
        return match ($risk) {
            'danger' => 'Kritis',
            'warning' => 'Perlu Perhatian',
            default => 'Terkendali',
        };
    }

    public function render()
    {
        $service = app(AcademicLeaderOversightService::class);
        $allRows = $service->classRows();
        $needle = strtolower(trim($this->search));
        $rows = $allRows
            ->filter(function (array $row) use ($needle) {
                $haystack = strtolower($row['course'].' '.$row['code'].' '.$row['program'].' '.implode(' ', $row['lecturers']));
                $matchesSearch = $needle === '' || str_contains($haystack, $needle);
                $matchesRisk = $this->risk === 'all' || $row['risk_level'] === $this->risk;

                return $matchesSearch && $matchesRisk;
            })
            ->values();

        return $this->view([
            'rows' => $rows,
            'stats' => [
                'classes' => $allRows->count(),
                'opened' => $allRows->sum('opened_sessions'),
                'problem' => $allRows->whereIn('risk_level', ['warning', 'danger'])->count(),
                'low_attendance' => $allRows->filter(fn ($row) => $row['attendance_rate'] !== null && $row['attendance_rate'] < 70)->count(),
            ],
        ])->layout('layouts.app', ['menus' => 'Pemantauan Akademik', 'pages' => 'Kelas dan Kehadiran']);
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
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.75rem;"><i class="fas fa-chalkboard"></i></span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Pemantauan Akademik</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">Kelas & Kehadiran</h1>
                        <div style="opacity:.9;">Monitor kelas aktif, dosen pengampu, sesi absensi, dan kepatuhan pertemuan.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-chalkboard"></i>{{ $stats['classes'] }} kelas</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-door-open"></i>{{ $stats['opened'] }} sesi dibuka</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-triangle-exclamation"></i>{{ $stats['problem'] }} perlu perhatian</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('academic-leader.reports.export', ['type' => 'classes', 'format' => 'xlsx']) }}" class="assignment-action" style="background:rgba(255,255,255,.95);color:#047857;"><i class="fas fa-file-excel"></i>Export</a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Kelas</div><div class="h2 fw-bold mb-0">{{ $stats['classes'] }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Sesi Dibuka</div><div class="h2 fw-bold text-primary mb-0">{{ $stats['opened'] }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Perlu Perhatian</div><div class="h2 fw-bold text-warning mb-0">{{ $stats['problem'] }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Kehadiran Rendah</div><div class="h2 fw-bold text-danger mb-0">{{ $stats['low_attendance'] }}</div></div></div>
    </div>

    <div class="card assignment-card">
        <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-list-check me-2 text-primary"></i>Monitoring Kelas</h3>
            <div class="d-flex gap-2 flex-wrap">
                <input type="search" class="form-control" style="width:280px;border-radius:12px;" wire:model.live.debounce.400ms="search" placeholder="Cari kelas, kode, prodi, dosen...">
                <select class="form-select" style="width:180px;border-radius:12px;" wire:model.live="risk">
                    <option value="all">Semua status</option>
                    <option value="success">Terkendali</option>
                    <option value="warning">Perlu perhatian</option>
                    <option value="danger">Kritis</option>
                </select>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="assignment-shell">
                @forelse ($rows as $row)
                    <div class="assignment-list-item">
                        <div class="row g-3 align-items-center">
                            <div class="col-xl-4">
                                <div class="fw-bold">{{ $row['course'] }}</div>
                                <div class="text-secondary small">Kelas {{ $row['label'] }} / {{ $row['code'] }}</div>
                                <div class="text-secondary small">{{ $row['program'] }}</div>
                            </div>
                            <div class="col-xl-3">
                                <div class="text-secondary small mb-1">Dosen Pengampu</div>
                                <div class="fw-bold">{{ implode(', ', $row['lecturers']) ?: 'Belum ada dosen' }}</div>
                            </div>
                            <div class="col-xl-3">
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="assignment-pill"><i class="fas fa-users"></i>{{ $row['student_count'] }} mhs</span>
                                    <span class="assignment-pill"><i class="fas fa-calendar-check"></i>{{ $row['session_count'] }}/{{ $row['target_meetings'] ?: '-' }} sesi</span>
                                    <span class="assignment-pill"><i class="fas fa-door-open"></i>{{ $row['opened_sessions'] }} dibuka</span>
                                    <span class="assignment-pill"><i class="fas fa-clock"></i>{{ $row['opened_too_long'] }} belum ditutup</span>
                                </div>
                            </div>
                            <div class="col-xl-2 text-xl-end">
                                <span class="assignment-pill" style="{{ $this->riskStyle($row['risk_level']) }}">{{ $this->riskLabel($row['risk_level']) }}</span>
                                <div class="mt-2 fw-bold">{{ $row['attendance_rate'] !== null ? $row['attendance_rate'].'%' : '-' }}</div>
                                <div class="text-secondary small">Kehadiran</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-secondary py-5"><i class="fas fa-inbox fa-3x mb-3"></i><div>Data kelas tidak ditemukan.</div></div>
                @endforelse
            </div>
        </div>
    </div>
</div>
