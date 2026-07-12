<?php

use App\Models\Academic\CourseSchedule;
use Livewire\Component;

new class extends Component {
    public CourseSchedule $schedule;

    public function mount($id): void
    {
        $this->schedule = CourseSchedule::with(['courseOffering.course', 'courseOffering.academicYear', 'lecturerProfile.user', 'room.building'])
            ->findOrFail($id);
    }

    public function goBack(): void
    {
        $this->redirectRoute('admin.academic.course-schedules.index');
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Detail Jadwal Kuliah',
            ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Detail Jadwal Perkuliahan"
        description="Informasi lengkap jadwal pertemuan kuliah, jam perkuliahan, alokasi ruangan, dan dosen pengampu."
        icon="calendar-alt"
    >
        <div class="d-flex align-items-center gap-2">
            @can('course-schedule.update')
                <a href="{{ route('admin.academic.course-schedules.edit', ['id' => $schedule->id]) }}" class="btn btn-sm btn-primary rounded-pill px-3 py-2 shadow-sm d-flex align-items-center gap-1">
                    <i class="fas fa-edit"></i> Edit Jadwal
                </a>
            @endcan
            <button type="button" class="btn btn-sm btn-light text-secondary rounded-pill px-3 py-2 border shadow-sm d-flex align-items-center gap-1" wire:click="goBack">
                <i class="fas fa-arrow-left"></i> Kembali
            </button>
        </div>
    </x-admin.academic.header>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title fw-bold mb-0">Informasi Mata Kuliah & Waktu Pertemuan</h5>
                    <div>
                        @if($schedule->is_active)
                            <span class="badge bg-success bg-opacity-10 text-success px-3 py-1 rounded-pill"><i class="fas fa-check-circle me-1"></i> Aktif</span>
                        @else
                            <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-1 rounded-pill"><i class="fas fa-minus-circle me-1"></i> Nonaktif</span>
                        @endif
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold mb-1">Mata Kuliah</label>
                            <div class="fw-bold fs-6 text-dark">
                                {{ $schedule->courseOffering?->course?->code ?? '-' }} - {{ $schedule->courseOffering?->course?->name ?? '-' }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold mb-1">Kelas Penawaran</label>
                            <div class="fw-bold fs-6 text-primary">{{ $schedule->courseOffering?->label ?? '-' }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold mb-1">Dosen Pengampu</label>
                            <div class="fw-bold text-dark">{{ $schedule->lecturerProfile?->user?->name ?? 'Jadwal Umum (Tanpa Dosen)' }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold mb-1">Hari Perkuliahan</label>
                            <div class="fw-bold text-dark">{{ $schedule->day_of_week }}</div>
                        </div>

                        <div class="col-md-3 col-6">
                            <label class="form-label text-muted small fw-semibold mb-1">Jam Mulai</label>
                            <div class="fw-bold fs-6 text-success">{{ $schedule->start_time?->format('H:i') ?? '-' }}</div>
                        </div>

                        <div class="col-md-3 col-6">
                            <label class="form-label text-muted small fw-semibold mb-1">Jam Selesai</label>
                            <div class="fw-bold fs-6 text-danger">{{ $schedule->end_time?->format('H:i') ?? '-' }}</div>
                        </div>

                        <div class="col-md-3 col-6">
                            <label class="form-label text-muted small fw-semibold mb-1">Tipe Sesi</label>
                            <div><span class="badge bg-info bg-opacity-10 text-info fw-semibold px-2 py-1">{{ $schedule->session_type }}</span></div>
                        </div>

                        <div class="col-md-3 col-6">
                            <label class="form-label text-muted small fw-semibold mb-1">Mode Perkuliahan</label>
                            <div>
                                @php
                                    $modeBadge = match($schedule->delivery_mode) {
                                        'Offline' => 'bg-success bg-opacity-10 text-success',
                                        'Online' => 'bg-primary bg-opacity-10 text-primary',
                                        'Hybrid' => 'bg-warning bg-opacity-10 text-warning text-dark',
                                        default => 'bg-secondary bg-opacity-10 text-secondary'
                                    };
                                @endphp
                                <span class="badge {{ $modeBadge }} fw-semibold px-2 py-1">{{ $schedule->delivery_mode }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white p-4 border-bottom">
                    <h5 class="card-title fw-bold mb-0">Lokasi & Tautan Pertemuan</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold mb-1">Ruang Kelas</label>
                            <div class="fw-bold text-dark">{{ $schedule->room?->name ?? '-' }}</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold mb-1">Gedung Kampus</label>
                            <div class="fw-bold text-dark">{{ $schedule->room?->building?->name ?? '-' }}</div>
                        </div>

                        @if($schedule->meeting_link)
                            <div class="col-12 border-top pt-3 mt-3">
                                <label class="form-label text-muted small fw-semibold mb-1">Meeting Link (Online)</label>
                                <div>
                                    <a href="{{ $schedule->meeting_link }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill d-inline-flex align-items-center gap-2 mt-1">
                                        <i class="fas fa-external-link-alt"></i> Buka Tautan Pertemuan
                                    </a>
                                </div>
                            </div>
                        @endif

                        @if($schedule->notes)
                            <div class="col-12 border-top pt-3 mt-3">
                                <label class="form-label text-muted small fw-semibold mb-1">Catatan Tambahan</label>
                                <div class="p-3 bg-light rounded-3 small text-muted">{{ $schedule->notes }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
