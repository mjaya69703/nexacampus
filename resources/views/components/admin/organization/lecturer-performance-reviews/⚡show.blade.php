<?php

use App\Models\Organization\LecturerPerformanceReview;
use Livewire\Component;

new class extends Component
{
    public LecturerPerformanceReview $review;

    public function mount($id): void
    {
        $this->review = LecturerPerformanceReview::with(['owner', 'edomPeriod', 'workloadPeriod', 'lecturerProfile.studyProgram'])->findOrFail($id);
    }

    public function publish(): void
    {
        $this->review->update(['status' => 'published', 'published_at' => now(), 'updated_by' => auth()->id()]);
        session()->flash('success', 'Review performa dipublikasikan.');
        $this->review = $this->review->fresh(['owner', 'edomPeriod', 'workloadPeriod', 'lecturerProfile.studyProgram']);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Detail Review Performa']);
    }
};
?>

<div>
    <x-alert />
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><h3 class="card-title mb-0">{{ $review->owner?->name }}</h3><small class="text-muted">{{ $review->edomPeriod?->name }} / {{ $review->lecturerProfile?->studyProgram?->name ?? '-' }}</small></div>
            <div class="d-flex gap-2">@activecan('lecturer-performance-review.update')<button wire:click="publish" class="btn btn-success"><i class="fas fa-check me-2"></i>Publikasikan</button>@endactivecan<a href="{{ route('admin.organization.lecturer-performance-reviews.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Kembali</a></div>
        </div>
    </div>
    <div class="row">
        @foreach ([['EDOM', $review->edom_score ?: '-'], ['Respon', $review->edom_response_count], ['Mengajar', $review->teaching_compliance_score ? $review->teaching_compliance_score.'%' : '-'], ['Absensi', $review->attendance_compliance_score ? $review->attendance_compliance_score.'%' : '-'], ['BKD', $review->workload_total_sks ?: '-'], ['Nilai Akhir', $review->final_score ?: '-']] as [$label, $value])
            <div class="col-md-4 mb-3"><div class="card"><div class="card-body"><small class="text-muted">{{ $label }}</small><h2 class="mb-0">{{ $value }}</h2></div></div></div>
        @endforeach
    </div>
</div>
