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
        .student-transcript-card {
            border-radius: 18px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
        }

        .student-transcript-summary {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
        }

        .student-transcript-value {
            font-size: 30px;
            font-weight: 700;
            line-height: 1.1;
        }

        .student-transcript-avatar {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: #206bc4;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
        }

        .student-transcript-meta-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .student-transcript-meta-value {
            font-weight: 600;
        }

        .student-semester-card {
            border-radius: 16px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
            padding: 1rem;
            height: 100%;
        }

        .student-semester-gpa {
            font-size: 24px;
            font-weight: 700;
        }

        .student-transcript-title {
            font-size: 18px;
            font-weight: 700;
        }

        .student-transcript-course-name {
            font-weight: 600;
        }

        .student-transcript-course-code {
            font-size: 12px;
            color: #6b7280;
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
        <div class="row mb-4 row-cards">
            <div class="col-md-6 col-lg-3">
                <div class="card student-transcript-card">
                    <div class="card-body">
                        <div class="student-transcript-summary">Total Mata Kuliah</div>
                        <div class="student-transcript-value">{{ $summary['total_courses'] }}</div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card student-transcript-card">
                    <div class="card-body">
                        <div class="student-transcript-summary">Total SKS</div>
                        <div class="student-transcript-value">{{ $summary['total_credits'] }}</div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card student-transcript-card">
                    <div class="card-body">
                        <div class="student-transcript-summary">SKS Lulus</div>
                        <div class="student-transcript-value">{{ $summary['passed_credits'] }}</div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card student-transcript-card">
                    <div class="card-body">
                        <div class="student-transcript-summary">IPK</div>
                        <div class="student-transcript-value">
                            {{ $summary['cumulative_gpa'] !== null ? number_format((float) $summary['cumulative_gpa'], 2) : '-' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card student-transcript-card mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-4">
                    <div class="student-transcript-avatar">
                        {{ strtoupper(substr($studentName ?? 'M', 0, 1)) }}
                    </div>

                    <div class="flex-fill">
                        <div class="h2 mb-1">{{ $studentName }}</div>
                        <div class="text-secondary mb-3">{{ $studyProgramName }}</div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="student-transcript-meta-label">NIM</div>
                                <div class="student-transcript-meta-value">{{ $studentNim }}</div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <div class="student-transcript-meta-label">Semester Saat Ini</div>
                                <div class="student-transcript-meta-value">{{ $currentSemester ?? '-' }}</div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <div class="student-transcript-meta-label">Status Akademik</div>
                                <span class="badge {{ $this->statusBadgeClass($academicStatus) }}">{{ $academicStatus ?? '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <div class="student-transcript-title mb-3">Hasil Studi per Semester</div>

            <div class="row row-cards">
                @forelse ($studyResults as $result)
                    <div class="col-md-6 col-xl-4">
                        <div class="student-semester-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div class="student-transcript-meta-label">{{ $result['academic_year'] }}</div>
                                    <div class="fw-bold">Semester {{ $result['semester_no'] ?? '-' }}</div>
                                </div>

                                <span class="badge {{ $this->statusBadgeClass($result['status']) }}">
                                    {{ $result['status'] }}
                                </span>
                            </div>

                            <div class="student-semester-gpa mb-1">
                                {{ $result['semester_gpa'] !== null ? number_format((float) $result['semester_gpa'], 2) : '-' }}
                            </div>
                            <div class="student-transcript-meta-label mb-3">IPS Semester</div>

                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="student-transcript-meta-label">Matkul</div>
                                    <div class="fw-bold">{{ $result['total_courses'] }}</div>
                                </div>
                                <div class="col-4">
                                    <div class="student-transcript-meta-label">SKS</div>
                                    <div class="fw-bold">{{ $result['credits_taken'] }}</div>
                                </div>
                                <div class="col-4">
                                    <div class="student-transcript-meta-label">Lulus</div>
                                    <div class="fw-bold">{{ $result['credits_passed'] }}</div>
                                </div>
                            </div>

                            <div class="mt-3 pt-3 border-top d-flex justify-content-between">
                                <span class="student-transcript-meta-label mb-0">Snapshot IPK</span>
                                <span class="fw-semibold">
                                    {{ $result['cumulative_gpa'] !== null ? number_format((float) $result['cumulative_gpa'], 2) : '-' }}
                                </span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-info mb-0">Belum ada hasil studi.</div>
                    </div>
                @endforelse
            </div>
        </div>

        @php
            $groupedTranscript = collect($transcriptEntries)
                ->sortByDesc('semester_no')
                ->groupBy(fn ($entry) => $entry['academic_year'] . ' - Semester ' . $entry['semester_no']);
        @endphp

        <div>
            <div class="student-transcript-title mb-3">Entri Transkrip</div>

            @foreach ($groupedTranscript as $semester => $entries)
                <div class="card student-transcript-card mb-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <div>
                                <div class="student-transcript-title">{{ $semester }}</div>
                                <div class="text-secondary small">{{ count($entries) }} mata kuliah</div>
                            </div>

                            <div class="text-end">
                                <div class="text-secondary small">Total SKS</div>
                                <div class="fw-bold">{{ collect($entries)->sum('credits') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
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
                                            <div class="student-transcript-course-name">{{ $entry['course_name'] }}</div>
                                            <div class="student-transcript-course-code">{{ $entry['course_code'] }}</div>
                                        </td>
                                        <td>{{ $entry['credits'] }}</td>
                                        <td><span class="badge bg-blue-lt">{{ $entry['letter_grade'] ?? '-' }}</span></td>
                                        <td>{{ $entry['grade_point'] !== null ? number_format((float) $entry['grade_point'], 2) : '-' }}</td>
                                        <td>{{ $entry['final_score'] !== null ? number_format((float) $entry['final_score'], 2) : '-' }}</td>
                                        <td>
                                            <span class="badge {{ $this->statusBadgeClass($entry['result_status']) }}">
                                                {{ $entry['result_status'] ?? '-' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
