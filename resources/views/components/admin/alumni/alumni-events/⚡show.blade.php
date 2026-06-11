<?php

use App\Enums\EventType;
use App\Models\Alumni\AlumniEvent;
use Livewire\Component;

new class extends Component
{
    public AlumniEvent $event;
    public $participants;

    public function mount($id): void
    {
        $this->event = AlumniEvent::query()->findOrFail($id);
        $this->participants = $this->event->participants()
            ->with(['alumniProfile.studyProgram'])
            ->orderBy('registered_at', 'desc')
            ->get();
    }

    public function goBack(): void
    {
        $this->redirectRoute('admin.alumni.events.index');
    }

    public function render()
    {
        $eventTypes = EventType::options();

        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni',
            'pages' => 'Detail Event Alumni',
            'eventTypes' => $eventTypes,
        ]);
    }
};
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Detail Event</h5>
                <div>
                    @activecan('alumni-event.update')
                    <a href="{{ route('admin.alumni.events.edit', ['id' => $event->id]) }}" class="btn btn-warning">
                        <i class="fas fa-pencil me-1"></i> Edit
                    </a>
                    @endactivecan
                    <button class="btn btn-secondary" wire:click="goBack">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Judul</label>
                        <div class="h6 mb-0">{{ $event->title }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Tipe</label>
                        <div class="h6 mb-0">{{ $eventTypes[$event->event_type] ?? $event->event_type }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Mode</label>
                        <div><span class="badge {{ $event->is_online ? 'bg-info' : 'bg-primary' }}">{{ $event->is_online ? 'Online' : 'Offline' }}</span></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Tanggal Mulai</label>
                        <div class="h6 mb-0">{{ $event->event_date?->format('d M Y H:i') ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Tanggal Selesai</label>
                        <div class="h6 mb-0">{{ $event->end_date?->format('d M Y H:i') ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Deadline Pendaftaran</label>
                        <div class="h6 mb-0">{{ $event->registration_deadline?->format('d M Y H:i') ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Lokasi</label>
                        <div class="h6 mb-0">{{ $event->location ?? '-' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Maks Peserta</label>
                        <div class="h6 mb-0">{{ $event->max_participants ?? 'Unlimited' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Status</label>
                        <div>
                            @if ($event->is_published)
                                <span class="badge bg-success">Published</span>
                            @else
                                <span class="badge bg-secondary">Draft</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Deskripsi</label>
                        <div class="p-2 bg-light rounded">{!! nl2br(e($event->description ?? '-')) !!}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Daftar Peserta ({{ $participants->count() }})</h5>
            </div>
            <div class="card-body">
                @if ($participants->isEmpty())
                    <p class="text-muted">Belum ada peserta terdaftar.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama</th>
                                    <th>NIM</th>
                                    <th>Prodi</th>
                                    <th>Terdaftar</th>
                                    <th>Hadir</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($participants as $i => $p)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $p->alumniProfile?->full_name ?? '-' }}</td>
                                        <td>{{ $p->alumniProfile?->nim ?? '-' }}</td>
                                        <td>{{ $p->alumniProfile?->studyProgram?->name ?? '-' }}</td>
                                        <td>{{ $p->registered_at?->format('d M Y H:i') ?? '-' }}</td>
                                        <td>
                                            @if ($p->attended_at)
                                                <span class="badge bg-success">Hadir</span>
                                            @else
                                                <span class="badge bg-secondary">Belum</span>
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
</div>
