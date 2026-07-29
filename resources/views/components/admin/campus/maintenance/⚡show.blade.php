<?php

use App\Models\Campus\FacilityMaintenanceTicket;
use Livewire\Component;

new class extends Component
{
    public FacilityMaintenanceTicket $ticket;

    public function mount(int $id): void
    {
        $this->ticket = FacilityMaintenanceTicket::with(['room.building', 'campusAsset', 'reporter', 'assignee'])->findOrFail($id);
    }

    public function markAsResolved(string $notes = 'Masalah telah selesai diperbaiki.'): void
    {
        $this->ticket->update([
            'status' => 'resolved',
            'resolution_notes' => $notes,
            'resolved_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Status tiket berhasil diubah menjadi Selesai!');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Campus Management',
            'pages' => 'Detail Tiket Kerusakan',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.campus.header
        title="Detail Tiket Kerusakan Fasilitas"
        description="Informasi pelapor, lokasi ruangan, aset terkait, penugasan teknisi, serta catatan penyelesaian perbaikan."
        icon="screwdriver-wrench"
    >
        <a href="{{ route('admin.campus.maintenance.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.campus.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-file-lines fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Informasi Tiket: {{ $ticket->ticket_number }}</h4>
                            <div class="text-muted small">Dilaporkan pada {{ $ticket->created_at?->translatedFormat('d F Y H:i') }}</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="p-3 bg-light rounded-3 mb-4">
                        <div class="text-muted small">Judul Kerusakan</div>
                        <h3 class="fw-bold text-dark mb-0">{{ $ticket->title }}</h3>
                    </div>

                    <table class="table table-borderless align-middle mb-0">
                        <tr>
                            <td class="text-muted" style="width: 180px;">No. Tiket</td>
                            <td class="fw-bold text-dark">: {{ $ticket->ticket_number }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Ruangan / Gedung</td>
                            <td class="fw-bold text-dark">: {{ $ticket->room?->name ?: '-' }} ({{ $ticket->room?->building?->name ?? '-' }})</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Aset Terkait</td>
                            <td class="fw-bold text-dark">: {{ $ticket->campusAsset?->name ?: '-' }} ({{ $ticket->campusAsset?->asset_code ?? '-' }})</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Pelapor</td>
                            <td class="fw-bold text-dark">: {{ $ticket->reporter?->name }} ({{ $ticket->reporter?->email }})</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Teknisi Penanggungjawab</td>
                            <td class="fw-bold text-dark">: {{ $ticket->assignee?->name ?: 'Belum Ditugaskan' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Tingkat Prioritas</td>
                            <td>: <span class="badge bg-warning text-white">{{ strtoupper($ticket->priority) }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Rincian Kerusakan</td>
                            <td class="text-dark">: {{ $ticket->description }}</td>
                        </tr>
                        @if($ticket->resolution_notes)
                            <tr>
                                <td class="text-muted">Catatan Solusi Perbaikan</td>
                                <td class="fw-bold text-success">: {{ $ticket->resolution_notes }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden text-center">
                <div class="card-body p-4">
                    <div class="text-muted small mb-2">Status Tiket</div>
                    <div class="mb-3">
                        @if($ticket->status === 'resolved')
                            <span class="badge bg-success text-white fs-6 px-3 py-2 rounded-pill"><i class="fa fa-check-circle me-1"></i> Selesai Ditangani</span>
                            <div class="small text-muted mt-2">Diselesaikan pada: {{ $ticket->resolved_at?->format('d M Y H:i') }}</div>
                        @elseif($ticket->status === 'in_progress')
                            <span class="badge bg-warning text-white fs-6 px-3 py-2 rounded-pill"><i class="fa fa-gears me-1"></i> Dalam Pengerjaan</span>
                        @else
                            <span class="badge bg-primary text-white fs-6 px-3 py-2 rounded-pill"><i class="fa fa-envelope-open me-1"></i> Terbuka / Baru</span>
                        @endif
                    </div>

                    @if($ticket->status !== 'resolved' && $ticket->status !== 'closed')
                        @activecan('facility-maintenance.resolve')
                            <div class="mt-4 pt-3 border-top d-grid gap-2">
                                <button type="button" class="btn btn-success rounded-pill" wire:click="markAsResolved">
                                    <i class="fa fa-check me-1"></i> Tandai Selesai Ditangani
                                </button>
                            </div>
                        @endactivecan
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
