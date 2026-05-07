<?php

use App\Models\Academic\StudyResult;
use App\Models\Academic\TranscriptEntry;
use Livewire\Component;

new class extends Component {
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
            ->orderByDesc('semester_no')
            ->orderByDesc('id')
            ->get();

        $entries = TranscriptEntry::query()
            ->with(['course', 'academicYear'])
            ->where('student_profile_id', $this->studentProfileId)
            ->orderBy('course_id')
            ->orderByDesc('grade_point')
            ->get();

        $this->hasTranscriptEntries = $entries->isNotEmpty();

        $this->studyResults = $results
            ->map(function (StudyResult $result) {
                return [
                    'academic_year' => $result->academicYear?->name ?? '-',
                    'semester_no' => $result->semester_no,
                    'total_courses' => (int) ($result->total_courses ?? 0),
                    'credits_taken' => (int) ($result->total_credits_taken ?? 0),
                    'credits_passed' => (int) ($result->total_credits_passed ?? 0),
                    'semester_gpa' => $result->semester_gpa,
                    'cumulative_gpa' => $result->cumulative_gpa,
                    'status' => $result->status ?? '-',
                ];
            })
            ->values()
            ->all();

        $this->transcriptEntries = $entries
            ->map(function (TranscriptEntry $entry) {
                return [
                    'course_code' => $entry->course?->code ?? '-',
                    'course_name' => $entry->course?->name ?? '-',
                    'academic_year' => $entry->academicYear?->name ?? '-',
                    'semester_no' => $entry->semester_no,
                    'credits' => (int) ($entry->credits ?? 0),
                    'final_score' => $entry->final_score,
                    'letter_grade' => $entry->letter_grade,
                    'grade_point' => $entry->grade_point,
                    'result_status' => $entry->result_status,
                ];
            })
            ->sortBy('course_code')
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

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'My Transcript',
        ]);
    }

    public function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'Published', 'Passed', 'Aktif' => 'bg-green-lt',
            'Finalized' => 'bg-blue-lt',
            'Incomplete' => 'bg-yellow-lt',
            'Failed', 'Cancelled', 'Withdrawn', 'Drop Out', 'Keluar' => 'bg-red-lt',
            default => 'bg-secondary-lt',
        };
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

        .stat-card {
            border-radius: 16px;
            padding: 1.5rem;
            background: white;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .profile-card {
            border-radius: 16px;
            padding: 2rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .semester-result-card {
            border-radius: 16px;
            padding: 1.5rem;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .semester-result-card:hover {
            background: linear-gradient(135deg, #fde68a 0%, #fcd34d 100%);
            border-color: #f59e0b;
            transform: translateY(-4px);
        }

        .gpa-display {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .transcript-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .transcript-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .transcript-table th:first-child {
            border-radius: 12px 0 0 0;
        }

        .transcript-table th:last-child {
            border-radius: 0 12px 0 0;
        }

        .transcript-table td {
            padding: 1rem;
            background: white;
            border-bottom: 1px solid #e5e7eb;
        }

        .transcript-table tr:last-child td:first-child {
            border-radius: 0 0 0 12px;
        }

        .transcript-table tr:last-child td:last-child {
            border-radius: 0 0 12px 0;
        }

        .transcript-table tr:hover td {
            background: #f8fafc;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: #667eea;
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-warning">Profil mahasiswa belum tersedia.</div>
    @elseif (! $hasTranscriptEntries)
        <div class="alert alert-info">Belum ada data transkrip.</div>
    @else
        {{-- Hero Section with Gradient --}}
        <div class="card modern-card hero-gradient mb-4" style="color: white;">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; backdrop-filter: blur(10px);">
                        <i class="fas fa-scroll"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Transkrip Nilai Akademik</div>
                        <h1 class="h2 mb-2" style="font-weight: 700;">{{ $studentName }}</h1>
                        <div style="opacity: 0.9;">
                            <i class="fas fa-university me-2"></i>{{ $studyProgramName }} • NIM {{ $studentNim }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="row row-cards mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #3b82f6;">
                            <i class="fas fa-book"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Total Mata Kuliah</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #1f2937;">{{ $summary['total_courses'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #f59e0b;">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Total SKS</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #1f2937;">{{ $summary['total_credits'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #10b981;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">SKS Lulus</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #1f2937;">{{ $summary['passed_credits'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #8b5cf6;">
                            <i class="fas fa-star"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">IPK</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #1f2937;">
                                {{ $summary['cumulative_gpa'] !== null ? number_format((float) $summary['cumulative_gpa'], 2) : '-' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Profile Card --}}
        <div class="modern-card profile-card mb-4">
            <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-4">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 700; color: white; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);">
                    {{ strtoupper(substr($studentName ?? 'M', 0, 1)) }}
                </div>

                <div class="flex-fill">
                    <div class="h2 mb-1" style="font-weight: 700;">{{ $studentName }}</div>
                    <div style="color: #6b7280; margin-bottom: 1rem;">{{ $studyProgramName }}</div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div style="font-size: 0.8rem; color: #9ca3af; margin-bottom: 0.25rem;">NIM</div>
                            <div style="font-weight: 600; color: #1f2937;">{{ $studentNim }}</div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <div style="font-size: 0.8rem; color: #9ca3af; margin-bottom: 0.25rem;">Semester Saat Ini</div>
                            <div style="font-weight: 600; color: #1f2937;">{{ $currentSemester ?? '-' }}</div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <div style="font-size: 0.8rem; color: #9ca3af; margin-bottom: 0.25rem;">Status Akademik</div>
                            <span class="badge {{ $this->statusBadgeClass($academicStatus) }}" style="padding: 0.5rem 0.75rem;">{{ $academicStatus ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Semester Results --}}
        <div class="mb-4">
            <div class="section-title">
                <i class="fas fa-chart-line"></i> Hasil Studi per Semester
            </div>

            <div class="row row-cards">
                @forelse ($studyResults as $result)
                    <div class="col-md-6 col-xl-4">
                        <div class="semester-result-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div style="font-size: 0.8rem; color: #92400e; margin-bottom: 0.25rem;">{{ $result['academic_year'] }}</div>
                                    <div style="font-weight: 700; color: #78350f; font-size: 1.1rem;">Semester {{ $result['semester_no'] ?? '-' }}</div>
                                </div>

                                <span class="badge {{ $this->statusBadgeClass($result['status']) }}" style="padding: 0.5rem 0.75rem;">
                                    {{ $result['status'] }}
                                </span>
                            </div>

                            <div class="gpa-display mb-1">
                                {{ $result['semester_gpa'] !== null ? number_format((float) $result['semester_gpa'], 2) : '-' }}
                            </div>
                            <div style="font-size: 0.8rem; color: #92400e; margin-bottom: 1rem;">IPS Semester</div>

                            <div class="row text-center" style="margin-bottom: 1rem;">
                                <div class="col-4">
                                    <div style="font-size: 0.75rem; color: #92400e; margin-bottom: 0.25rem;">Matkul</div>
                                    <div style="font-weight: 700; color: #78350f;">{{ $result['total_courses'] }}</div>
                                </div>
                                <div class="col-4">
                                    <div style="font-size: 0.75rem; color: #92400e; margin-bottom: 0.25rem;">SKS</div>
                                    <div style="font-weight: 700; color: #78350f;">{{ $result['credits_taken'] }}</div>
                                </div>
                                <div class="col-4">
                                    <div style="font-size: 0.75rem; color: #92400e; margin-bottom: 0.25rem;">Lulus</div>
                                    <div style="font-weight: 700; color: #78350f;">{{ $result['credits_passed'] }}</div>
                                </div>
                            </div>

                            <div style="padding-top: 1rem; border-top: 2px solid rgba(251, 191, 36, 0.3); display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 0.8rem; color: #92400e;">Snapshot IPK</span>
                                <span style="font-weight: 700; color: #78350f; font-size: 1.1rem;">
                                    {{ $result['cumulative_gpa'] !== null ? number_format((float) $result['cumulative_gpa'], 2) : '-' }}
                                </span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>Belum ada hasil studi.
                        </div>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Transcript Entries --}}
        @php
            $groupedTranscript = collect($transcriptEntries)
                ->sortByDesc('semester_no')
                ->groupBy(fn ($entry) => $entry['academic_year'] . ' - Semester ' . $entry['semester_no']);
        @endphp

        <div>
            <div class="section-title">
                <i class="fas fa-list-alt"></i> Entri Transkrip
            </div>

            @foreach ($groupedTranscript as $semester => $entries)
                <div class="modern-card mb-4">
                    <div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <div>
                                <div style="font-weight: 700; color: #1f2937; font-size: 1.25rem;">{{ $semester }}</div>
                                <div style="color: #6b7280; font-size: 0.85rem;">{{ count($entries) }} mata kuliah</div>
                            </div>

                            <div class="text-end">
                                <div style="color: #6b7280; font-size: 0.85rem;">Total SKS</div>
                                <div style="font-weight: 700; color: #667eea; font-size: 1.25rem;">{{ collect($entries)->sum('credits') }}</div>
                            </div>
                        </div>
                    </div>

                    <div style="padding: 0;">
                        <div class="table-responsive">
                            <table class="transcript-table">
                                <thead>
                                    <tr>
                                        <th>Mata Kuliah</th>
                                        <th>SKS</th>
                                        <th>Nilai</th>
                                        <th>Point</th>
                                        <th>Score</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($entries as $entry)
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600; color: #1f2937;">{{ $entry['course_name'] }}</div>
                                                <div style="font-size: 0.8rem; color: #9ca3af;">{{ $entry['course_code'] }}</div>
                                            </td>
                                            <td style="font-weight: 600;">{{ $entry['credits'] }}</td>
                                            <td><span class="badge bg-blue-lt text-blue" style="padding: 0.5rem 0.75rem;">{{ $entry['letter_grade'] ?? '-' }}</span></td>
                                            <td style="font-weight: 600; color: #667eea;">{{ $entry['grade_point'] !== null ? number_format((float) $entry['grade_point'], 2) : '-' }}</td>
                                            <td>{{ $entry['final_score'] !== null ? number_format((float) $entry['final_score'], 2) : '-' }}</td>
                                            <td>
                                                <span class="badge {{ $this->statusBadgeClass($entry['result_status']) }}" style="padding: 0.5rem 0.75rem;">
                                                    {{ $entry['result_status'] ?? '-' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
