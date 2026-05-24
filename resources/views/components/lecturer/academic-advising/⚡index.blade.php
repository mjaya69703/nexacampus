<?php

use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentAdvisorNote;
use App\Support\AcademicAdvisorService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public array $students = [];
    public array $stats = [];
    public string $search = '';

    public function mount(AcademicAdvisorService $advisorService): void
    {
        $this->loadStudents($advisorService);
    }

    public function updatedSearch(): void
    {
        $this->loadStudents(app(AcademicAdvisorService::class));
    }

    public function loadStudents(AcademicAdvisorService $advisorService): void
    {
        $lecturerProfileId = auth()->user()?->lecturerProfile?->id;

        abort_unless($lecturerProfileId && $advisorService->hasActiveAssignmentsForLecturer($lecturerProfileId), 403);

        $search = str($this->search)->lower()->trim()->toString();

        $assignments = $advisorService->assignedStudentsForLecturer($lecturerProfileId);

        $this->stats = [
            'total_students' => $assignments->count(),
            'general_assignments' => $assignments->whereNull('academic_year_id')->count(),
            'year_assignments' => $assignments->whereNotNull('academic_year_id')->count(),
            'study_programs' => $assignments->pluck('studentProfile.study_program_id')->filter()->unique()->count(),
            'open_notes' => StudentAdvisorNote::query()
                ->where('lecturer_profile_id', $lecturerProfileId)
                ->where('status', 'Open')
                ->count(),
        ];

        $this->students = $assignments
            ->filter(function ($assignment) use ($search) {
                if ($search === '') {
                    return true;
                }

                $student = $assignment->studentProfile;
                $haystack = str(($student?->nim ?? '').' '.($student?->user?->name ?? '').' '.($student?->studyProgram?->name ?? ''))->lower();

                return $haystack->contains($search);
            })
            ->map(fn ($assignment) => [
                'id' => $assignment->id,
                'student_name' => $assignment->studentProfile?->user?->name ?? '-',
                'nim' => $assignment->studentProfile?->nim ?? '-',
                'study_program' => $assignment->studentProfile?->studyProgram?->name ?? '-',
                'semester' => $assignment->studentProfile?->current_semester ?? '-',
                'academic_status' => $assignment->studentProfile?->academic_status ?? '-',
                'academic_year' => $assignment->academicYear?->name ?? 'Umum',
                'start_date' => $assignment->start_date?->format('d M Y') ?? '-',
                'end_date' => $assignment->end_date?->format('d M Y') ?? 'Aktif',
                'snapshot' => $this->studentSnapshot($assignment->studentProfile),
                'notes' => $this->studentNotes($assignment->studentProfile?->id),
            ])
            ->values()
            ->all();
    }

    public function markNoteDone(int $noteId): void
    {
        $lecturerProfileId = auth()->user()?->lecturerProfile?->id;

        StudentAdvisorNote::query()
            ->whereKey($noteId)
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->update([
                'status' => 'Done',
                'updated_by' => auth()->id(),
            ]);

        $this->dispatch('success', message: 'Follow-up ditandai selesai.');
        $this->loadStudents(app(AcademicAdvisorService::class));
    }

    private function studentSnapshot(?StudentProfile $student): array
    {
        if (! $student) {
            return [
                'gpa' => '-',
                'passed_credits' => 0,
                'latest_study_plan' => '-',
                'failed_courses' => 0,
                'financial_holds' => 0,
                'overdue_invoices' => 0,
                'risk_level' => 'low',
                'risk_label' => 'Stabil',
            ];
        }

        $transcript = $student->transcriptEntries()
            ->where('is_counted_in_gpa', true)
            ->where('is_best_grade', true)
            ->get(['credits', 'grade_point', 'result_status']);

        $passedCredits = (int) $transcript
            ->where('result_status', 'Passed')
            ->sum('credits');

        $gpaCredits = (float) $transcript
            ->filter(fn ($entry) => $entry->grade_point !== null)
            ->sum('credits');

        $weightedPoints = (float) $transcript
            ->filter(fn ($entry) => $entry->grade_point !== null)
            ->sum(fn ($entry) => ((float) $entry->grade_point) * ((float) $entry->credits));

        $failedCourses = $student->transcriptEntries()
            ->where('result_status', 'Failed')
            ->count();

        $latestStudyPlan = $student->studyPlans()
            ->with('academicYear')
            ->latest('semester_no')
            ->first();

        $financialHolds = DB::table('financial_holds')
            ->where('student_profile_id', $student->id)
            ->where('status', 'active')
            ->count();

        $overdueInvoices = DB::table('student_invoices')
            ->where('student_profile_id', $student->id)
            ->where('status', 'overdue')
            ->count();

        $riskScore = 0;
        $riskScore += $failedCourses > 0 ? 1 : 0;
        $riskScore += $financialHolds > 0 || $overdueInvoices > 0 ? 1 : 0;
        $riskScore += in_array($student->academic_status, ['inactive', 'leave', 'suspended'], true) ? 1 : 0;

        return [
            'gpa' => $gpaCredits > 0 ? number_format($weightedPoints / $gpaCredits, 2) : '-',
            'passed_credits' => $passedCredits,
            'latest_study_plan' => $latestStudyPlan
                ? trim('Smt '.$latestStudyPlan->semester_no.' / '.($latestStudyPlan->status ?? '-'))
                : '-',
            'failed_courses' => $failedCourses,
            'financial_holds' => $financialHolds,
            'overdue_invoices' => $overdueInvoices,
            'risk_level' => $riskScore >= 2 ? 'high' : ($riskScore === 1 ? 'medium' : 'low'),
            'risk_label' => $riskScore >= 2 ? 'Perlu Atensi' : ($riskScore === 1 ? 'Pantau' : 'Stabil'),
        ];
    }

    private function studentNotes(?int $studentProfileId, int $limit = 2): array
    {
        if (! $studentProfileId) {
            return [];
        }

        return StudentAdvisorNote::query()
            ->where('student_profile_id', $studentProfileId)
            ->where('lecturer_profile_id', auth()->user()?->lecturerProfile?->id)
            ->latest('created_at')
            ->take($limit)
            ->get()
            ->map(fn (StudentAdvisorNote $note) => [
                'id' => $note->id,
                'topic' => $note->topic,
                'notes' => str($note->notes)->limit(100)->toString(),
                'recommendation' => $note->recommendation ? str($note->recommendation)->limit(100)->toString() : null,
                'follow_up_at' => $note->follow_up_at?->format('d M Y'),
                'status' => $note->status,
                'visible_to_student' => $note->visible_to_student,
            ])
            ->values()
            ->all();
    }

    public function riskBadgeClass(string $riskLevel): string
    {
        return match ($riskLevel) {
            'high' => 'bg-red-lt text-red',
            'medium' => 'bg-yellow-lt text-yellow',
            default => 'bg-green-lt text-green',
        };
    }

    public function render(): View
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Bimbingan Akademik',
        ]);
    }
};
?>

