<?php

use App\Models\Campus\CampusAsset;
use App\Models\Campus\FacilityMaintenanceTicket;
use App\Models\Campus\Room;
use App\Models\User;
use Livewire\Component;

new class extends Component
{
    public FacilityMaintenanceTicket $ticket;
    public array $form = [];
    public string $reporterSearch = '';
    public string $assigneeSearch = '';
    public ?array $selectedReporter = null;
    public ?array $selectedAssignee = null;

    public function mount(int $id): void
    {
        $this->ticket = FacilityMaintenanceTicket::with(['reporter', 'assignee'])->findOrFail($id);

        if ($this->ticket->reporter) {
            $this->selectedReporter = [
                'id' => $this->ticket->reporter->id,
                'name' => $this->ticket->reporter->name,
                'email' => $this->ticket->reporter->email,
            ];
        }

        if ($this->ticket->assignee) {
            $this->selectedAssignee = [
                'id' => $this->ticket->assignee->id,
                'name' => $this->ticket->assignee->name,
                'email' => $this->ticket->assignee->email,
            ];
        }

        $this->form = [
            'title' => $this->ticket->title,
            'description' => $this->ticket->description,
            'room_id' => $this->ticket->room_id,
            'campus_asset_id' => $this->ticket->campus_asset_id,
            'reported_by_user_id' => $this->ticket->reported_by_user_id,
            'assigned_to_user_id' => $this->ticket->assigned_to_user_id,
            'priority' => $this->ticket->priority,
            'status' => $this->ticket->status,
            'resolution_notes' => $this->ticket->resolution_notes,
            'repair_cost' => $this->ticket->repair_cost,
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
            'form.status' => 'required|in:open,in_progress,resolved,closed,rejected',
            'form.resolution_notes' => 'nullable|string',
            'form.repair_cost' => 'nullable|numeric',
        ]);

        $payload = $validated['form'];
        $payload['room_id'] = $payload['room_id'] ?: null;
        $payload['campus_asset_id'] = $payload['campus_asset_id'] ?: null;
        $payload['assigned_to_user_id'] = $payload['assigned_to_user_id'] ?: null;

        if ($payload['status'] === 'resolved' && $this->ticket->status !== 'resolved') {
            $payload['resolved_at'] = now();
        }

        $payload['updated_by'] = auth()->id();
        $this->ticket->update($payload);

        session()->flash('success', 'Tiket maintenance berhasil diperbarui!');
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
            'pages' => 'Edit Tiket Kerusakan',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.campus.header
        title="Edit Tiket Maintenance"
        description="Perbarui status perbaikan, penugasan teknisi, dan informasi biaya perbaikan fasilitas kampus."
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
                            <i class="fa fa-edit fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Edit Tiket: {{ $ticket->ticket_number }}</h4>
                            <div class="text-muted small">Update status dan progres pengerjaan teknisi.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form wire:submit.prevent="save">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Judul Masalah / Kerusakan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3 @error('form.title') is-invalid @enderror" wire:model="form.title">
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

                            <div class="col-lg-4">
                                <label class="form-label fw-semibold">Tingkat Prioritas <span class="text-danger">*</span></label>
                                <select class="form-select rounded-3 @error('form.priority') is-invalid @enderror" wire:model="form.priority">
                                    <option value="low">Rendah (Low)</option>
                                    <option value="medium">Sedang (Medium)</option>
                                    <option value="high">Tinggi (High)</option>
                                    <option value="urgent">Sangat Mendesak (Urgent)</option>
                                </select>
                                @error('form.priority') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-4">
                                <label class="form-label fw-semibold">Status Pengerjaan <span class="text-danger">*</span></label>
                                <select class="form-select rounded-3 @error('form.status') is-invalid @enderror" wire:model="form.status">
                                    <option value="open">Baru / Terbuka</option>
                                    <option value="in_progress">Dalam Pengerjaan</option>
                                    <option value="resolved">Selesai Ditangani</option>
                                    <option value="closed">Ditutup</option>
                                    <option value="rejected">Ditolak</option>
                                </select>
                                @error('form.status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-4">
                                <label class="form-label fw-semibold">Biaya Perbaikan (Rp)</label>
                                <input type="number" step="any" class="form-control rounded-3 @error('form.repair_cost') is-invalid @enderror" wire:model="form.repair_cost">
                                @error('form.repair_cost') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Catatan Solusi Perbaikan</label>
                                <textarea class="form-control rounded-3 @error('form.resolution_notes') is-invalid @enderror" wire:model="form.resolution_notes" rows="2" placeholder="Catatan hasil pengerjaan teknisi..."></textarea>
                                @error('form.resolution_notes') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Rincian Deskripsi Kerusakan <span class="text-danger">*</span></label>
                                <textarea class="form-control rounded-3 @error('form.description') is-invalid @enderror" wire:model="form.description" rows="3"></textarea>
                                @error('form.description') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
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
                            <h5 class="fw-bold mb-1">Status Pengerjaan</h5>
                            <div class="text-muted small">Update histori pengerjaan teknisi.</div>
                        </div>
                    </div>

                    <div class="border rounded-3 p-3 bg-white mb-3">
                        <div class="text-muted small mb-1">Status Tiket Saat Ini</div>
                        @if($ticket->status === 'resolved')
                            <span class="badge bg-success text-white">Selesai Ditangani</span>
                        @elseif($ticket->status === 'in_progress')
                            <span class="badge bg-warning text-white">Dalam Pengerjaan</span>
                        @else
                            <span class="badge bg-primary text-white">Terbuka / Baru</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
