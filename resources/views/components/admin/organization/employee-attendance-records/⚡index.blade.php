<?php

use App\Models\Organization\EmployeeAttendanceRecord;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => EmployeeAttendanceRecord::count(),
            'today' => EmployeeAttendanceRecord::whereDate('attendance_date', today())->count(),
            'present_today' => EmployeeAttendanceRecord::whereDate('attendance_date', today())->where('status', 'present')->count(),
            'late_today' => EmployeeAttendanceRecord::whereDate('attendance_date', today())->where('status', 'late')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Absensi Pegawai',
        ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Daftar Catatan Absensi Pegawai"
        description="Pantau dan verifikasi riwayat check-in serta check-out harian seluruh pegawai berdasarkan lokasi presensi dan bukti foto/keterangan."
        icon="clock"
    >
        @activecan('employee-attendance-record.create')
            <a href="{{ route('admin.organization.employee-attendance-records.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Input Absensi Manual</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Absensi</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-day fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Presensi Hari Ini</div>
                        <div class="fw-bold">{{ number_format($this->stats()['today']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Hadir Tepat Waktu</div>
                        <div class="fw-bold">{{ number_format($this->stats()['present_today']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-exclamation fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Terlambat Hari Ini</div>
                        <div class="fw-bold">{{ number_format($this->stats()['late_today']) }}</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Riwayat Presensi Harian</h4>
                    <span class="text-muted small">Gunakan filter tabel untuk mencari data absensi berdasarkan pegawai, tanggal, atau status validasi.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:organization.employee-attendance-record-table />
        </div>
    </div>
</div>
