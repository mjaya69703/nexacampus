<?php

use App\Models\Academic\StudentProfile;
use App\Support\TranscriptSyncService;
use Livewire\Component;

new class extends Component {
    public StudentProfile $studentProfile;

    public function mount($id): void
    {
        $this->studentProfile = StudentProfile::with([
            'user',
            'studyProgram',
            'studyResults.academicYear',
            'studyResults.studyPlan',
            'transcriptEntries.course',
            'transcriptEntries.academicYear',
        ])->findOrFail($id);
    }

    public function syncNow(): void
    {
        if (! auth()->user()?->hasActivePermission('transcript.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk sinkronisasi transcript.');

            return;
        }

        $service = new TranscriptSyncService();
        $result = $service->syncStudent((int) $this->studentProfile->id);
        $this->studentProfile->refresh();
        $this->studentProfile->load([
            'user',
            'studyProgram',
            'studyResults.academicYear',
            'studyResults.studyPlan',
            'transcriptEntries.course',
            'transcriptEntries.academicYear',
        ]);

        session()->flash('success', 'Sinkronisasi transcript berhasil. Study results: ' . $result['study_results'] . ', transcript entries: ' . $result['transcript_entries'] . '.');
    }

    public function backToIndex(): void
    {
        $this->redirectRoute('admin.academic.transcripts.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Transcript Mahasiswa',
        ]);
    }
};
?>

<div class="row">
    <div class="col-12">
        <x-alert />

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Header Transcript</h5>
                <div>
                    @activecan('transcript.update')
                    <button type="button" class="btn btn-primary" wire:click="syncNow">
                        <i class="fas fa-rotate me-1"></i> Sinkronisasi
                    </button>
                    @endactivecan
                    <button type="button" class="btn btn-secondary" wire:click="backToIndex">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label text-muted">Mahasiswa</label>
                        <div class="h6 mb-0">{{ $studentProfile->user?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label text-muted">NIM</label>
                        <div class="h6 mb-0">{{ $studentProfile->nim ?? '-' }}</div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label text-muted">Prodi</label>
                        <div class="h6 mb-0">{{ $studentProfile->studyProgram?->name ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Study Results (Semester Snapshot)</h5>
            </div>
            <div class="card-body">
                @if($studentProfile->studyResults->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Tahun Akademik</th>
                                    <th>Semester</th>
                                    <th>Total Matkul</th>
                                    <th>SKS Diambil</th>
                                    <th>SKS Lulus</th>
                                    <th>IPS</th>
                                    <th>IPK Snapshot</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($studentProfile->studyResults->sortByDesc('academicYear.start_date') as $result)
                                    <tr>
                                        <td>{{ $result->academicYear?->name ?? '-' }}</td>
                                        <td>{{ $result->semester_no ?? '-' }}</td>
                                        <td>{{ $result->total_courses }}</td>
                                        <td>{{ $result->total_credits_taken }}</td>
                                        <td>{{ $result->total_credits_passed }}</td>
                                        <td>{{ $result->semester_gpa ?? '-' }}</td>
                                        <td>{{ $result->cumulative_gpa ?? '-' }}</td>
                                        <td><span class="badge bg-info">{{ $result->status }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-warning mb-0">
                        Belum ada study results. Klik sinkronisasi untuk generate snapshot.
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Transcript Entries (Best Grade per Mata Kuliah)</h5>
            </div>
            <div class="card-body">
                @if($studentProfile->transcriptEntries->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Mata Kuliah</th>
                                    <th>Tahun Akademik</th>
                                    <th>Semester</th>
                                    <th>SKS</th>
                                    <th>Final Score</th>
                                    <th>Grade</th>
                                    <th>Point</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($studentProfile->transcriptEntries->sortBy('course.code') as $entry)
                                    <tr>
                                        <td>{{ $entry->course?->code ?? '-' }}</td>
                                        <td>{{ $entry->course?->name ?? '-' }}</td>
                                        <td>{{ $entry->academicYear?->name ?? '-' }}</td>
                                        <td>{{ $entry->semester_no ?? '-' }}</td>
                                        <td>{{ $entry->credits ?? '-' }}</td>
                                        <td>{{ $entry->final_score ?? '-' }}</td>
                                        <td>{{ $entry->letter_grade ?? '-' }}</td>
                                        <td>{{ $entry->grade_point ?? '-' }}</td>
                                        <td><span class="badge bg-secondary">{{ $entry->result_status ?? '-' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-warning mb-0">
                        Belum ada transcript entries. Klik sinkronisasi untuk generate best grade per course.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
