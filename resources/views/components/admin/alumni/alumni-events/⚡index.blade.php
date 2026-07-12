<?php

use App\Models\Alumni\AlumniEvent;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public int $totalEvents = 0;
    public int $publishedEvents = 0;
    public int $totalParticipants = 0;
    public int $onlineEvents = 0;

    public function mount(): void
    {
        $this->totalEvents = AlumniEvent::count();
        $this->publishedEvents = AlumniEvent::query()->where('is_published', true)->count();
        $this->onlineEvents = AlumniEvent::query()->where('is_online', true)->count();
        $this->totalParticipants = DB::table('alumni_event_participants')->count();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni Management',
            'pages' => 'Agenda & Event Alumni',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.alumni.header
        title="Agenda & Kegiatan Alumni"
        description="Jadwal seminar karir, temu kangen, networking, job fair, serta berbagai event kolaboratif untuk silaturahmi alumni."
        icon="calendar-check"
    >
        @activecan('alumni-event.create')
            <a href="{{ route('admin.alumni.events.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Event Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-alt fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Event</div>
                        <div class="fw-bold">{{ $totalEvents }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Dipublikasikan</div>
                        <div class="fw-bold">{{ $publishedEvents }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-users fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Peserta</div>
                        <div class="fw-bold">{{ $totalParticipants }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-laptop fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Event Online</div>
                        <div class="fw-bold">{{ $onlineEvents }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.alumni.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Statistik Kegiatan & Pendaftaran</h4>
                <div class="text-muted small">Ringkasan partisipasi lulusan dalam program pengembagan karir dan reuni kampus.</div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Event Diselenggarakan</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $totalEvents }}</div>
                            <i class="fa fa-calendar-check fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Event Aktif Dipublikasi</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $publishedEvents }}</div>
                            <i class="fa fa-globe fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Peserta Terdaftar</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $totalParticipants }}</div>
                            <i class="fa fa-users fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Webinar / Sesi Online</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $onlineEvents }}</div>
                            <i class="fa fa-video fs-4 text-warning opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Jadwal & Event Alumni</h4>
                    <span class="text-muted small">Judul kegiatan, tipe event, tanggal & jam pelaksanaan, mode lokasi, serta kuota pendaftar.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:alumni.alumni-event-table />
        </div>
    </div>
</div>
