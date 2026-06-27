<?php

use App\Enums\EmploymentStatus;
use App\Enums\JobRelevance;
use App\Models\Alumni\TracerStudyResponse;
use Livewire\Component;

new class extends Component
{
    public TracerStudyResponse $response;
    public array $employmentStatuses = [];
    public array $jobRelevances = [];

    public function mount($id): void
    {
        $this->response = TracerStudyResponse::query()
            ->with(['alumniProfile.studyProgram', 'campaign'])
            ->findOrFail($id);
        $this->employmentStatuses = EmploymentStatus::options();
        $this->jobRelevances = JobRelevance::options();
    }

    public function goBack(): void
    {
        $this->redirectRoute('admin.alumni.tracer-study.responses', ['id' => $this->response->tracer_study_campaign_id]);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni',
            'pages' => 'Detail Respon Tracer Study',
        ]);
    }
};
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Detail Respon Tracer Study</h5>
                <button class="btn btn-secondary" wire:click="goBack">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </button>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Nama Alumni</label>
                        <div class="h6 mb-0">{{ $response->alumniProfile?->full_name ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">NIM</label>
                        <div class="h6 mb-0">{{ $response->alumniProfile?->nim ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Program Studi</label>
                        <div class="h6 mb-0">{{ $response->alumniProfile?->studyProgram?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Status Kerja</label>
                        <div><span class="badge bg-primary">{{ $employmentStatuses[$response->employment_status] ?? $response->employment_status ?? '-' }}</span></div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Perusahaan</label>
                        <div class="h6 mb-0">{{ $response->employer_name ?? '-' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Jabatan</label>
                        <div class="h6 mb-0">{{ $response->job_title ?? '-' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Relevansi Pekerjaan</label>
                        <div class="h6 mb-0">{{ $jobRelevances[$response->job_relevance] ?? $response->job_relevance ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Waktu ke Kerja (bulan)</label>
                        <div class="h6 mb-0">{{ $response->time_to_employment_months ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Range Gaji</label>
                        <div class="h6 mb-0">{{ $response->salary_range ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Studi Lanjut</label>
                        <div>
                            @if ($response->further_study)
                                <span class="badge bg-info">Ya</span>
                            @else
                                <span class="badge bg-secondary">Tidak</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Submitted</label>
                        <div class="h6 mb-0">{{ $response->submitted_at?->format('d M Y H:i') ?? '-' }}</div>
                    </div>
                </div>

                @if (!empty($response->answers))
                    <hr>
                    <h5 class="mt-4 mb-3">Jawaban Survey</h5>
                    @php
                        $questions = $response->campaign?->questions ?? [];
                        $questionMap = collect($questions)->keyBy('id')->toArray();
                    @endphp
                    @foreach ($response->answers as $questionId => $answer)
                        <div class="mb-3 p-3 border rounded">
                            <label class="form-label text-muted mb-1">
                                {{ $questionMap[$questionId]['text'] ?? 'Pertanyaan: ' . $questionId }}
                            </label>
                            <div class="h6 mb-0">{{ is_array($answer) ? implode(', ', $answer) : ($answer ?: '-') }}</div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
