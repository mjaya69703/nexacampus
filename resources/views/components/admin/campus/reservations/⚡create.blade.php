<?php

use App\Models\Campus\Room;
use App\Models\Campus\RoomReservation;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public array $form = [];
    public string $userSearch = '';
    public ?array $selectedUser = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->selectedUser = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];

        $this->form = [
            'room_id' => '',
            'user_id' => $user->id,
            'title' => '',
            'purpose' => '',
            'reservation_date' => date('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'attendee_count' => 10,
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
        ]);

        $payload = $validated['form'];
        $payload['reservation_number'] = 'RES-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        $payload['status'] = 'pending';
        $payload['created_by'] = auth()->id();

        RoomReservation::create($payload);

        session()->flash('success', 'Permohonan peminjaman ruangan berhasil dibuat!');
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
            $this->form['end_time'] ?: null
        );

        return $this->view([
            'rooms' => $rooms,
            'searchResults' => $searchResults,
            'conflictMessage' => $conflictMessage,
        ])->layout('layouts.app', [
            'menus' => 'Campus Management',
            'pages' => 'Buat Permohonan Reservasi',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.campus.header
        title="Buat Permohonan Reservasi Ruangan"
        description="Ajukan jadwal peminjaman ruangan perkuliahan, laboratorium, atau aula untuk kegiatan civitas akademika."
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
                            <i class="fa fa-calendar-plus fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Reservasi Ruangan</h4>
                            <div class="text-muted small">Lengkapi informasi pemohon, jadwal, ruangan, dan estimasi peserta.</div>
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
                                <div class="small text-muted mt-1">Anda tetap dapat mengajukan draft reservasi, namun admin tidak dapat menyetujuinya selama jadwal masih bentrok.</div>
                            </div>
                        </div>
                    @elseif($form['room_id'] && $form['reservation_date'] && $form['start_time'] && $form['end_time'])
                        <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-3 py-2">
                            <i class="fa fa-circle-check fs-4 text-success"></i>
                            <div class="small fw-semibold text-success">Ruangan Bebas Bentrok pada Tanggal & Jam Terpilih.</div>
                        </div>
                    @endif

                    <form wire:submit.prevent="save">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Pilih Ruangan <span class="text-danger">*</span></label>
                                <select class="form-select rounded-3 @error('form.room_id') is-invalid @enderror" wire:model.live="form.room_id">
                                    <option value="">-- Pilih Ruangan --</option>
                                    @foreach($rooms as $room)
                                        <option value="{{ $room->id }}">{{ $room->name }} ({{ $room->building?->name ?? 'Gedung' }}) - Kapasitas: {{ $room->capacity }}</option>
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
                                <input type="text" class="form-control rounded-3 @error('form.title') is-invalid @enderror" wire:model="form.title" placeholder="Contoh: Seminar Teknologi AI & Workshop Cloud 2026">
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
                                <label class="form-label fw-semibold">Estimasi Jumlah Peserta <span class="text-danger">*</span></label>
                                <input type="number" class="form-control rounded-3 @error('form.attendee_count') is-invalid @enderror" wire:model="form.attendee_count" min="1">
                                @error('form.attendee_count') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Tujuan & Rincian Keperluan</label>
                                <textarea class="form-control rounded-3 @error('form.purpose') is-invalid @enderror" wire:model="form.purpose" rows="3" placeholder="Jelaskan rincian agenda dan kebutuhan pendukung..."></textarea>
                                @error('form.purpose') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="cancel">
                                <i class="fa fa-times me-2"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="fa fa-save me-2"></i> Simpan Permohonan
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
                            <h5 class="fw-bold mb-1">Panduan Reservasi</h5>
                            <div class="text-muted small">Aturan peminjaman sarana ruangan kampus.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pastikan ruangan dalam status aktif sebelum memilih.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Periksa kapasitas ruangan agar cukup menampung estimasi peserta.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Permohonan baru akan masuk ke status <strong>Menunggu Approval</strong> admin.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Keterangan Tambahan</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Verifikasi Jadwal Real-time</div>
                    <p class="text-muted small mb-0">Sistem otomatis memeriksa bentrok dengan <strong>Jadwal Perkuliahan</strong> dan <strong>Reservasi Lain yang Disetujui</strong> saat tanggal dan jam dipilih.</p>
                </div>
            </div>
        </div>
    </div>
</div>
