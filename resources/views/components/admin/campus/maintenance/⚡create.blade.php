<?php

use App\Models\Campus\CampusAsset;
use App\Models\Campus\FacilityMaintenanceTicket;
use App\Models\Campus\Room;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public array $form = [];
    public string $reporterSearch = '';
    public string $assigneeSearch = '';
    public ?array $selectedReporter = null;
    public ?array $selectedAssignee = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->selectedReporter = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];

        $this->form = [
            'title' => '',
            'description' => '',
            'room_id' => '',
            'campus_asset_id' => '',
            'reported_by_user_id' => $user->id,
            'assigned_to_user_id' => '',
            'priority' => 'medium',
        ];
    }

    public function selectReporter(int $id, string $name, string $email): void
    {
        $this->form['reported_by_user_id'] = $id;
        $this->selectedReporter = ['id' => $id, 'name' => $name, 'email' => $email];
        $this->reporterSearch = '';
    }

    public function clearReporter(): void
    {
        $this->form['reported_by_user_id'] = '';
        $this->selectedReporter = null;
        $this->reporterSearch = '';
    }

    public function selectAssignee(int $id, string $name, string $email): void
    {
        $this->form['assigned_to_user_id'] = $id;
        $this->selectedAssignee = ['id' => $id, 'name' => $name, 'email' => $email];
        $this->assigneeSearch = '';
    }

    public function clearAssignee(): void
    {
        $this->form['assigned_to_user_id'] = '';
        $this->selectedAssignee = null;
        $this->assigneeSearch = '';
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.campus.maintenance.index');
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.title' => 'required|string|max:255',
            'form.description' => 'required|string',
            'form.room_id' => 'nullable|exists:rooms,id',
            'form.campus_asset_id' => 'nullable|exists:campus_assets,id',
            'form.reported_by_user_id' => 'required|exists:users,id',
            'form.assigned_to_user_id' => 'nullable|exists:users,id',
            'form.priority' => 'required|in:low,medium,high,urgent',
        ]);

        $payload = $validated['form'];
        $payload['room_id'] = $payload['room_id'] ?: null;
        $payload['campus_asset_id'] = $payload['campus_asset_id'] ?: null;
        $payload['assigned_to_user_id'] = $payload['assigned_to_user_id'] ?: null;
        $payload['ticket_number'] = 'TCK-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        $payload['status'] = 'open';
        $payload['created_by'] = auth()->id();

        FacilityMaintenanceTicket::create($payload);

        session()->flash('success', 'Laporan kerusakan berhasil dibuat!');
        $this->redirectRoute('admin.campus.maintenance.index');
    }

    public function render()
    {
        $rooms = Room::where('is_active', true)->with('building')->orderBy('name')->get();
        $assets = CampusAsset::where('status', '!=', 'disposed')->orderBy('name')->get();

        $reporterResults = [];
        if (strlen(trim($this->reporterSearch)) >= 2) {
            $reporterResults = User::query()
                ->select(['id', 'first_name', 'last_name', 'email'])
                ->where(function ($q) {
                    $term = '%' . trim($this->reporterSearch) . '%';
                    $q->where('first_name', 'like', $term)
                      ->orWhere('last_name', 'like', $term)
                      ->orWhere('email', 'like', $term);
                })
                ->limit(8)
                ->get();
        }

        $assigneeResults = [];
        if (strlen(trim($this->assigneeSearch)) >= 2) {
            $assigneeResults = User::query()
                ->select(['id', 'first_name', 'last_name', 'email'])
                ->where(function ($q) {
                    $term = '%' . trim($this->assigneeSearch) . '%';
                    $q->where('first_name', 'like', $term)
                      ->orWhere('last_name', 'like', $term)
                      ->orWhere('email', 'like', $term);
                })
                ->limit(8)
                ->get();
        }

        return $this->view([
            'rooms' => $rooms,
            'assets' => $assets,
            'reporterResults' => $reporterResults,
            'assigneeResults' => $assigneeResults,
        ])->layout('layouts.app', [
            'menus' => 'Campus Management',
            'pages' => 'Buat Laporan Kerusakan',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.campus.header
        title="Buat Laporan Kerusakan Fasilitas"
        description="Buat tiket pengaduan kerusakan sarana prasarana kampus dan alokasikan penugasan teknisi/staff maintenance."
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
                            <i class="fa fa-triangle-exclamation fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Laporan Kerusakan Baru</h4>
                            <div class="text-muted small">Deskripsikan masalah kerusakan fasilitas secara rinci.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form wire:submit.prevent="save">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Judul Masalah / Kerusakan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3 @error('form.title') is-invalid @enderror" wire:model="form.title" placeholder="Misal: AC Ruang L201 Tidak Dingin / Proyektor Buram">
                                @error('form.title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Lokasi Ruangan Kerusakan</label>
                                <select class="form-select rounded-3 @error('form.room_id') is-invalid @enderror" wire:model="form.room_id">
                                    <option value="">-- Pilih Ruangan --</option>
                                    @foreach($rooms as $room)
                                        <option value="{{ $room->id }}">{{ $room->name }} ({{ $room->building?->name ?? 'Gedung' }})</option>
                                    @endforeach
                                </select>
                                @error('form.room_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Aset / Barang Bermasalah (Opsional)</label>
                                <select class="form-select rounded-3 @error('form.campus_asset_id') is-invalid @enderror" wire:model="form.campus_asset_id">
                                    <option value="">-- Pilih Aset Terkait --</option>
                                    @foreach($assets as $asset)
                                        <option value="{{ $asset->id }}">{{ $asset->asset_code }} - {{ $asset->name }}</option>
                                    @endforeach
                                </select>
                                @error('form.campus_asset_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Cari & Pilih Pelapor <span class="text-danger">*</span></label>
                                @if($selectedReporter)
                                    <div class="p-2 border rounded-3 bg-light d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 13px;">
                                                {{ substr($selectedReporter['name'], 0, 1) }}
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark small">{{ $selectedReporter['name'] }}</div>
                                                <div class="text-muted" style="font-size: 11px;">{{ $selectedReporter['email'] }}</div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-circle" wire:click="clearReporter" title="Ganti Pelapor">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </div>
                                @else
                                    <div class="position-relative">
                                        <input type="text" class="form-control rounded-3" wire:model.live.debounce.300ms="reporterSearch" placeholder="Ketik nama/email pelapor...">
                                        @if(count($reporterResults) > 0)
                                            <div class="position-absolute w-100 bg-white border rounded-3 shadow-sm mt-1 z-3 overflow-hidden" style="max-height: 200px; overflow-y: auto;">
                                                @foreach($reporterResults as $u)
                                                    <button type="button" class="w-100 text-start btn btn-link text-decoration-none text-dark p-2 border-bottom hover-bg-light d-flex align-items-center justify-content-between" wire:click="selectReporter({{ $u->id }}, '{{ addslashes($u->name) }}', '{{ addslashes($u->email) }}')">
                                                        <div>
                                                            <div class="fw-bold small">{{ $u->name }}</div>
                                                            <div class="text-muted" style="font-size: 11px;">{{ $u->email }}</div>
                                                        </div>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                                @error('form.reported_by_user_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Penugasan Teknisi / Staff</label>
                                @if($selectedAssignee)
                                    <div class="p-2 border rounded-3 bg-light d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 13px;">
                                                {{ substr($selectedAssignee['name'], 0, 1) }}
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark small">{{ $selectedAssignee['name'] }}</div>
                                                <div class="text-muted" style="font-size: 11px;">{{ $selectedAssignee['email'] }}</div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-circle" wire:click="clearAssignee" title="Ganti Teknisi">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </div>
                                @else
                                    <div class="position-relative">
                                        <input type="text" class="form-control rounded-3" wire:model.live.debounce.300ms="assigneeSearch" placeholder="Ketik nama/email teknisi...">
                                        @if(count($assigneeResults) > 0)
                                            <div class="position-absolute w-100 bg-white border rounded-3 shadow-sm mt-1 z-3 overflow-hidden" style="max-height: 200px; overflow-y: auto;">
                                                @foreach($assigneeResults as $u)
                                                    <button type="button" class="w-100 text-start btn btn-link text-decoration-none text-dark p-2 border-bottom hover-bg-light d-flex align-items-center justify-content-between" wire:click="selectAssignee({{ $u->id }}, '{{ addslashes($u->name) }}', '{{ addslashes($u->email) }}')">
                                                        <div>
                                                            <div class="fw-bold small">{{ $u->name }}</div>
                                                            <div class="text-muted" style="font-size: 11px;">{{ $u->email }}</div>
                                                        </div>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Tingkat Prioritas <span class="text-danger">*</span></label>
                                <select class="form-select rounded-3 @error('form.priority') is-invalid @enderror" wire:model="form.priority">
                                    <option value="low">Rendah (Low)</option>
                                    <option value="medium">Sedang (Medium)</option>
                                    <option value="high">Tinggi (High)</option>
                                    <option value="urgent">Sangat Mendesak (Urgent)</option>
                                </select>
                                @error('form.priority') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Rincian Deskripsi Kerusakan <span class="text-danger">*</span></label>
                                <textarea class="form-control rounded-3 @error('form.description') is-invalid @enderror" wire:model="form.description" rows="3" placeholder="Jelaskan secara detail kondisi kerusakan..."></textarea>
                                @error('form.description') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="cancel">
                                <i class="fa fa-times me-2"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="fa fa-save me-2"></i> Buat Tiket Kerusakan
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
                            <h5 class="fw-bold mb-1">Panduan Maintenance</h5>
                            <div class="text-muted small">Informasi pengerjaan tiket sarana prasarana.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Tiket baru secara otomatis berstatus <strong>Terbuka / Open</strong>.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pilih prioritas <strong>Urgent</strong> untuk kerusakan yang mengganggu proses perkuliahan.</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
