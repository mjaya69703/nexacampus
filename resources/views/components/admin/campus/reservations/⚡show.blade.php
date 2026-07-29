<?php

use App\Models\Campus\RoomReservation;
use Livewire\Component;

new class extends Component
{
    public RoomReservation $reservation;

    public function mount(int $id): void
    {
        $this->reservation = RoomReservation::with(['room.building', 'user', 'approver'])->findOrFail($id);
    }

    public function approve(): void
    {
        $conflict = RoomReservation::getConflict(
            $this->reservation->room_id,
            $this->reservation->reservation_date?->format('Y-m-d'),
            $this->reservation->start_time,
            $this->reservation->end_time,
            $this->reservation->id
        );

        if ($conflict) {
            session()->flash('error', 'Gagal Menyetujui Reservasi! ' . $conflict);
            return;
        }

        $this->reservation->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Reservasi berhasil disetujui!');
    }

    public function reject(string $reason = 'Jadwal bentrok / tidak memenuhi syarat'): void
    {
        $this->reservation->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Reservasi telah ditolak.');
    }

    public function render()
    {
        $conflictMessage = RoomReservation::getConflict(
            $this->reservation->room_id,
            $this->reservation->reservation_date?->format('Y-m-d'),
            $this->reservation->start_time,
            $this->reservation->end_time,
            $this->reservation->id
        );

        return $this->view([
            'conflictMessage' => $conflictMessage,
        ])->layout('layouts.app', [
            'menus' => 'Campus Management',
            'pages' => 'Detail Reservasi Ruangan',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.campus.header
        title="Detail Reservasi Ruangan"
        description="Detail permohonan reservasi ruangan, informasi pemohon, jadwal kegiatan, dan aksi persetujuan (approval)."
        icon="calendar-check"
    >
        <a href="{{ route('admin.campus.reservations.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
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
                            <h4 class="card-title fw-bold mb-1 text-dark">Informasi Reservasi: {{ $reservation->reservation_number }}</h4>
                            <div class="text-muted small">Dibuat pada {{ $reservation->created_at?->translatedFormat('d F Y H:i') }}</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @if($conflictMessage && $reservation->status === 'pending')
                        <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-3">
                            <i class="fa fa-triangle-exclamation fs-3 text-danger"></i>
                            <div>
                                <div class="fw-bold text-danger">Peringatan Bentrok Jadwal!</div>
                                <div class="small text-danger text-opacity-90">{{ $conflictMessage }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="p-3 bg-light rounded-3 mb-4">
                        <div class="text-muted small">Judul Kegiatan</div>
                        <h3 class="fw-bold text-dark mb-0">{{ $reservation->title }}</h3>
                    </div>

                    <table class="table table-borderless align-middle mb-0">
                        <tr>
                            <td class="text-muted" style="width: 180px;">No. Reservasi</td>
                            <td class="fw-bold text-dark">: {{ $reservation->reservation_number }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Ruangan & Gedung</td>
                            <td class="fw-bold text-dark">: {{ $reservation->room?->name }} ({{ $reservation->room?->building?->name ?? 'Gedung' }})</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Pemohon</td>
                            <td class="fw-bold text-dark">: {{ $reservation->user?->name }} ({{ $reservation->user?->email }})</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Tanggal Kegiatan</td>
                            <td class="fw-bold text-dark">: {{ $reservation->reservation_date?->translatedFormat('l, d F Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Waktu Pelaksanaan</td>
                            <td class="fw-bold text-dark">: {{ substr($reservation->start_time, 0, 5) }} - {{ substr($reservation->end_time, 0, 5) }} WIB</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Estimasi Peserta</td>
                            <td class="fw-bold text-dark">: {{ $reservation->attendee_count }} Orang</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Tujuan Keperluan</td>
                            <td class="text-dark">: {{ $reservation->purpose ?: '-' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden text-center">
                <div class="card-body p-4">
                    <div class="text-muted small mb-2">Status Persetujuan</div>
                    <div class="mb-3">
                        @if($reservation->status === 'approved')
                            <span class="badge bg-success text-white fs-6 px-3 py-2 rounded-pill"><i class="fa fa-check-circle me-1"></i> Disetujui</span>
                            <div class="small text-muted mt-2">Disetujui oleh: {{ $reservation->approver?->name ?? 'Admin' }}</div>
                        @elseif($reservation->status === 'rejected')
                            <span class="badge bg-danger text-white fs-6 px-3 py-2 rounded-pill"><i class="fa fa-times-circle me-1"></i> Ditolak</span>
                            <div class="small text-danger mt-2">Alasan: {{ $reservation->rejection_reason ?: 'Tidak memenuhi syarat' }}</div>
                        @elseif($reservation->status === 'cancelled')
                            <span class="badge bg-secondary text-white fs-6 px-3 py-2 rounded-pill"><i class="fa fa-ban me-1"></i> Dibatalkan</span>
                        @else
                            <span class="badge bg-warning text-white fs-6 px-3 py-2 rounded-pill"><i class="fa fa-clock me-1"></i> Menunggu Approval</span>
                        @endif
                    </div>

                    @if($reservation->status === 'pending')
                        @activecan('room-reservation.approve')
                            <div class="mt-4 pt-3 border-top d-grid gap-2">
                                @if($conflictMessage)
                                    <div class="alert alert-warning text-dark small p-2 mb-2 rounded-3">
                                        <i class="fa fa-triangle-exclamation me-1 text-warning"></i> Tombol setujui dinonaktifkan karena bentrok dengan jadwal lain.
                                    </div>
                                    <button type="button" class="btn btn-secondary rounded-pill" disabled title="Tidak bisa disetujui karena bentrok jadwal">
                                        <i class="fa fa-lock me-1"></i> Tidak Bisa Disetujui (Bentrok)
                                    </button>
                                @else
                                    <button type="button" class="btn btn-success rounded-pill" wire:click="approve">
                                        <i class="fa fa-check me-1"></i> Setujui Reservasi
                                    </button>
                                @endif
                                <button type="button" class="btn btn-outline-danger rounded-pill" wire:click="reject">
                                    <i class="fa fa-times me-1"></i> Tolak Reservasi
                                </button>
                            </div>
                        @endactivecan
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
