<?php

use App\Enums\EmploymentStatus;
use App\Enums\JobRelevance;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\TracerStudyCampaign;
use App\Support\Alumni\TracerStudyService;
use Livewire\Component;

new class extends Component
{
    public TracerStudyCampaign $campaign;
    public array $answers = [];
    public array $snapshot = [
        'employment_status' => '',
        'employer_name' => '',
        'job_title' => '',
        'job_relevance' => '',
        'time_to_employment_months' => '',
        'salary_range' => '',
        'further_study' => false,
    ];

    public function mount($id): void
    {
        $this->campaign = TracerStudyCampaign::query()
            ->where('status', 'active')
            ->with('academicYear')
            ->findOrFail($id);

        $profile = AlumniProfile::query()->where('user_id', auth()->id())->first();
        abort_unless($profile, 404, 'Profil alumni belum tersedia.');

        // Check if already responded
        $alreadyResponded = $this->campaign->responses()
            ->where('alumni_profile_id', $profile->id)
            ->exists();

        if ($alreadyResponded) {
            session()->flash('info', 'Kamu sudah mengisi survei ini.');
            $this->redirectRoute('alumni.tracer-study.show', ['id' => $this->campaign->id]);
        }

        // Pre-fill snapshot from profile
        $this->snapshot['employment_status'] = $profile->employment_status ?? 'unemployed';
        $this->snapshot['employer_name'] = $profile->employer_name ?? '';
        $this->snapshot['job_title'] = $profile->job_title ?? '';

        // Initialize answers
        foreach ($this->campaign->questions ?? [] as $question) {
            $qid = $question['id'] ?? $question['text'];
            $this->answers[$qid] = '';
        }
    }

    public function submit(TracerStudyService $service): void
    {
        $profile = AlumniProfile::query()->where('user_id', auth()->id())->first();
        abort_unless($profile, 422, 'Profil alumni belum tersedia.');

        $this->validate([
            'snapshot.employment_status' => ['required', 'string'],
            'snapshot.employer_name' => ['nullable', 'string', 'max:255'],
            'snapshot.job_title' => ['nullable', 'string', 'max:255'],
            'snapshot.job_relevance' => ['nullable', 'string'],
            'snapshot.time_to_employment_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'snapshot.salary_range' => ['nullable', 'string', 'max:100'],
            'snapshot.further_study' => ['boolean'],
        ]);

        $employmentSnapshot = [
            'employment_status' => $this->snapshot['employment_status'],
            'employer_name' => $this->snapshot['employer_name'] ?: null,
            'job_title' => $this->snapshot['job_title'] ?: null,
            'job_relevance' => $this->snapshot['job_relevance'] ?: null,
            'time_to_employment_months' => $this->snapshot['time_to_employment_months'] ? (int) $this->snapshot['time_to_employment_months'] : null,
            'salary_range' => $this->snapshot['salary_range'] ?: null,
            'further_study' => (bool) $this->snapshot['further_study'],
        ];

        try {
            $service->submitResponse($this->campaign, $profile, $this->answers, $employmentSnapshot);
            session()->flash('success', 'Terima kasih! Jawaban tracer study kamu berhasil disimpan.');
            $this->redirectRoute('alumni.tracer-study.show', ['id' => $this->campaign->id]);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Tracer Study',
            'pages' => 'Isi Survei: ' . $this->campaign->title,
        ]);
    }
};
?>

