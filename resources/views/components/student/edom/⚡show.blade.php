<?php

use App\Models\Academic\CourseOffering;
use App\Models\Academic\LecturerProfile;
use App\Models\Organization\EdomPeriod;
use App\Models\Organization\EdomQuestion;
use App\Support\Organization\EdomService;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public EdomPeriod $period;
    public CourseOffering $offering;
    public LecturerProfile $lecturer;
    public array $questions = [];
    public array $answers = [];

    public function mount($periodId, $courseOfferingId, $lecturerProfileId): void
    {
        $this->period = EdomPeriod::findOrFail($periodId);
        $this->offering = CourseOffering::with('course')->findOrFail($courseOfferingId);
        $this->lecturer = LecturerProfile::with('user')->findOrFail($lecturerProfileId);
        $this->questions = EdomQuestion::where('is_active', true)->orderBy('sort_order')->get()->map(fn ($q) => [
            'id' => $q->id,
            'category' => $q->category,
            'question_text' => $q->question_text,
            'answer_type' => $q->answer_type,
            'is_required' => $q->is_required,
        ])->all();
    }

    public function submit(): void
    {
        app(EdomService::class)->submit(auth()->user(), $this->period, $this->offering->id, $this->lecturer->id, $this->answers);
        session()->flash('success', 'Evaluasi berhasil dikirim. Terima kasih untuk masukannya.');
        $this->redirectRoute('student.edom.index');
    }

    public function setScaleAnswer(int $questionId, int $value): void
    {
        $this->answers[$questionId] = $value;
    }

    public function render(): View
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Academic', 'pages' => 'Isi Evaluasi Dosen']);
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
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.75rem;"><i class="fas fa-pen-to-square"></i></span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Evaluasi Pembelajaran</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">{{ $offering->course?->name ?? '-' }}</h1>
                        <div style="opacity:.9;">{{ $lecturer->user?->name ?? '-' }} / {{ $period->name }}</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-list-check"></i>{{ count($questions) }} pertanyaan</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-shield-halved"></i>Anonim ke dosen</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('student.edom.index') }}" class="assignment-action" style="background:rgba(255,255,255,.95);color:#4f46e5;"><i class="fas fa-arrow-left"></i>Kembali</a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <form wire:submit.prevent="submit" class="card assignment-card">
                <div class="card-header py-3">
                    <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-star-half-stroke me-2 text-primary"></i>Form Evaluasi</h3>
                </div>
                <div class="card-body p-4">
                    <div class="assignment-shell">
                        @foreach ($questions as $index => $question)
                            <div class="assignment-list-item">
                                <div class="d-flex gap-3 align-items-start">
                                    <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;width:2.75rem;height:2.75rem;">{{ $index + 1 }}</span>
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="assignment-pill">{{ str($question['category'])->replace('_', ' ')->title() }}</span>
                                            @if ($question['is_required'])
                                                <span class="assignment-pill" style="background:#fee2e2;color:#dc2626;">Wajib</span>
                                            @endif
                                        </div>
                                        <div class="fw-bold mb-3">{{ $question['question_text'] }}</div>

                                        @if ($question['answer_type'] === 'scale')
                                            <div class="d-flex flex-wrap gap-2">
                                                @for ($i = 1; $i <= 5; $i++)
                                                    <button
                                                        type="button"
                                                        class="assignment-action"
                                                        wire:click="setScaleAnswer({{ $question['id'] }}, {{ $i }})"
                                                        style="{{ (int)($answers[$question['id']] ?? 0) === $i ? 'background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;' : 'background:#f8fafc;color:#475569;border:1px solid #e2e8f0;' }}"
                                                        aria-pressed="{{ (int)($answers[$question['id']] ?? 0) === $i ? 'true' : 'false' }}"
                                                    >
                                                        {{ $i }}
                                                    </button>
                                                @endfor
                                            </div>
                                        @else
                                            <textarea class="form-control" rows="4" wire:model.defer="answers.{{ $question['id'] }}" placeholder="Tulis masukan dengan jelas dan sopan..."></textarea>
                                        @endif

                                        @error('answers.'.$question['id']) <div class="text-danger mt-2">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    <button type="submit" class="assignment-action" wire:loading.attr="disabled" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;">
                        <span wire:loading.remove><i class="fas fa-paper-plane"></i>Kirim Evaluasi</span>
                        <span wire:loading><i class="fas fa-spinner fa-spin"></i>Memproses...</span>
                    </button>
                </div>
            </form>
        </div>

        <div class="col-xl-4">
            <div class="assignment-shell">
                <div class="assignment-panel">
                    <div class="fw-bold mb-2"><i class="fas fa-circle-info text-primary me-2"></i>Detail Kelas</div>
                    <div class="d-flex justify-content-between border-bottom py-2"><span>Mata kuliah</span><strong class="text-end">{{ $offering->course?->name ?? '-' }}</strong></div>
                    <div class="d-flex justify-content-between border-bottom py-2"><span>Dosen</span><strong class="text-end">{{ $lecturer->user?->name ?? '-' }}</strong></div>
                    <div class="d-flex justify-content-between pt-2"><span>Periode</span><strong class="text-end">{{ $period->name }}</strong></div>
                </div>
                <div class="assignment-panel">
                    <div class="fw-bold mb-2"><i class="fas fa-shield-halved text-success me-2"></i>Privasi Jawaban</div>
                    <div class="text-secondary small">Evaluasi disimpan untuk rekap pembelajaran. Hasil yang dibuka dosen berbentuk agregat tanpa identitas mahasiswa.</div>
                </div>
            </div>
        </div>
    </div>
</div>
