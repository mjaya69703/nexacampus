<?php

use App\Models\Academic\LecturerProfile;
use App\Support\Organization\EdomService;
use Livewire\Component;

new class extends Component
{
    public function calculateAll(): void
    {
        LecturerProfile::with('user')->where('is_active', true)->get()->each(function ($profile) {
            if ($profile->user) {
                app(EdomService::class)->calculatePerformance($profile->user);
            }
        });

        session()->flash('success', 'Review performa berhasil dihitung ulang.');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Review Performa']);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><h3 class="card-title mb-0">Review Performa Dosen</h3><small class="text-muted">Rekap performa dari EDOM, kepatuhan mengajar, absensi pegawai, dan BKD approved.</small></div>
            @activecan('lecturer-performance-review.update')<button type="button" wire:click="calculateAll" class="btn btn-primary"><i class="fas fa-calculator me-2"></i>Hitung Ulang</button>@endactivecan
        </div>
        <div class="card-body"><livewire:organization.lecturer-performance-review-table /></div>
    </div>
</div>