<div>
    <x-alert />

    <a href="{{ route('alumni.tracer-study.show', ['id' => $campaign->id]) }}" class="btn btn-outline-secondary mb-4">
        <i class="fas fa-arrow-left me-1"></i> Kembali
    </a>

    <div class="row justify-content-center">
        <div class="col-lg-9">
            {{-- Campaign Header --}}
            <div class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 16px;">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex align-items-start gap-3">
                        <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Tracer Study</div>
                            <h1 class="h2 mb-2" style="font-weight: 800;">{{ $campaign->title }}</h1>
                            <div style="opacity: 0.9;">
                                {{ count($campaign->questions ?? []) }} pertanyaan &middot;
                                Periode: {{ $campaign->start_date->format('d M Y') }} - {{ $campaign->end_date->format('d M Y') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form wire:submit.prevent="submit">
                {{-- Employment Snapshot --}}
                <div class="card mb-4">
                    <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                        <h3 class="card-title mb-0"><i class="fas fa-briefcase me-2 text-primary"></i>Data Pekerjaan Saat Ini</h3>
                        <small class="text-muted">Snapshot data karir kamu saat ini.</small>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status Pekerjaan <span class="text-danger">*</span></label>
                                <select wire:model.live="snapshot.employment_status" class="form-select">
                                    @foreach (EmploymentStatus::cases() as $status)
                                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                                @error('snapshot.employment_status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            @if (in_array($snapshot['employment_status'], ['working', 'entrepreneur']))
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Nama Perusahaan</label>
                                    <input type="text" wire:model.defer="snapshot.employer_name" class="form-control">
                                    @error('snapshot.employer_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Jabatan</label>
                                    <input type="text" wire:model.defer="snapshot.job_title" class="form-control">
                                    @error('snapshot.job_title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Kesesuaian Pekerjaan dengan Studi</label>
                                    <select wire:model.defer="snapshot.job_relevance" class="form-select">
                                        <option value="">-- Pilih --</option>
                                        @foreach (JobRelevance::cases() as $rel)
                                            <option value="{{ $rel->value }}">{{ $rel->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('snapshot.job_relevance') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Waktu Dapat Kerja (bulan setelah lulus)</label>
                                    <input type="number" wire:model.defer="snapshot.time_to_employment_months" class="form-control" min="0" max="600">
                                    @error('snapshot.time_to_employment_months') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Range Gaji</label>
                                    <input type="text" wire:model.defer="snapshot.salary_range" class="form-control" placeholder="Contoh: 5-8 juta">
                                    @error('snapshot.salary_range') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                            @endif
                            <div class="col-12">
                                <div class="form-check">
                                    <input type="checkbox" wire:model.defer="snapshot.further_study" class="form-check-input" id="furtherStudy">
                                    <label class="form-check-label" for="furtherStudy">Saya sedang/telah menempuh studi lanjut</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Survey Questions --}}
                <div class="card mb-4">
                    <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                        <h3 class="card-title mb-0"><i class="fas fa-list-check me-2 text-primary"></i>Pertanyaan Survei</h3>
                        <small class="text-muted">Jawab semua pertanyaan berikut dengan jujur.</small>
                    </div>
                    <div class="card-body p-4">
                        @if (empty($campaign->questions))
                            <div class="text-muted text-center py-4">Belum ada pertanyaan yang ditambahkan untuk kampanye ini.</div>
                        @else
                            <div class="d-grid gap-4">
                                @foreach ($campaign->questions as $i => $question)
                                    @php $qid = $question['id'] ?? $question['text']; @endphp
                                    <div class="border rounded p-3">
                                        <label class="form-label fw-semibold mb-2">{{ $i + 1 }}. {{ $question['text'] ?? '-' }}</label>

                                        @if (($question['type'] ?? 'text') === 'text')
                                            <input type="text" wire:model.defer="answers.{{ $qid }}" class="form-control" placeholder="Jawaban kamu...">

                                        @elseif ($question['type'] === 'textarea')
                                            <textarea wire:model.defer="answers.{{ $qid }}" class="form-control" rows="3" placeholder="Jawaban kamu..."></textarea>

                                        @elseif ($question['type'] === 'radio')
                                            @foreach ($question['options'] ?? [] as $option)
                                                <div class="form-check">
                                                    <input type="radio" wire:model.defer="answers.{{ $qid }}" value="{{ $option }}" class="form-check-input" id="q{{ $qid }}_{{ $loop->index }}">
                                                    <label class="form-check-label" for="q{{ $qid }}_{{ $loop->index }}">{{ $option }}</label>
                                                </div>
                                            @endforeach

                                        @elseif ($question['type'] === 'select')
                                            <select wire:model.defer="answers.{{ $qid }}" class="form-select">
                                                <option value="">-- Pilih --</option>
                                                @foreach ($question['options'] ?? [] as $option)
                                                    <option value="{{ $option }}">{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        @endif

                                        @error("answers.{$qid}") <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Submit --}}
                <div class="d-flex justify-content-between">
                    <a href="{{ route('alumni.tracer-study.show', ['id' => $campaign->id]) }}" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary btn-lg fw-semibold">
                        <i class="fas fa-paper-plane me-2"></i> Kirim Jawaban
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
