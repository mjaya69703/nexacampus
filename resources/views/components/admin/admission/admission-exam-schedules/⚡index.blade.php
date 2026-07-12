<?php

use Livewire\Component;
use App\Models\Admission\AdmissionExamSchedule;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    public function render()
    {
        $totalSchedules = AdmissionExamSchedule::count();
        $activeSchedules = AdmissionExamSchedule::where('is_active', true)->count();
        $totalParticipants = DB::table('admission_exam_participants')->count();

        return $this->view([
            'totalSchedules' => $totalSchedules,
            'activeSchedules' => $activeSchedules,
            'totalParticipants' => $totalParticipants,
        ])->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Jadwal Ujian & Seleksi Masuk',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.admission.header
        title="Jadwal Ujian & Seleksi Masuk"
        description="Atur jadwal tes tertulis, wawancara, atau ujian praktik bagi calon mahasiswa baru, lengkap dengan kuota ruangan dan daftar hadir."
        icon="calendar-check"
    >
        @activecan('admission-exam-schedule.create')
            <a href="{{ route('admin.admission.admission-exam-schedules.create') }}" class="btn btn-light rounded-pill px-4 py-2 text-primary fw-bold shadow-sm d-inline-flex align-items-center gap-2 border-0">
                <i class="fas fa-plus-circle"></i> Tambah Jadwal Baru
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-calendar-alt text-warning fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Total Jadwal</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($totalSchedules) }} <small class="fs-7 fw-normal">Sesi Ujian</small></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-check-circle text-success fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Jadwal Aktif</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($activeSchedules) }} <small class="fs-7 fw-normal">Sesi Dibuka</small></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-users text-info fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Total Peserta</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($totalParticipants) }} <small class="fs-7 fw-normal">Calon Mhs</small></div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.admission.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-list text-primary"></i> Daftar Sesi Ujian & Seleksi
                </h4>
                <p class="text-muted fs-7 mb-0">Kelola rincian tanggal, waktu, kapasitas ruangan, serta rekap kehadiran peserta ujian masuk kampus.</p>
            </div>
        </div>
        <div class="card-body p-4">
            <livewire:admission.exam-schedule-table />
        </div>
    </div>
</div>
