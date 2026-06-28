<?php

use App\Models\Academic\StudyResult;
use App\Models\Academic\TranscriptEntry;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;

    public bool $hasTranscriptEntries = false;

    public ?int $studentProfileId = null;

    public ?string $studentName = null;

    public ?string $studentNim = null;

    public ?string $studyProgramName = null;

    public ?string $academicStatus = null;

    public ?int $currentSemester = null;

    public array $studyResults = [];

    public array $transcriptEntries = [];

    public array $summary = [
        'total_courses' => 0,
        'total_credits' => 0,
        'passed_credits' => 0,
        'cumulative_gpa' => null,
    ];

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $studentProfile = $user->studentProfile()->with('studyProgram')->first();

        if (! $studentProfile) {
            return;
        }

        $this->hasProfile = true;
        $this->studentProfileId = $studentProfile->id;
        $this->studentName = $studentProfile->user?->name ?? '-';
        $this->studentNim = $studentProfile->nim ?? '-';
        $this->studyProgramName = $studentProfile->studyProgram?->name ?? '-';
        $this->academicStatus = $studentProfile->academic_status ?? '-';
        $this->currentSemester = $studentProfile->current_semester;

        $results = StudyResult::query()
            ->with('academicYear')
            ->where('student_profile_id', $this->studentProfileId)
            ->orderBy('semester_no')
            ->orderBy('id')
            ->get();

        $entries = TranscriptEntry::query()
            ->with(['course', 'academicYear'])
            ->where('student_profile_id', $this->studentProfileId)
            ->orderBy('semester_no')
            ->orderBy('course_id')
            ->orderByDesc('grade_point')
            ->get();

        $this->hasTranscriptEntries = $entries->isNotEmpty();

        $this->studyResults = $results
            ->map(fn (StudyResult $result) => [
                'academic_year' => $result->academicYear?->name ?? '-',
                'semester_no' => $result->semester_no,
                'total_courses' => (int) ($result->total_courses ?? 0),
                'credits_taken' => (int) ($result->total_credits_taken ?? 0),
                'credits_passed' => (int) ($result->total_credits_passed ?? 0),
                'semester_gpa' => $result->semester_gpa,
                'cumulative_gpa' => $result->cumulative_gpa,
                'status' => $result->status ?? '-',
            ])
            ->values()
            ->all();

        $this->transcriptEntries = $entries
            ->map(fn (TranscriptEntry $entry) => [
                'course_code' => $entry->course?->code ?? '-',
                'course_name' => $entry->course?->name ?? '-',
                'academic_year' => $entry->academicYear?->name ?? '-',
                'semester_no' => $entry->semester_no,
                'credits' => (int) ($entry->credits ?? 0),
                'final_score' => $entry->final_score,
                'letter_grade' => $entry->letter_grade,
                'grade_point' => $entry->grade_point,
                'result_status' => $entry->result_status,
            ])
            ->values()
            ->all();

        $latestResult = $results->sortByDesc('semester_no')->first();

        $this->summary = [
            'total_courses' => count($this->transcriptEntries),
            'total_credits' => (int) collect($this->transcriptEntries)->sum('credits'),
            'passed_credits' => (int) collect($this->transcriptEntries)->where('result_status', 'Passed')->sum('credits'),
            'cumulative_gpa' => $latestResult?->cumulative_gpa,
        ];
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            'Passed' => 'Lulus',
            'Failed' => 'Tidak Lulus',
            'Incomplete' => 'Belum Lengkap',
            'Withdrawn' => 'Mengundurkan Diri',
            'Cancelled' => 'Dibatalkan',
            'Published' => 'Terpublikasi',
            'Finalized' => 'Difinalisasi',
            default => $status ?: '-',
        };
    }

    public function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'Published', 'Passed', 'Aktif' => 'bg-green-lt text-green',
            'Finalized' => 'bg-blue-lt text-blue',
            'Incomplete' => 'bg-yellow-lt text-yellow',
            'Failed', 'Cancelled', 'Withdrawn', 'Drop Out', 'Keluar' => 'bg-red-lt text-red',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Transkrip Akademik',
        ]);
    }
};
?>