@push('styles')
    <style>
        .modern-card {
            background: white;
            border: none;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all .3s ease;
        }

        .modern-card:hover {
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
            transform: translateY(-3px);
        }

        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            overflow: hidden;
            position: relative;
        }

        .hero-gradient::before {
            animation: pulse 15s ease-in-out infinite;
            background: radial-gradient(circle, rgba(255,255,255,.1) 0%, transparent 70%);
            content: '';
            height: 200%;
            position: absolute;
            right: -50%;
            top: -50%;
            width: 200%;
        }

        @keyframes pulse {
            0%, 100% { opacity: .5; transform: scale(1); }
            50% { opacity: .8; transform: scale(1.08); }
        }

        .advisor-stat {
            background: rgba(255,255,255,.2);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }

        .advisor-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            height: 100%;
            overflow: hidden;
            transition: all .3s ease;
        }

        .advisor-card:hover {
            border-color: #667eea;
            box-shadow: 0 12px 40px rgba(102, 126, 234, .18);
            transform: translateY(-4px);
        }

        .advisor-card-header {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 1.25rem;
        }

        .advisor-avatar {
            align-items: center;
            background: white;
            border-radius: 16px;
            color: #667eea;
            display: inline-flex;
            font-size: 1.35rem;
            height: 3.25rem;
            justify-content: center;
            width: 3.25rem;
        }

        .filter-input {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: .75rem 1rem;
            transition: all .3s ease;
        }

        .filter-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, .1);
        }

        .badge-modern {
            border-radius: 999px;
            display: inline-flex;
            font-size: .78rem;
            font-weight: 700;
            gap: .35rem;
            padding: .45rem .75rem;
        }

        .advisor-metric {
            background: #f9fafb;
            border-radius: 12px;
            padding: .75rem;
        }

        .advisor-metric-value {
            color: #1f2937;
            font-size: 1.2rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .advisor-metric-label {
            color: #6b7280;
            font-size: .72rem;
            margin-top: .25rem;
            text-transform: uppercase;
        }

        .advisor-action {
            border-radius: 12px;
            font-weight: 700;
            padding: .75rem 1rem;
        }

        .note-preview {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: .85rem;
        }

    </style>
@endpush

<div>
    <x-alert />

    <div class="card modern-card hero-gradient mb-4">
        <div class="card-body p-4 p-lg-5" style="position:relative;">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div style="width:64px;height:64px;background:rgba(255,255,255,.2);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:2rem;">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div>
                            <div style="font-size:.9rem;opacity:.9;margin-bottom:.25rem;">Bimbingan Akademik</div>
                            <h2 class="h2 mb-0" style="font-weight:700;">Mahasiswa Bimbingan</h2>
                        </div>
                    </div>
                    <div style="opacity:.9;margin-bottom:1rem;">
                        Pantau kondisi akademik, simpan catatan bimbingan, dan susun follow-up mahasiswa PA dari satu ruang kerja dosen.
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge-modern" style="background:rgba(255,255,255,.2);backdrop-filter:blur(10px);color:white;">
                            <i class="fas fa-users"></i>{{ number_format($stats['total_students'] ?? 0) }} Mahasiswa
                        </span>
                        <span class="badge-modern" style="background:rgba(255,255,255,.2);backdrop-filter:blur(10px);color:white;">
                            <i class="fas fa-layer-group"></i>{{ number_format($stats['study_programs'] ?? 0) }} Prodi
                        </span>
                        <span class="badge-modern" style="background:rgba(255,255,255,.2);backdrop-filter:blur(10px);color:white;">
                            <i class="fas fa-clipboard-list"></i>{{ number_format($stats['open_notes'] ?? 0) }} Follow-up Terbuka
                        </span>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="row g-3">
                        <div class="col-4">
                            <div class="advisor-stat">
                                <div style="font-size:.72rem;opacity:.9;text-transform:uppercase;letter-spacing:.05em;">Aktif</div>
                                <div class="h2 mt-2 mb-0" style="font-weight:700;">{{ number_format($stats['total_students'] ?? 0) }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="advisor-stat">
                                <div style="font-size:.72rem;opacity:.9;text-transform:uppercase;letter-spacing:.05em;">Umum</div>
                                <div class="h2 mt-2 mb-0" style="font-weight:700;">{{ number_format($stats['general_assignments'] ?? 0) }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="advisor-stat">
                                <div style="font-size:.72rem;opacity:.9;text-transform:uppercase;letter-spacing:.05em;">Open</div>
                                <div class="h2 mt-2 mb-0" style="font-weight:700;">{{ number_format($stats['open_notes'] ?? 0) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modern-card mb-4">
        <div class="card-body p-4">
            <div class="row g-2 align-items-center">
                <div class="col-lg-7">
                    <div class="fw-bold mb-1">Daftar Bimbingan Aktif</div>
                    <div class="text-secondary small">{{ count($students) }} mahasiswa tampil sesuai pencarian.</div>
                </div>
                <div class="col-lg-5">
                    <input type="text" class="form-control filter-input" wire:model.live.debounce.300ms="search" placeholder="Cari nama, NIM, atau prodi...">
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        @forelse ($students as $row)
            <div class="col-xl-4 col-md-6">
                <div class="advisor-card">
                    <div class="advisor-card-header">
                        <div class="d-flex gap-3 align-items-start">
                            <span class="advisor-avatar">
                                <i class="fas fa-user-graduate"></i>
                            </span>
                            <div style="min-width:0;">
                                <div class="fw-bold text-truncate" style="font-size:1.05rem;">{{ $row['student_name'] }}</div>
                                <div class="text-secondary small">{{ $row['nim'] }}</div>
                                <div class="text-secondary small text-truncate">{{ $row['study_program'] }}</div>
                                <div class="d-flex gap-2 flex-wrap mt-2">
                                    <span class="badge-modern {{ $this->riskBadgeClass($row['snapshot']['risk_level']) }}">
                                        <i class="fas fa-signal"></i>{{ $row['snapshot']['risk_label'] }}
                                    </span>
                                    <span class="badge-modern bg-secondary-lt text-secondary">
                                        Smt {{ $row['semester'] }} / {{ $row['academic_status'] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-3">
                        <div class="row g-2">
                            <div class="col-4">
                                <div class="advisor-metric">
                                    <div class="advisor-metric-value">{{ $row['snapshot']['gpa'] }}</div>
                                    <div class="advisor-metric-label">IPK</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="advisor-metric">
                                    <div class="advisor-metric-value">{{ $row['snapshot']['passed_credits'] }}</div>
                                    <div class="advisor-metric-label">SKS Lulus</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="advisor-metric">
                                    <div class="advisor-metric-value">{{ $row['snapshot']['failed_courses'] }}</div>
                                    <div class="advisor-metric-label">Gagal</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between border-bottom py-2">
                                <span class="text-secondary">KRS Terbaru</span>
                                <strong>{{ $row['snapshot']['latest_study_plan'] }}</strong>
                            </div>
                            <div class="d-flex justify-content-between border-bottom py-2">
                                <span class="text-secondary">Financial Hold</span>
                                <strong>{{ $row['snapshot']['financial_holds'] }}</strong>
                            </div>
                            <div class="d-flex justify-content-between py-2">
                                <span class="text-secondary">Invoice Overdue</span>
                                <strong>{{ $row['snapshot']['overdue_invoices'] }}</strong>
                            </div>
                        </div>
                        <div class="mt-2 d-flex flex-wrap gap-2">
                            <span class="badge-modern bg-blue-lt text-blue"><i class="fas fa-user-check"></i>Aktif</span>
                            <span class="badge-modern bg-green-lt text-green"><i class="fas fa-calendar"></i>{{ $row['academic_year'] }}</span>
                            <span class="badge-modern bg-purple-lt text-purple"><i class="fas fa-clock"></i>{{ $row['start_date'] }} - {{ $row['end_date'] }}</span>
                        </div>
                        <div class="mt-3">
                            @forelse ($row['notes'] as $note)
                                <div class="note-preview mb-2">
                                    <div class="d-flex justify-content-between gap-2">
                                        <div class="fw-bold text-dark">{{ $note['topic'] }}</div>
                                        <span class="badge-modern {{ $note['status'] === 'Done' ? 'bg-green-lt text-green' : 'bg-yellow-lt text-yellow' }}">
                                            {{ $note['status'] }}
                                        </span>
                                    </div>
                                    <div class="text-secondary small mt-1">{{ $note['notes'] }}</div>
                                    @if ($note['follow_up_at'] && $note['status'] !== 'Done')
                                        <button type="button" class="btn btn-outline-success advisor-action mt-2" wire:click="markNoteDone({{ $note['id'] }})">
                                            <i class="fas fa-check me-2"></i>Tandai Selesai
                                        </button>
                                    @endif
                                </div>
                            @empty
                                <div class="text-secondary small mt-3">Belum ada catatan bimbingan untuk mahasiswa ini.</div>
                            @endforelse
                        </div>
                        <a href="{{ route('lecturer.academic-advising.show', $row['id']) }}" class="btn btn-primary advisor-action w-100 mt-3">
                            <i class="fas fa-folder-open me-2"></i>Buka Bimbingan
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="modern-card text-center text-secondary py-5">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <div>Tidak ada mahasiswa bimbingan sesuai pencarian.</div>
                </div>
            </div>
        @endforelse
    </div>

</div>
