<?php

use App\Models\Campus\Building;
use App\Models\Campus\Room;
use Livewire\Component;

new class extends Component
{
    public array $roomForm = [];
    public array $buildings = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.campus.rooms.index');
    }

    public function mount(): void
    {
        $this->buildings = Building::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        $this->roomForm = [
            'building_id' => '',
            'name' => '',
            'code' => '',
            'floor' => '',
            'capacity' => '',
            'type' => 'Classroom',
            'is_active' => true,
            'desc' => '',
        ];
    }

    public function createRoom(): void
    {
        $validatedData = $this->validate([
            'roomForm.building_id' => 'nullable|exists:buildings,id',
            'roomForm.name' => 'required|string|max:255',
            'roomForm.code' => 'nullable|string|max:50|unique:rooms,code',
            'roomForm.floor' => 'nullable|string',
            'roomForm.capacity' => 'nullable|integer|min:1',
            'roomForm.type' => 'required|in:Classroom,Laboratory,Auditorium,Office,Meeting Room,Library,Other',
            'roomForm.is_active' => 'boolean',
            'roomForm.desc' => 'nullable|string',
        ]);

        $payload = [
            'building_id' => $validatedData['roomForm']['building_id'] ?: null,
            'name' => $validatedData['roomForm']['name'],
            'code' => $validatedData['roomForm']['code'] ?: null,
            'floor' => $validatedData['roomForm']['floor'] ?: null,
            'capacity' => $validatedData['roomForm']['capacity'] ?: null,
            'type' => $validatedData['roomForm']['type'],
            'is_active' => (bool) $validatedData['roomForm']['is_active'],
            'desc' => $validatedData['roomForm']['desc'] ?: null,
            'created_by' => auth()->id(),
        ];

        Room::create($payload);

        session()->flash('success', 'Ruangan berhasil ditambahkan.');
        $this->redirectRoute('admin.campus.rooms.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Campus Management',
            'pages' => 'Tambah Ruangan',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.campus.header
        title="Tambah Ruangan Kampus"
        description="Tambahkan ruang kuliah, laboratorium, atau ruangan kerja berikut relasi gedung, lantai, dan kapasitas yang sesuai kebutuhan operasional."
        icon="door-open"
    >
        <a href="{{ route('admin.campus.rooms.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.campus.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-door-open fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Ruangan Baru</h4>
                            <div class="text-muted small">Pastikan ruangan terhubung ke gedung aktif dan memiliki kapasitas yang realistis.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label for="building_id" class="form-label fw-semibold">Gedung</label>
                            <select id="building_id" class="form-select rounded-3" wire:model.defer="roomForm.building_id">
                                <option value="">-- Pilih Gedung --</option>
                                @foreach($buildings as $building)
                                    <option value="{{ $building['id'] }}">{{ $building['name'] }}</option>
                                @endforeach
                            </select>
                            @error('roomForm.building_id')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-lg-6">
                            <label for="name" class="form-label fw-semibold">Nama Ruangan <span class="text-danger">*</span></label>
                            <input type="text" id="name" class="form-control rounded-3" placeholder="Contoh: Ruang 101" wire:model.defer="roomForm.name">
                            @error('roomForm.name')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-lg-6">
                            <label for="code" class="form-label fw-semibold">Kode Ruangan</label>
                            <input type="text" id="code" class="form-control rounded-3" placeholder="Contoh: R-101" wire:model.defer="roomForm.code">
                            @error('roomForm.code')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-lg-6">
                            <label for="floor" class="form-label fw-semibold">Lantai</label>
                            <input type="text" id="floor" class="form-control rounded-3" placeholder="Contoh: 1" wire:model.defer="roomForm.floor">
                            @error('roomForm.floor')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-lg-6">
                            <label for="capacity" class="form-label fw-semibold">Kapasitas</label>
                            <input type="number" id="capacity" class="form-control rounded-3" placeholder="Contoh: 30" wire:model.defer="roomForm.capacity" min="1">
                            @error('roomForm.capacity')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-lg-6">
                            <label for="type" class="form-label fw-semibold">Tipe Ruangan <span class="text-danger">*</span></label>
                            <select id="type" class="form-select rounded-3" wire:model.defer="roomForm.type">
                                <option value="Classroom">Classroom</option>
                                <option value="Laboratory">Laboratory</option>
                                <option value="Auditorium">Auditorium</option>
                                <option value="Office">Office</option>
                                <option value="Meeting Room">Meeting Room</option>
                                <option value="Library">Library</option>
                                <option value="Other">Other</option>
                            </select>
                            @error('roomForm.type')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label for="desc" class="form-label fw-semibold">Deskripsi</label>
                            <textarea id="desc" class="form-control rounded-3" placeholder="Deskripsi ruangan" wire:model.defer="roomForm.desc" rows="4"></textarea>
                            @error('roomForm.desc')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="roomForm.is_active">
                                    <label class="form-check-label fw-semibold" for="is_active">Ruangan aktif dan siap dipakai</label>
                                </div>
                                <div class="text-muted small mt-2">Ruangan aktif dapat dipakai untuk jadwal perkuliahan dan kebutuhan layanan kampus.</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="cancel">
                            <i class="fa fa-times me-2"></i> Batal
                        </button>
                        <button type="button" class="btn btn-primary rounded-pill px-4" wire:click="createRoom">
                            <i class="fa fa-save me-2"></i> Simpan Ruangan
                        </button>
                    </div>
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
                            <h5 class="fw-bold mb-1">Panduan Singkat</h5>
                            <div class="text-muted small">Informasi ini membantu ruangan tetap mudah dicari dan aman dipakai.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pastikan ruangan ditempatkan pada gedung yang masih aktif.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Kapasitas harus sesuai kondisi nyata untuk mencegah bentrok jadwal.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Ruangan yang dipakai jadwal aktif tidak bisa dihapus sebelum jadwal dipindahkan.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status awal</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Classroom</div>
                    <p class="text-muted small mb-0">Tipe ruangan awal disiapkan untuk kelas perkuliahan, dan bisa Anda ubah sesuai fungsi sebenarnya.</p>
                </div>
            </div>
        </div>
        </div>
</div>
