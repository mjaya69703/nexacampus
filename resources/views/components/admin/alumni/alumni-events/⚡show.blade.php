<?php

use App\Enums\EventType;
use App\Models\Alumni\AlumniEvent;
use Livewire\Component;

new class extends Component
{
    public AlumniEvent $event;
    public $participants;
    public array $eventTypes = [];

    public function mount($id): void
    {
        $this->event = AlumniEvent::query()->findOrFail($id);
        $this->participants = $this->event->participants()
            ->with(['alumniProfile.studyProgram'])
            ->orderBy('registered_at', 'desc')
            ->get();
        $this->eventTypes = EventType::options();
    }

    public function goBack(): void
    {
        $this->redirectRoute('admin.alumni.events.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni Management',
            'pages' => 'Detail Event Alumni',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.alumni.header
        title="Detail Kegiatan: {{ $event->title }}"
        description="Jadwal pelaksanaan, mode kegiatan, poster, dan daftar partisipasi pendaftar alumni."
        icon="calendar-check"
    >
        @activecan('alumni-event.update')
            <a href="{{ route('admin.alumni.events.edit', ['id' => $event->id]) }}" class="btn btn-sm btn-light text-warning fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-edit"></i> <span>Edit Kegiatan</span>
            </a>
        @endactivecan
        <button type="button" wire:click="goBack" class="btn btn-sm btn-outline-light text-white border-opacity-50 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </button>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-day fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tanggal Pelaksanaan</div>
                        <div class="fw-bold">{{ $event->event_date?->format('d M Y H:i') ?? '-' }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa {{ $event->is_online ? 'fa-laptop' : 'fa-building' }} fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Mode Kegiatan</div>
                        <div class="fw-bold">{{ $event->is_online ? 'Online (Virtual)' : 'Offline (Tatap Muka)' }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-users fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Peserta Terdaftar</div>
                        <div class="fw-bold">{{ $participants->count() }} / {{ $event->max_participants ?? 'Unlimited' }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-globe fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Publikasi</div>
                        <div class="fw-bold">{{ $event->is_published ? 'Published' : 'Draft / Internal' }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.alumni.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa fa-info-circle me-2 text-primary"></i>Informasi Umum Kegiatan</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Judul Event</label>
                            <div class="fw-bold fs-6 text-dark">{{ $event->title }}</div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Tipe Event</label>
                            <div>
                                <span class="badge bg-primary px-3 py-1.5 rounded-pill fs-6">{{ $eventTypes[$event->event_type] ?? $event->event_type }}</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Waktu Mulai</label>
                            <div class="fw-bold fs-6 text-dark">{{ $event->event_date?->format('d M Y, H:i WIB') ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Waktu Selesai</label>
                            <div class="fw-bold fs-6 text-dark">{{ $event->end_date?->format('d M Y, H:i WIB') ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label text-muted small mb-1">Batas Pendaftaran</label>
                            <div class="fw-bold fs-6 text-dark">{{ $event->registration_deadline?->format('d M Y, H:i WIB') ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Tempat / Lokasi</label>
                            <div class="fw-bold fs-6 text-dark">{{ $event->location ?: '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label text-muted small mb-1">Tautan Virtual (Meeting URL)</label>
                            <div>
                                @if ($event->meeting_url)
                                    <a href="{{ $event->meeting_url }}" target="_blank" class="fw-semibold text-primary d-inline-flex align-items-center gap-1">
                                        <i class="fa fa-external-link-alt"></i> <span>Buka Link Meeting</span>
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-12 mt-3">
                            <label class="form-label text-muted small mb-1">Deskripsi Kegiatan</label>
                            <div class="p-3 bg-light rounded-3 text-dark lh-lg">{!! nl2br(e($event->description ?? 'Tidak ada deskripsi rinci.')) !!}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa fa-users me-2 text-success"></i>Daftar Peserta Terdaftar ({{ $participants->count() }})</h5>
                </div>
                <div class="card-body p-4">
                    @if ($participants->isEmpty())
                        <div class="text-center py-4 text-muted">
                            <i class="fa fa-user-slash fs-2 mb-2 opacity-50"></i>
                            <p class="mb-0 small">Belum ada alumni yang mendaftar pada kegiatan ini.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="py-3 px-3 text-secondary small fw-bold">#</th>
                                        <th class="py-3 px-3 text-secondary small fw-bold">Nama Lulusan & NIM</th>
                                        <th class="py-3 px-3 text-secondary small fw-bold">Program Studi</th>
                                        <th class="py-3 px-3 text-secondary small fw-bold">Waktu Daftar</th>
                                        <th class="py-3 px-3 text-secondary small fw-bold text-center">Kehadiran</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($participants as $i => $p)
                                        <tr>
                                            <td class="px-3">{{ $i + 1 }}</td>
                                            <td class="px-3">
                                                <div class="fw-bold text-dark">{{ $p->alumniProfile?->full_name ?? '-' }}</div>
                                                <div class="small text-muted">{{ $p->alumniProfile?->nim ?? '-' }}</div>
                                            </td>
                                            <td class="px-3">{{ $p->alumniProfile?->studyProgram?->name ?? '-' }}</td>
                                            <td class="px-3 small text-muted">{{ $p->registered_at?->format('d M Y, H:i') ?? '-' }}</td>
                                            <td class="px-3 text-center">
                                                @if ($p->attended_at)
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1 rounded-pill small"><i class="fa fa-check me-1"></i> Hadir</span>
                                                @else
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2.5 py-1 rounded-pill small">Belum Hadir</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden text-center p-4 bg-white">
                @if ($event->poster_path)
                    <img src="{{ Storage::url($event->poster_path) }}" alt="Poster {{ $event->title }}" class="rounded mx-auto mb-3 object-fit-cover shadow-sm border w-100" style="max-height: 280px;">
                @else
                    <div class="bg-primary bg-opacity-10 text-primary rounded d-flex flex-column align-items-center justify-content-center mx-auto mb-3 p-4 w-100" style="height: 200px;">
                        <i class="fa fa-image fs-1 mb-2 opacity-50"></i>
                        <span class="small text-muted fw-semibold">Tidak Ada Poster Banner</span>
                    </div>
                @endif
                <h5 class="fw-bold mb-1 text-dark">{{ $event->title }}</h5>
                <p class="text-muted small mb-3">{{ $event->location ?: ($event->is_online ? 'Platform Virtual Online' : 'Lokasi Khusus') }}</p>

                <div class="mb-2">
                    @if ($event->is_published)
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-1.5 rounded-pill"><i class="fa fa-circle-check me-1 small"></i> Status Published & Terbuka</span>
                    @else
                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-1.5 rounded-pill"><i class="fa fa-circle-pause me-1 small"></i> Status Draft (Disembunyikan)</span>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <h6 class="fw-bold mb-3 text-dark"><i class="fa fa-user-check me-2 text-info"></i>Tingkat Partisipasi</h6>
                    @php
                        $max = $event->max_participants ? $event->max_participants : 100;
                        $percentage = $event->max_participants ? min(100, round(($participants->count() / $max) * 100)) : 100;
                    @endphp
                    <div class="progress rounded-pill mb-2" style="height: 10px;">
                        <div class="progress-bar bg-info" role="progressbar" style="width: {{ $percentage }}%;" aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between text-muted small">
                        <span>Pendaftar Masuk:</span>
                        <span class="fw-bold text-dark">{{ $participants->count() }} Orang</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
