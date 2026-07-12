<?php

use App\Models\Academic\LecturerProfile;
use App\Models\Organization\LecturerPerformanceReview;
use App\Support\ActivePermission;
use App\Support\Organization\EdomService;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => LecturerPerformanceReview::count(),
            'published' => LecturerPerformanceReview::where('status', 'published')->count(),
            'avg_score' => LecturerPerformanceReview::avg('final_score') ?: 0,
        ];
    }

    public function confirmCalculateAll(): void
    {
        $this->js('
            Swal.fire({
                title: "Hitung Ulang Performa?",
                text: "Sistem akan mengalkulasi ulang nilai evaluasi performa (EDOM, BKD, Absensi) untuk seluruh dosen aktif.",
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Ya, Hitung Ulang!",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("calculateAllItem")
                }
            });
        ');
    }

    #[On('calculateAllItem')]
    public function calculateAll(): void
    {
        if (! ActivePermission::check('lecturer-performance-review.update')) {
            session()->flash('error', 'Anda tidak memiliki izin menghitung ulang performa.');
            return;
        }

        LecturerProfile::with('user')->where('is_active', true)->get()->each(function ($profile) {
            if ($profile->user) {
                app(EdomService::class)->calculatePerformance($profile->user);
            }
        });

        session()->flash('success', 'Review performa berhasil dihitung ulang.');
        $this->dispatch('pg:eventRefresh-lecturerPerformanceReviewTable');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Review Performa']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Review Performa Dosen"
        description="Rekapitulasi evaluasi kinerja tri dharma dosen berdasarkan skor EDOM, kepatuhan jadwal mengajar, kedisiplinan absensi, serta kelayakan beban kerja (BKD)."
        icon="star"
    >
        @activecan('lecturer-performance-review.update')
            <button type="button" wire:click="confirmCalculateAll" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-calculator"></i> <span>Hitung Ulang Performa</span>
            </button>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-chart-line fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Evaluasi</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Published</div>
                        <div class="fw-bold">{{ number_format($this->stats()['published']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-star fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Rata-Rata Nilai</div>
                        <div class="fw-bold">{{ number_format($this->stats()['avg_score'], 1) }} / 100</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.organization.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Rekapitulasi Skor & Kualifikasi</h4>
                    <span class="text-muted small">Gunakan filter tabel untuk mencari hasil evaluasi berdasarkan nama dosen, fakultas, atau prodi.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:organization.lecturer-performance-review-table />
        </div>
    </div>
</div>
