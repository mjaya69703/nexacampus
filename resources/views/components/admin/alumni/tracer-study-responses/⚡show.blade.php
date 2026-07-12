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
            'menus' => 'Alumni Management',
            'pages' => 'Detail Respon Tracer Study',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.alumni.header
        title="Respon Alumni: {{ $response->alumniProfile?->full_name ?? 'Lulusan' }}"
        description="Detail jawaban kuesioner pelacakan karir untuk kampanye {{ $response->campaign?->title ?? 'Tracer Study' }}."
        icon="file-circle-check"
    >
        <button type="button" wire:click="goBack" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar Respon</span>
        </button>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-id-card fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">NIM & Prodi</div>
                        <div class="fw-bold">{{ $response->alumniProfile?->nim ?? '-' }} ({{ $response->alumniProfile?->studyProgram?->code ?? '-' }})</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-briefcase fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Kerja</div>
                        <div class="fw-bold">{{ $employmentStatuses[$response->employment_status] ?? $response->employment_status ?? '-' }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-building fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Perusahaan / Instansi</div>
                        <div class="fw-bold">{{ $response->employer_name ?: '-' }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-double fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Relevansi Bidang</div>
                        <div class="fw-bold">{{ $jobRelevances[$response->job_relevance] ?? $response->job_relevance ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.alumni.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa fa-user-graduate me-2 text-primary"></i>Data Profil & Status Pekerjaan Alumni</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Nama Lengkap Alumni</label>
                            <div class="fw-bold fs-6 text-dark">{{ $response->alumniProfile?->full_name ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Nomor Induk Mahasiswa (NIM)</label>
                            <div class="fw-bold fs-6 text-dark">{{ $response->alumniProfile?->nim ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Program Studi</label>
                            <div class="fw-bold fs-6 text-dark">{{ $response->alumniProfile?->studyProgram?->name ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Tahun Lulus</label>
                            <div class="fw-bold fs-6 text-dark">{{ $response->alumniProfile?->graduation_year ?? '-' }}</div>
                        </div>

                        <div class="col-12"><hr class="my-2 border-light"></div>

                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Status Pekerjaan Terkini</label>
                            <div>
                                <span class="badge bg-primary px-3 py-1.5 rounded-pill fs-6">{{ $employmentStatuses[$response->employment_status] ?? $response->employment_status ?? '-' }}</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Nama Perusahaan / Tempat Kerja</label>
                            <div class="fw-bold fs-6 text-dark">{{ $response->employer_name ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Jabatan / Posisi Kerja</label>
                            <div class="fw-bold fs-6 text-dark">{{ $response->job_title ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Tingkat Relevansi Pekerjaan</label>
                            <div class="fw-bold fs-6 text-dark">{{ $jobRelevances[$response->job_relevance] ?? $response->job_relevance ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Waktu Tunggu Kerja (Masa Jeda)</label>
                            <div class="fw-bold fs-6 text-dark">{{ $response->time_to_employment_months !== null ? $response->time_to_employment_months . ' Bulan' : '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Rentang Penghasilan / Gaji</label>
                            <div class="fw-bold fs-6 text-dark">{{ $response->salary_range ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Melanjutkan Studi (S2/S3/Profesi)</label>
                            <div>
                                @if ($response->further_study)
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-3 py-1 rounded-pill"><i class="fa fa-check me-1"></i> Ya, Melanjutkan Studi</span>
                                @else
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-1 rounded-pill">Tidak Melanjutkan</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Waktu Pengisian Survei</label>
                            <div class="fw-bold fs-6 text-dark">{{ $response->submitted_at?->format('d M Y, H:i WIB') ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa fa-clipboard-check me-2 text-success"></i>Jawaban Butir Kuesioner Kampanye</h5>
                </div>
                <div class="card-body p-4">
                    @if (empty($response->answers))
                        <div class="text-center py-4 text-muted">
                            <i class="fa fa-comment-slash fs-2 mb-2 opacity-50"></i>
                            <p class="mb-0 small">Alumni ini tidak mengisi jawaban pada pertanyaan kuesioner tambahan (hanya data profil kerja umum).</p>
                        </div>
                    @else
                        @php
                            $questions = $response->campaign?->questions ?? [];
                            $questionMap = collect($questions)->keyBy('id')->toArray();
                        @endphp
                        <div class="d-grid gap-3">
                            @foreach ($response->answers as $questionId => $answer)
                                <div class="border rounded-4 p-3 bg-white shadow-sm">
                                    <label class="form-label text-muted small fw-bold mb-1 d-block">
                                        {{ $questionMap[$questionId]['text'] ?? 'Pertanyaan ID #' . $questionId }}
                                    </label>
                                    <div class="fw-semibold text-dark fs-6 p-2 bg-light rounded-3 mt-1">
                                        {{ is_array($answer) ? implode(', ', $answer) : ($answer !== '' ? $answer : '-') }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden bg-white p-4 text-center">
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 shadow-sm" style="width: 80px; height: 80px;">
                    <i class="fa fa-check-to-slot fs-1"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Survei Terverifikasi</h5>
                <p class="text-muted small mb-3">Telah dikirim pada {{ $response->submitted_at?->format('d M Y') }}</p>

                <div class="border-top pt-3 text-start">
                    <div class="small text-muted mb-1">Kampanye Tracer Study:</div>
                    <div class="fw-bold text-dark mb-3">{{ $response->campaign?->title ?? '-' }}</div>

                    <button type="button" wire:click="goBack" class="btn btn-outline-primary rounded-pill w-100 py-2.5 fw-semibold shadow-sm d-flex align-items-center justify-content-center gap-2">
                        <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar Respon</span>
                    </button>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <h6 class="fw-bold mb-2 text-dark"><i class="fa fa-shield-check me-2 text-success"></i>Integritas Data Alumni</h6>
                    <p class="text-muted small mb-0">Jawaban kuesioner pelacakan dikumpulkan secara rahasia dan digunakan untuk kepentingan pemeringkatan perguruan tinggi dan akreditasi program studi.</p>
                </div>
            </div>
        </div>
    </div>
</div>