@push('styles')
    <style>
        .modern-card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            background: white;
        }

        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }

        .hero-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 15s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .stat-card {
            padding: 1.5rem;
            border-radius: 16px;
            background: white;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            line-height: 1;
        }

        .profile-panel {
            border-radius: 16px;
            padding: 1.25rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px solid #eef2ff;
        }

        .result-card {
            border-radius: 16px;
            padding: 1.25rem;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            height: 100%;
        }

        .result-card:hover {
            transform: translateY(-4px);
            border-color: #f59e0b;
            box-shadow: 0 8px 24px rgba(245, 158, 11, 0.18);
        }

        .gpa-display {
            font-size: 2.2rem;
            font-weight: 700;
            color: #78350f;
            line-height: 1;
        }

        .transcript-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .transcript-table th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: #475569;
            padding: 0.9rem 1rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            border-bottom: 2px solid #e2e8f0;
        }

        .transcript-table td {
            padding: 1rem;
            background: white;
            border-bottom: 1px solid #e5e7eb;
        }

        .transcript-table tr:hover td {
            background: #f8fafc;
        }

        .action-btn {
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-warning">Profil mahasiswa belum tersedia.</div>
    @else
        <div class="card modern-card hero-gradient mb-4" style="color: white;">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-start gap-3">
                            <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; backdrop-filter: blur(10px);">
                                {{ strtoupper(substr($studentName ?? 'M', 0, 1)) }}
                            </div>
                            <div>
                                <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Riwayat Akademik Mahasiswa</div>
                                <h1 class="h2 mb-2" style="font-weight: 700;">Transkrip Akademik</h1>
                                <div style="opacity: 0.9; margin-bottom: 1rem;">
                                    <i class="fas fa-graduation-cap me-2"></i>{{ $studyProgramName }} &bull; NIM {{ $studentNim }}
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="info-badge"><i class="fas fa-user-check"></i>{{ $academicStatus ?? '-' }}</span>
                                    <span class="info-badge"><i class="fas fa-layer-group"></i>Semester {{ $currentSemester ?? '-' }}</span>
                                    <span class="info-badge"><i class="fas fa-star"></i>IPK {{ $summary['cumulative_gpa'] !== null ? number_format((float) $summary['cumulative_gpa'], 2) : '-' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="d-flex flex-column gap-2">
                            <a href="{{ route('student.grades.index') }}" class="action-btn justify-content-center" style="background: rgba(255,255,255,0.94); color: #4f46e5;">
                                <i class="fas fa-chart-simple"></i>Nilai Saya
                            </a>
                            <a href="{{ route('student.grade-appeals.index') }}" class="action-btn justify-content-center" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                                <i class="fas fa-scale-balanced"></i>Keberatan Nilai
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #2563eb;"><i class="fas fa-book"></i></div>
                    <div class="stat-label">Mata Kuliah</div>
                    <div class="stat-value">{{ $summary['total_courses'] }}</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%); color: #7c3aed;"><i class="fas fa-layer-group"></i></div>
                    <div class="stat-label">Total SKS</div>
                    <div class="stat-value">{{ $summary['total_credits'] }}</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #059669;"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-label">SKS Lulus</div>
                    <div class="stat-value">{{ $summary['passed_credits'] }}</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #d97706;"><i class="fas fa-star"></i></div>
                    <div class="stat-label">IPK</div>
                    <div class="stat-value">{{ $summary['cumulative_gpa'] !== null ? number_format((float) $summary['cumulative_gpa'], 2) : '-' }}</div>
                </div>
            </div>
        </div>

        <div class="profile-panel mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-lg-5">
                    <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Mahasiswa</div>
                    <div style="font-weight: 700; font-size: 1.2rem; color: #1f2937;">{{ $studentName }}</div>
                    <div style="font-size: 0.9rem; color: #64748b;">{{ $studyProgramName }}</div>
                </div>
                <div class="col-sm-4 col-lg-2">
                    <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">NIM</div>
                    <div style="font-weight: 700; color: #1f2937;">{{ $studentNim }}</div>
                </div>
                <div class="col-sm-4 col-lg-2">
                    <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Semester</div>
                    <div style="font-weight: 700; color: #1f2937;">{{ $currentSemester ?? '-' }}</div>
                </div>
                <div class="col-sm-4 col-lg-3">
                    <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Status Akademik</div>
                    <span class="badge {{ $this->statusBadgeClass($academicStatus) }}" style="padding: 0.5rem 0.75rem; border-radius: 8px;">{{ $academicStatus ?? '-' }}</span>
                </div>
            </div>
        </div>

        @if (! $hasTranscriptEntries)
            <div class="card modern-card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-file-circle-info" style="font-size: 4rem; color: #cbd5e1;"></i>
                    <div class="mt-3" style="font-size: 1.1rem; color: #64748b; font-weight: 500;">Belum ada data transkrip.</div>
                    <a href="{{ route('student.grades.index') }}" class="action-btn mt-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <i class="fas fa-chart-simple"></i>Cek Nilai Saya
                    </a>
                </div>
            </div>
        @else
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="mb-0" style="font-weight: 700; color: #1f2937;"><i class="fas fa-chart-line me-2" style="color: #f59e0b;"></i>Hasil Studi per Semester</h3>
                    <span class="badge bg-indigo-lt text-indigo" style="padding: 0.5rem 0.75rem; border-radius: 8px;">{{ count($studyResults) }} semester</span>
                </div>
                <div class="row g-3">
                    @forelse ($studyResults as $result)
                        <div class="col-md-6 col-xl-4">
                            <div class="result-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <div style="font-size: 0.8rem; color: #92400e;">{{ $result['academic_year'] }}</div>
                                        <div style="font-weight: 700; color: #78350f; font-size: 1.1rem;">Semester {{ $result['semester_no'] ?? '-' }}</div>
                                    </div>
                                    <span class="badge {{ $this->statusBadgeClass($result['status']) }}" style="padding: 0.45rem 0.75rem; border-radius: 8px;">{{ $this->statusLabel($result['status']) }}</span>
                                </div>
                                <div class="gpa-display mb-1">{{ $result['semester_gpa'] !== null ? number_format((float) $result['semester_gpa'], 2) : '-' }}</div>
                                <div style="font-size: 0.8rem; color: #92400e; margin-bottom: 1rem;">IPS Semester</div>
                                <div class="row text-center">
                                    <div class="col-4"><div style="font-size: 0.75rem; color: #92400e;">Matkul</div><div style="font-weight: 700; color: #78350f;">{{ $result['total_courses'] }}</div></div>
                                    <div class="col-4"><div style="font-size: 0.75rem; color: #92400e;">SKS</div><div style="font-weight: 700; color: #78350f;">{{ $result['credits_taken'] }}</div></div>
                                    <div class="col-4"><div style="font-size: 0.75rem; color: #92400e;">Lulus</div><div style="font-weight: 700; color: #78350f;">{{ $result['credits_passed'] }}</div></div>
                                </div>
                                <div class="d-flex justify-content-between mt-3 pt-3" style="border-top: 2px solid rgba(251, 191, 36, 0.35);">
                                    <span style="font-size: 0.8rem; color: #92400e;">Snapshot IPK</span>
                                    <span style="font-weight: 700; color: #78350f;">{{ $result['cumulative_gpa'] !== null ? number_format((float) $result['cumulative_gpa'], 2) : '-' }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12"><div class="alert alert-info mb-0">Belum ada hasil studi per semester.</div></div>
                    @endforelse
                </div>
            </div>

            @php
                $groupedTranscript = collect($transcriptEntries)
                    ->groupBy(fn ($entry) => $entry['academic_year'].' - Semester '.($entry['semester_no'] ?? '-'));
            @endphp

            <div class="card modern-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                    <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;"><i class="fas fa-list-alt me-2" style="color: #667eea;"></i>Entri Transkrip</h3>
                    <span class="badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 16px; border-radius: 8px;">{{ count($transcriptEntries) }} mata kuliah</span>
                </div>
                <div class="card-body p-0">
                    @foreach ($groupedTranscript as $semester => $entries)
                        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e5e7eb;">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <div style="font-weight: 700; color: #1f2937; font-size: 1.05rem;">{{ $semester }}</div>
                                    <div style="font-size: 0.85rem; color: #64748b;">{{ count($entries) }} mata kuliah &bull; {{ collect($entries)->sum('credits') }} SKS</div>
                                </div>
                                <span class="badge bg-blue-lt text-blue" style="padding: 0.45rem 0.75rem; border-radius: 8px;">Rerata GP {{ collect($entries)->whereNotNull('grade_point')->avg('grade_point') !== null ? number_format((float) collect($entries)->whereNotNull('grade_point')->avg('grade_point'), 2) : '-' }}</span>
                            </div>
                            <div class="table-responsive" style="border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden;">
                                <table class="transcript-table">
                                    <thead>
                                        <tr>
                                            <th>Mata Kuliah</th>
                                            <th>SKS</th>
                                            <th>Nilai</th>
                                            <th>GP</th>
                                            <th>Skor</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($entries as $entry)
                                            <tr>
                                                <td>
                                                    <div style="font-weight: 700; color: #1f2937;">{{ $entry['course_name'] }}</div>
                                                    <div style="font-size: 0.8rem; color: #667eea; font-weight: 700;">{{ $entry['course_code'] }}</div>
                                                </td>
                                                <td style="font-weight: 700;">{{ $entry['credits'] }}</td>
                                                <td><span class="badge bg-blue-lt text-blue" style="padding: 0.5rem 0.75rem; border-radius: 8px;">{{ $entry['letter_grade'] ?? '-' }}</span></td>
                                                <td style="font-weight: 700; color: #4f46e5;">{{ $entry['grade_point'] !== null ? number_format((float) $entry['grade_point'], 2) : '-' }}</td>
                                                <td>{{ $entry['final_score'] !== null ? number_format((float) $entry['final_score'], 2) : '-' }}</td>
                                                <td><span class="badge {{ $this->statusBadgeClass($entry['result_status']) }}" style="padding: 0.5rem 0.75rem; border-radius: 8px;">{{ $this->statusLabel($entry['result_status']) }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
