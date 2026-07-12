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

<div>
    <x-alert />

    <x-admin.academic.header
        title="Detail Transkrip Nilai Mahasiswa"
        description="Rincian rekam jejak akademik, ringkasan IPS tiap semester (Study Results), dan daftar mata kuliah dengan nilai terbaik (Transcript Entries)."
        icon="certificate"
    >
        <div class="d-flex align-items-center gap-2">
            @activecan('transcript.update')
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-4 py-2 shadow-sm d-inline-flex align-items-center gap-2 fw-semibold" wire:click="syncNow">
                    <i class="fa fa-rotate"></i> <span>Sinkronisasi Transkrip</span>
                </button>
            @endactivecan
            <button type="button" class="btn btn-sm btn-light text-dark fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2 border" wire:click="backToIndex">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </button>
        </div>
    </x-admin.academic.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-id-card fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Profil Akademik Mahasiswa</h4>
                            <div class="text-muted small">Informasi identitas mahasiswa, nomor induk, dan program studi.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Nama Lengkap</label>
                            <div class="fw-bold text-dark fs-6">{{ $studentProfile->user?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small d-block mb-1">Nomor Induk (NIM)</label>
                            <div class="fw-bold text-primary fs-6">{{ $studentProfile->nim ?? '-' }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small d-block mb-1">Program Studi</label>
                            <div class="fw-semibold text-dark">{{ $studentProfile->studyProgram?->name ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-list-check fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Transcript Entries (Best Grade per Mata Kuliah)</h4>
                            <div class="text-muted small">Daftar pencapaian nilai tertinggi yang masuk ke dalam transkrip resmi mahasiswa.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @if($studentProfile->transcriptEntries->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-3 px-3">Kode & Mata Kuliah</th>
                                        <th class="py-3 px-3 text-center">Tahun / Sem</th>
                                        <th class="py-3 px-3 text-center">SKS</th>
                                        <th class="py-3 px-3 text-center">Skor</th>
                                        <th class="py-3 px-3 text-center">Grade</th>
                                        <th class="py-3 px-3 text-center">Bobot</th>
                                        <th class="py-3 px-3 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($studentProfile->transcriptEntries->sortBy('course.code') as $entry)
                                        <tr>
                                            <td class="px-3">
                                                <div class="fw-bold text-dark">{{ $entry->course?->code ?? '-' }} - {{ $entry->course?->name ?? '-' }}</div>
                                            </td>
                                            <td class="px-3 text-center small text-muted">{{ $entry->academicYear?->name ?? '-' }} <br> <span class="badge bg-light text-dark border">Sem {{ $entry->semester_no ?? '-' }}</span></td>
                                            <td class="px-3 text-center fw-bold text-primary">{{ $entry->credits ?? '-' }}</td>
                                            <td class="px-3 text-center">{{ $entry->final_score ?? '-' }}</td>
                                            <td class="px-3 text-center"><span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-bold fs-6">{{ $entry->letter_grade ?? '-' }}</span></td>
                                            <td class="px-3 text-center fw-semibold">{{ $entry->grade_point ?? '-' }}</td>
                                            <td class="px-3 text-center"><span class="badge bg-secondary rounded-pill px-2 py-1">{{ $entry->result_status ?? '-' }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-0 d-flex align-items-center gap-3">
                            <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                                <i class="fa fa-exclamation-triangle fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Belum Ada Transcript Entries</h6>
                                <div class="small">Klik tombol <strong>"Sinkronisasi Transkrip"</strong> di atas untuk membuat entri nilai terbaik per mata kuliah dari KHS yang sudah difinalisasi.</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-chart-line fs-5"></i>
                        </div>
                        <div>
                            <h5 class="card-title fw-bold mb-1 text-dark">Study Results (Semester Snapshot)</h5>
                            <div class="text-muted small">Rekapitulasi IPS dan IPK.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @if($studentProfile->studyResults->count() > 0)
                        <div class="d-flex flex-column gap-3">
                            @foreach($studentProfile->studyResults->sortByDesc('academicYear.start_date') as $result)
                                <div class="p-3 border rounded-3 bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-bold text-dark">{{ $result->academicYear?->name ?? '-' }}</span>
                                        <span class="badge rounded-pill bg-primary px-3">Semester {{ $result->semester_no ?? '-' }}</span>
                                    </div>
                                    <div class="row g-2 small text-muted mb-2">
                                        <div class="col-6">Matkul: <strong class="text-dark">{{ $result->total_courses }}</strong></div>
                                        <div class="col-6">Diambil: <strong class="text-dark">{{ $result->total_credits_taken }} SKS</strong></div>
                                        <div class="col-6">Lulus: <strong class="text-success">{{ $result->total_credits_passed }} SKS</strong></div>
                                        <div class="col-6">Status: <span class="badge bg-info px-2 py-0">{{ $result->status }}</span></div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center border-top pt-2">
                                        <div>IPS: <strong class="text-primary fs-6">{{ $result->semester_gpa ?? '-' }}</strong></div>
                                        <div>IPK: <strong class="text-success fs-6">{{ $result->cumulative_gpa ?? '-' }}</strong></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="alert alert-info border-0 shadow-sm rounded-4 p-3 mb-0 small">
                            <i class="fa fa-info-circle me-1"></i> Belum ada snapshot semester (Study Results). Klik sinkronisasi untuk menghitung ulang.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
