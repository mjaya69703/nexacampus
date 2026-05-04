<?php

use App\Models\Academic\CourseSchedule;
use Livewire\Component;

new class extends Component {
    public CourseSchedule $schedule;

    public function mount($id): void
    {
        $this->schedule = CourseSchedule::with(['courseOffering.course', 'courseOffering.academicYear', 'lecturerProfile.user'])
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

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Detail Jadwal Kuliah</h5>
                <div>
                    @can('course-schedule.update')
                        <a href="{{ route('admin.academic.course-schedules.edit', ['id' => $schedule->id]) }}" class="btn btn-warning ">
                            <i class="fas fa-pencil me-1"></i> Edit
                        </a>
                    @endcan
                    <button class="btn btn-secondary " wire:click="goBack">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Mata Kuliah</label>
                        <div class="h6">
                            {{ $schedule->courseOffering?->course?->code ?? '-' }} - {{ $schedule->courseOffering?->course?->name ?? '-' }}
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Kelas</label>
                        <div class="h6">{{ $schedule->courseOffering?->label ?? '-' }}</div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Dosen</label>
                        <div class="h6">{{ $schedule->lecturerProfile?->user?->name ?? 'Jadwal Umum' }}</div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label class="form-label text-muted">Hari</label>
                        <div class="h6">{{ $schedule->day_of_week }}</div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label class="form-label text-muted">Mulai</label>
                        <div class="h6">{{ $schedule->start_time?->format('H:i') ?? '-' }}</div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label class="form-label text-muted">Selesai</label>
                        <div class="h6">{{ $schedule->end_time?->format('H:i') ?? '-' }}</div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Ruangan</label>
                        <div class="h6">{{ $schedule->room ?? '-' }}</div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Gedung</label>
                        <div class="h6">{{ $schedule->building ?? '-' }}</div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label class="form-label text-muted">Tipe Sesi</label>
                        <div><span class="badge bg-info">{{ $schedule->session_type }}</span></div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label class="form-label text-muted">Mode</label>
                        <div>
                            @php
                                $modeBadge = match($schedule->delivery_mode) {
                                    'Offline' => 'bg-success',
                                    'Online' => 'bg-primary',
                                    'Hybrid' => 'bg-warning',
                                    default => 'bg-secondary'
                                };
                            @endphp
                            <span class="badge {{ $modeBadge }}">{{ $schedule->delivery_mode }}</span>
                        </div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label class="form-label text-muted">Status</label>
                        <div>
                            @if($schedule->is_active)
                                <span class="badge bg-success">Aktif</span>
                            @else
                                <span class="badge bg-secondary">Nonaktif</span>
                            @endif
                        </div>
                    </div>

                    @if($schedule->meeting_link)
                        <div class="col-12 mb-3">
                            <label class="form-label text-muted">Meeting Link</label>
                            <div>
                                <a href="{{ $schedule->meeting_link }}" target="_blank" class="btn  btn-link">
                                    {{ $schedule->meeting_link }}
                                </a>
                            </div>
                        </div>
                    @endif

                    @if($schedule->notes)
                        <div class="col-12 mb-3">
                            <label class="form-label text-muted">Catatan</label>
                            <div class="p-2 bg-light rounded">{{ $schedule->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
