<?php

use App\Models\Campus\Room;
use App\Models\Campus\RoomReservation;
use App\Models\User;
use Livewire\Component;

new class extends Component
{
    public RoomReservation $reservation;
    public array $form = [];
    public string $userSearch = '';
    public ?array $selectedUser = null;

    public function mount(int $id): void
    {
        $this->reservation = RoomReservation::with('user')->findOrFail($id);

        if ($this->reservation->user) {
            $this->selectedUser = [
                'id' => $this->reservation->user->id,
                'name' => $this->reservation->user->name,
                'email' => $this->reservation->user->email,
            ];
        }

        $this->form = [
            'room_id' => $this->reservation->room_id,
            'user_id' => $this->reservation->user_id,
            'title' => $this->reservation->title,
            'purpose' => $this->reservation->purpose,
            'reservation_date' => $this->reservation->reservation_date?->format('Y-m-d') ?? '',
            'start_time' => substr($this->reservation->start_time, 0, 5),
            'end_time' => substr($this->reservation->end_time, 0, 5),
            'attendee_count' => $this->reservation->attendee_count,
            'status' => $this->reservation->status,
            'rejection_reason' => $this->reservation->rejection_reason,
        ];
    }

    public function selectUser(int $id, string $name, string $email): void
    {
        $this->form['user_id'] = $id;
        $this->selectedUser = ['id' => $id, 'name' => $name, 'email' => $email];
        $this->userSearch = '';
    }

    public function clearUser(): void
    {
        $this->form['user_id'] = '';
        $this->selectedUser = null;
        $this->userSearch = '';
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.campus.reservations.index');
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.room_id' => 'required|exists:rooms,id',
            'form.user_id' => 'required|exists:users,id',
            'form.title' => 'required|string|max:255',
            'form.purpose' => 'nullable|string',
            'form.reservation_date' => 'required|date',
            'form.start_time' => 'required',
            'form.end_time' => 'required',
            'form.attendee_count' => 'required|integer|min:1',
            'form.status' => 'required|in:pending,approved,rejected,cancelled',
            'form.rejection_reason' => 'nullable|string',
        ]);

        $payload = $validated['form'];

        if ($payload['status'] === 'approved') {
            $conflict = RoomReservation::getConflict(
                (int) $payload['room_id'],
                $payload['reservation_date'],
                $payload['start_time'],
                $payload['end_time'],
                $this->reservation->id
            );

            if ($conflict) {
                session()->flash('error', 'Gagal Menyetujui Reservasi! ' . $conflict);
                return;
            }

            if ($this->reservation->status !== 'approved') {
                $payload['approved_by'] = auth()->id();
                $payload['approved_at'] = now();
            }
        }

        $payload['updated_by'] = auth()->id();
        $this->reservation->update($payload);

        session()->flash('success', 'Reservasi ruangan berhasil diperbarui!');
        $this->redirectRoute('admin.campus.reservations.index');
    }

    public function render()
    {
        $rooms = Room::where('is_active', true)->with('building')->orderBy('name')->get();

        $searchResults = [];
        if (strlen(trim($this->userSearch)) >= 2) {
            $searchResults = User::query()
                ->select(['id', 'first_name', 'last_name', 'email'])
                ->where(function ($q) {
                    $term = '%' . trim($this->userSearch) . '%';
                    $q->where('first_name', 'like', $term)
                      ->orWhere('last_name', 'like', $term)
                      ->orWhere('email', 'like', $term);
                })
                ->limit(8)
                ->get();
        }

        $conflictMessage = RoomReservation::getConflict(
            $this->form['room_id'] ? (int) $this->form['room_id'] : null,
            $this->form['reservation_date'] ?: null,
            $this->form['start_time'] ?: null,
            $this->form['end_time'] ?: null,
            $this->reservation->id
        );

        return $this->view([
            'rooms' => $rooms,
            'searchResults' => $searchResults,
            'conflictMessage' => $conflictMessage,
        ])->layout('layouts.app', [
            'menus' => 'Campus Management',
            'pages' => 'Edit Reservasi Ruangan',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.campus.header
        title="Edit Reservasi Ruangan"
        description="Perbarui informasi peminjaman ruangan, waktu pelaksanaan, atau ubah status persetujuan (approval)."
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
                            <i class="fa fa-edit fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Edit Data Reservasi: {{ $reservation->reservation_number }}</h4>
                            <div class="text-muted small">Perubahan status ke Approved akan dicek bentrok secara otomatis.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @if($conflictMessage)
                        <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-3">
                            <i class="fa fa-triangle-exclamation fs-3 text-warning"></i>
                            <div>
                                <div class="fw-bold text-dark">Peringatan Bentrok Terdeteksi!</div>
                                <div class="small text-muted">{{ $conflictMessage }}</div>
                                <div class="small text-muted mt-1">Status tidak dapat diubah ke Disetujui (Approved) selama jadwal masih bentrok.</div>
                            </div>
                        </div>
                    @endif

                    <form wire:submit.prevent="save">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Pilih Ruangan <span class="text-danger">*</span></label>
                                <select class="form-select rounded-3 @error('form.room_id') is-invalid @enderror" wire:model.live="form.room_id">
                                    <option value="">-- Pilih Ruangan --</option>
                                    @foreach($rooms as $room)
                                        <option value="{{ $room->id }}">{{ $room->name }} ({{ $room->building?->name ?? 'Gedung' }})</option>
                                    @endforeach
                                </select>
                                @error('form.room_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Cari & Pilih Pemohon <span class="text-danger">*</span></label>
                                @if($selectedUser)
                                    <div class="p-2 border rounded-3 bg-light d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 13px;">
                                                {{ substr($selectedUser['name'], 0, 1) }}
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark small">{{ $selectedUser['name'] }}</div>
                                                <div class="text-muted" style="font-size: 11px;">{{ $selectedUser['email'] }}</div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-circle" wire:click="clearUser" title="Ganti User">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </div>
                                @else
                                    <div class="position-relative">
                                        <input type="text" class="form-control rounded-3" wire:model.live.debounce.300ms="userSearch" placeholder="Ketik nama atau email user...">
                                        @if(count($searchResults) > 0)
                                            <div class="position-absolute w-100 bg-white border rounded-3 shadow-sm mt-1 z-3 overflow-hidden" style="max-height: 220px; overflow-y: auto;">
                                                @foreach($searchResults as $u)
                                                    <button type="button" class="w-100 text-start btn btn-link text-decoration-none text-dark p-2 border-bottom hover-bg-light d-flex align-items-center justify-content-between" wire:click="selectUser({{ $u->id }}, '{{ addslashes($u->name) }}', '{{ addslashes($u->email) }}')">
                                                        <div>
                                                            <div class="fw-bold small">{{ $u->name }}</div>
                                                            <div class="text-muted" style="font-size: 11px;">{{ $u->email }}</div>
                                                        </div>
                                                        <i class="fa fa-chevron-right text-muted small"></i>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @elseif(strlen(trim($userSearch)) >= 2)
                                            <div class="position-absolute w-100 bg-white border rounded-3 p-2 text-muted small shadow-sm mt-1">
                                                User tidak ditemukan...
                                            </div>
                                        @endif
                                    </div>
                                @endif
                                @error('form.user_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Judul Kegiatan / Acara <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3 @error('form.title') is-invalid @enderror" wire:model="form.title">
                                @error('form.title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-4">
                                <label class="form-label fw-semibold">Tanggal Kegiatan <span class="text-danger">*</span></label>
                                <input type="date" class="form-control rounded-3 @error('form.reservation_date') is-invalid @enderror" wire:model.live="form.reservation_date">
                                @error('form.reservation_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-4">
                                <label class="form-label fw-semibold">Jam Mulai <span class="text-danger">*</span></label>
                                <input type="time" class="form-control rounded-3 @error('form.start_time') is-invalid @enderror" wire:model.live="form.start_time">
                                @error('form.start_time') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-4">
                                <label class="form-label fw-semibold">Jam Selesai <span class="text-danger">*</span></label>
                                <input type="time" class="form-control rounded-3 @error('form.end_time') is-invalid @enderror" wire:model.live="form.end_time">
                                @error('form.end_time') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Jumlah Peserta</label>
                                <input type="number" class="form-control rounded-3 @error('form.attendee_count') is-invalid @enderror" wire:model="form.attendee_count">
                                @error('form.attendee_count') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Status Persetujuan <span class="text-danger">*</span></label>
                                <select class="form-select rounded-3 @error('form.status') is-invalid @enderror" wire:model="form.status">
                                    <option value="pending">Menunggu Approval</option>
                                    <option value="approved" @if($conflictMessage) disabled @endif>Disetujui (Approved) @if($conflictMessage) - (Bentrok) @endif</option>
                                    <option value="rejected">Ditolak (Rejected)</option>
                                    <option value="cancelled">Dibatalkan (Cancelled)</option>
                                </select>
                                @error('form.status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            @if($form['status'] === 'rejected')
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Alasan Penolakan</label>
                                    <textarea class="form-control rounded-3 @error('form.rejection_reason') is-invalid @enderror" wire:model="form.rejection_reason" rows="2"></textarea>
                                    @error('form.rejection_reason') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                            @endif

                            <div class="col-12">
                                <label class="form-label fw-semibold">Tujuan Keperluan</label>
                                <textarea class="form-control rounded-3 @error('form.purpose') is-invalid @enderror" wire:model="form.purpose" rows="2"></textarea>
                                @error('form.purpose') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="cancel">
                                <i class="fa fa-times me-2"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="fa fa-save me-2"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-circle-info"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Status Reservasi</h5>
                            <div class="text-muted small">Informasi persetujuan reservasi.</div>
                        </div>
                    </div>

                    <div class="border rounded-3 p-3 bg-white mb-3">
                        <div class="text-muted small mb-1">Status Saat Ini</div>
                        @if($reservation->status === 'approved')
                            <span class="badge bg-success text-white">Disetujui</span>
                        @elseif($reservation->status === 'rejected')
                            <span class="badge bg-danger text-white">Ditolak</span>
                        @elseif($reservation->status === 'cancelled')
                            <span class="badge bg-secondary text-white">Dibatalkan</span>
                        @else
                            <span class="badge bg-warning text-white">Menunggu Approval</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
