<?php

use App\Models\Organization\LecturerWorkloadSubmission;
use App\Support\Organization\AcademicLeaderContext;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

new class extends Component
{
    public function statusLabel(string $status): string
    {
        return match ($status) {
            'in_approval' => 'Menunggu Approval',
            'approved' => 'Disetujui',
            'revision' => 'Perlu Revisi',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            default => 'Draf',
        };
    }

    public function statusStyle(string $status): string
    {
        return match ($status) {
            'in_approval' => 'background:#fef3c7;color:#b45309;',
            'approved' => 'background:#dcfce7;color:#15803d;',
            'revision' => 'background:#ffedd5;color:#ea580c;',
            'rejected' => 'background:#fee2e2;color:#dc2626;',
            'cancelled' => 'background:#f1f5f9;color:#64748b;',
            default => 'background:#eef2ff;color:#4338ca;',
        };
    }

    public function render()
    {
        $context = app(AcademicLeaderContext::class);
        $facultyIds = $context->facultyIds();
        $programIds = $context->studyProgramIds();

        $query = LecturerWorkloadSubmission::query()
            ->with(['owner', 'period', 'lecturerProfile.studyProgram'])
            ->whereHas('lecturerProfile', function (Builder $query) use ($facultyIds, $programIds) {
                $query->where(function (Builder $nested) use ($facultyIds, $programIds) {
                    if ($programIds) $nested->orWhereIn('study_program_id', $programIds);
                    if ($facultyIds) $nested->orWhereIn('faculty_id', $facultyIds);
                    if (! $programIds && ! $facultyIds) $nested->whereRaw('1 = 0');
                });
            })
            ->latest('updated_at');

        $statsQuery = clone $query;
        $rows = $query->paginate(12);

        return $this->view([
            'rows' => $rows,
            'hasScope' => $context->hasScope(),
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'approval' => (clone $statsQuery)->where('status', 'in_approval')->count(),
                'approved' => (clone $statsQuery)->where('status', 'approved')->count(),
                'avg_sks' => (clone $statsQuery)->avg('total_sks') ?: 0,
            ],
        ])->layout('layouts.app', ['menus' => 'Pemantauan Akademik', 'pages' => 'BKD Dosen']);
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
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.75rem;"><i class="fas fa-scale-balanced"></i></span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Pemantauan Akademik</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">BKD Dosen</h1>
                        <div style="opacity:.9;">Lihat beban kerja dosen sesuai fakultas atau program studi yang berada dalam scope jabatan aktif.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-file-signature"></i>{{ $stats['total'] }} pengajuan</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-clock"></i>{{ $stats['approval'] }} approval</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-check-circle"></i>{{ $stats['approved'] }} disetujui</span>
                        </div>
                    </div>
                </div>
                <div class="assignment-panel" style="min-width:min(100%, 260px);background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.28);color:white;">
                    <div style="opacity:.86;font-weight:700;">Rata-rata SKS</div>
                    <div class="h2 mb-0" style="font-weight:900;">{{ number_format($stats['avg_sks'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    @unless ($hasScope)
        <div class="assignment-panel mb-4" style="background:#fffbeb;border-color:#fde68a;">
            <div class="fw-bold text-warning"><i class="fas fa-triangle-exclamation me-2"></i>Scope belum tersedia</div>
            <div class="text-secondary mt-1">Akun ini belum memiliki scope fakultas atau program studi aktif.</div>
        </div>
    @endunless

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Pengajuan</div><div class="h2 fw-bold mb-0">{{ $stats['total'] }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Approval</div><div class="h2 fw-bold text-warning mb-0">{{ $stats['approval'] }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Disetujui</div><div class="h2 fw-bold text-success mb-0">{{ $stats['approved'] }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Rata-rata SKS</div><div class="h2 fw-bold text-primary mb-0">{{ number_format($stats['avg_sks'], 2) }}</div></div></div>
    </div>

    <div class="card assignment-card">
        <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-list-check me-2 text-primary"></i>Daftar BKD</h3>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="assignment-pill">{{ $rows->total() }} data</span>
                <a href="{{ route('academic-leader.workloads.export', 'xlsx') }}" class="assignment-action" style="background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;"><i class="fas fa-file-excel"></i>Excel</a>
                <a href="{{ route('academic-leader.workloads.export', 'pdf') }}" class="assignment-action" style="background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;"><i class="fas fa-file-pdf"></i>PDF</a>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="assignment-shell">
                @forelse ($rows as $row)
                    <div class="assignment-list-item">
                        <div class="row g-3 align-items-center">
                            <div class="col-xl-6">
                                <div class="d-flex gap-3">
                                    <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-user-tie"></i></span>
                                    <div>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="assignment-pill" style="{{ $this->statusStyle($row->status) }}">{{ $this->statusLabel($row->status) }}</span>
                                            <span class="assignment-pill"><i class="fas fa-calendar"></i>{{ $row->period?->name ?? '-' }}</span>
                                        </div>
                                        <a class="fw-bold text-decoration-none" href="{{ route('academic-leader.lecturers.show', $row->lecturer_profile_id) }}">{{ $row->owner?->name ?? '-' }}</a>
                                        <div class="text-secondary small">{{ $row->lecturerProfile?->studyProgram?->name ?? '-' }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4">
                                <div class="assignment-panel">
                                    <div class="d-flex justify-content-between border-bottom py-1"><span>Mengajar</span><strong>{{ $row->teaching_sks }}</strong></div>
                                    <div class="d-flex justify-content-between border-bottom py-1"><span>Jabatan</span><strong>{{ $row->structural_sks }}</strong></div>
                                    <div class="d-flex justify-content-between pt-1"><span>Tridharma</span><strong>{{ $row->tridharma_sks }}</strong></div>
                                </div>
                            </div>
                            <div class="col-xl-2 text-xl-end">
                                <div class="h3 mb-0">{{ $row->total_sks }} SKS</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-secondary py-5">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <div>Belum ada data BKD pada scope ini.</div>
                    </div>
                @endforelse
            </div>
        </div>
        <div class="card-footer">{{ $rows->links() }}</div>
    </div>
</div>
