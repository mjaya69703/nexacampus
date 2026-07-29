<?php

use App\Models\Publication\Agenda;
use Livewire\Component;

new class extends Component
{
    public int $totalAgendas = 0;

    public int $publishedCount = 0;

    public int $draftCount = 0;

    public int $upcomingCount = 0;

    public int $pastCount = 0;

    public function mount(): void
    {
        $this->totalAgendas = Agenda::count();
        $this->publishedCount = Agenda::where('is_published', true)->count();
        $this->draftCount = Agenda::where('is_published', false)->count();
        $this->upcomingCount = Agenda::where('event_date', '>=', now()->startOfDay())->count();
        $this->pastCount = Agenda::where('event_date', '<', now()->startOfDay())->count();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publication',
            'pages' => 'Daftar Agenda',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.publication.header
        title="Manajemen Agenda"
        description="Kelola seluruh agenda dan acara kampus, termasuk jadwal, lokasi, kategori, dan status publikasi agar informasi kegiatan tersampaikan dengan baik."
        icon="calendar-days"
    >
        @activecan('agenda.create')
            <a href="{{ route('admin.publication.agendas.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i>
                <span>Tambah Agenda</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-days fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total</div>
                        <div class="fw-bold">{{ $totalAgendas }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Published</div>
                        <div class="fw-bold">{{ $publishedCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Draft</div>
                        <div class="fw-bold">{{ $draftCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-arrow-up fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Akan Datang</div>
                        <div class="fw-bold">{{ $upcomingCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-arrow-down fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Terlewat</div>
                        <div class="fw-bold">{{ $pastCount }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.publication.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Agenda</h4>
                <div class="text-muted small">Statistik singkat untuk memantau jadwal dan status agenda kampus.</div>
            </div>
            <div class="text-muted small">
                Gunakan filter dan toggle untuk mengelola agenda secara cepat.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $totalAgendas }}</div>
                            <i class="fa fa-calendar-days fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Published</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-success lh-1">{{ $publishedCount }}</div>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Draft</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-secondary lh-1">{{ $draftCount }}</div>
                            <i class="fa fa-clock fs-4 text-secondary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Akan Datang</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-info lh-1">{{ $upcomingCount }}</div>
                            <i class="fa fa-arrow-up fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Terlewat</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-danger lh-1">{{ $pastCount }}</div>
                            <i class="fa fa-arrow-down fs-4 text-danger opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Agenda</h4>
                    <span class="text-muted small">Judul, kategori, tanggal, jam, lokasi, status, dan aksi pengelolaan agenda.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:publication.agenda-table />
        </div>
    </div>
</div>
