<?php

use App\Models\Organization\LecturerPerformanceReview;
use App\Support\ActivePermission;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public LecturerPerformanceReview $review;

    public function mount($id): void
    {
        $this->review = LecturerPerformanceReview::with(['owner', 'edomPeriod', 'workloadPeriod', 'lecturerProfile.studyProgram'])->findOrFail($id);
    }

    public function confirmPublish(): void
    {
        $this->js('
            Swal.fire({
                title: "Publikasikan Review?",
                text: "Hasil review performa ini akan dipublikasikan kepada dosen yang bersangkutan.",
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Ya, Publikasikan!",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("publishItem")
                }
            });
        ');
    }

    #[On('publishItem')]
    public function publish(): void
    {
        if (! ActivePermission::check('lecturer-performance-review.update')) {
            session()->flash('error', 'Anda tidak memiliki izin mempublikasikan review.');
            return;
        }

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

    <x-admin.organization.header
        title="Detail Review: {{ $review->owner?->name ?? 'Dosen' }}"
        description="Hasil evaluasi kinerja tri dharma dosen untuk periode: {{ $review->edomPeriod?->name ?? 'Periode EDOM' }} &bull; Program Studi: {{ $review->lecturerProfile?->studyProgram?->name ?? '-' }}"
        icon="star"
    >
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a href="{{ route('admin.organization.lecturer-performance-reviews.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </a>
            @activecan('lecturer-performance-review.update')
                @if ($review->status !== 'published')
                    <button wire:click="confirmPublish" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                        <i class="fa fa-circle-check"></i> <span>Publikasikan Review</span>
                    </button>
                @endif
            @endactivecan
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-info fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Review</div>
                        <div class="fw-bold">{{ str($review->status ?? 'Draft')->title() }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-star fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Nilai Akhir</div>
                        <div class="fw-bold">{{ $review->final_score ?: '-' }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-briefcase fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total SKS BKD</div>
                        <div class="fw-bold">{{ $review->workload_total_sks ? $review->workload_total_sks.' SKS' : '-' }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.organization.header>

    <div class="row g-4 mb-4">
        @foreach ([
            ['label' => 'Skor Evaluasi Dosen oleh Mahasiswa (EDOM)', 'value' => $review->edom_score ?: '-', 'icon' => 'user-graduate', 'color' => 'primary', 'desc' => 'Rata-rata skor kuesioner dari ' . ($review->edom_response_count ?: 0) . ' mahasiswa.'],
            ['label' => 'Kepatuhan Jadwal Mengajar', 'value' => $review->teaching_compliance_score ? number_format((float) $review->teaching_compliance_score, 1).'%' : '-', 'icon' => 'chalkboard-user', 'color' => 'success', 'desc' => 'Persentase kehadiran dan ketepatan waktu dalam realisasi perkuliahan.'],
            ['label' => 'Kedisiplinan Absensi Kepegawaian', 'value' => $review->attendance_compliance_score ? number_format((float) $review->attendance_compliance_score, 1).'%' : '-', 'icon' => 'clock', 'color' => 'info', 'desc' => 'Tingkat kepatuhan jam kerja dan rekapitulasi kehadiran harian.'],
            ['label' => 'Realisasi Beban Kerja Dosen (BKD)', 'value' => $review->workload_total_sks ? number_format((float) $review->workload_total_sks, 1).' SKS' : '-', 'icon' => 'briefcase', 'color' => 'warning', 'desc' => 'Total SKS kegiatan tri dharma (Pendidikan, Penelitian, Pengabdian, Penunjang) yang telah disetujui.'],
            ['label' => 'Jumlah Respon Kuesioner', 'value' => number_format((int) $review->edom_response_count), 'icon' => 'comments', 'color' => 'secondary', 'desc' => 'Total pengisian EDOM yang valid dari mahasiswa.'],
            ['label' => 'Skor Akhir Gabungan (Final Score)', 'value' => $review->final_score ? number_format((float) $review->final_score, 2) : '-', 'icon' => 'award', 'color' => 'danger', 'desc' => 'Kalkulasi akhir dari bobot EDOM, kepatuhan mengajar, dan absensi.']
        ] as $item)
            <div class="col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="badge bg-{{ $item['color'] }} bg-opacity-10 text-{{ $item['color'] }} p-3 rounded-4 fs-4">
                            <i class="fa fa-{{ $item['icon'] }}"></i>
                        </span>
                        <h3 class="fw-bold text-dark mb-0 fs-3">{{ $item['value'] }}</h3>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">{{ $item['label'] }}</h6>
                    <p class="small text-muted mb-0">{{ $item['desc'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</div>
