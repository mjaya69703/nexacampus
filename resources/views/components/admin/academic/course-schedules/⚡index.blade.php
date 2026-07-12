<?php

use Livewire\Component;
use App\Models\Academic\CourseSchedule;

new class extends Component {
    public int $totalSchedules = 0;

    public int $activeSchedules = 0;

    public int $offlineSchedules = 0;

    public int $onlineSchedules = 0;

    public function mount(): void
    {
        $this->totalSchedules = CourseSchedule::count();
        $this->activeSchedules = CourseSchedule::where('is_active', true)->count();
        $this->offlineSchedules = CourseSchedule::where('delivery_mode', 'Offline')->count();
        $this->onlineSchedules = CourseSchedule::where('delivery_mode', 'Online')->count();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic Management',
            'pages' => 'Daftar Jadwal Kuliah',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.academic.header
        title="Manajemen Jadwal Perkuliahan"
        description="Atur dan kelola jadwal pertemuan mingguan mata kuliah, ruang kelas, jam perkuliahan, dan dosen pengampu."
        icon="calendar-alt"
    >
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @include('templates.import.academic.grid-actions')
            @activecan('course-schedule.create')
                <a href="{{ route('admin.academic.course-schedules.create') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                    <i class="fa fa-plus-circle"></i>
                    <span>Buat Jadwal</span>
                </a>
            @endactivecan
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-alt fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total</div>
                        <div class="fw-bold">{{ number_format($totalSchedules) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Aktif</div>
                        <div class="fw-bold">{{ number_format($activeSchedules) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-chalkboard-teacher fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Offline</div>
                        <div class="fw-bold">{{ number_format($offlineSchedules) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-laptop fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Online</div>
                        <div class="fw-bold">{{ number_format($onlineSchedules) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.academic.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Jadwal Kuliah</h4>
                <div class="text-muted small">Statistik singkat jadwal perkuliahan berdasarkan status dan mode pembelajaran.</div>
            </div>
            <div class="text-muted small">Gunakan filter tabel untuk penelusuran lebih lanjut.</div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Jadwal</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($totalSchedules) }}</div>
                            <i class="fa fa-calendar-alt fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Jadwal Aktif</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-success lh-1">{{ number_format($activeSchedules) }}</div>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Offline</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-info lh-1">{{ number_format($offlineSchedules) }}</div>
                            <i class="fa fa-chalkboard-teacher fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Online</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-warning lh-1">{{ number_format($onlineSchedules) }}</div>
                            <i class="fa fa-laptop fs-4 text-warning opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Direktori Jadwal Perkuliahan</h4>
                    <span class="text-muted small">Kelola jadwal pertemuan mingguan, alokasi ruang kelas, waktu perkuliahan, dan dosen pengampu.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:academic.course-schedule-table />
        </div>
    </div>
</div>
