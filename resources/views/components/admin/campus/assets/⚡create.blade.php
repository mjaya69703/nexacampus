<?php

use App\Models\Campus\CampusAsset;
use App\Models\Campus\Room;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public array $form = [];

    public function mount(): void
    {
        $this->form = [
            'asset_code' => 'AST-' . date('Y') . '-' . strtoupper(Str::random(5)),
            'name' => '',
            'category' => 'electronics',
            'room_id' => '',
            'brand' => '',
            'model_number' => '',
            'serial_number' => '',
            'condition' => 'good',
            'purchase_date' => '',
            'purchase_cost' => null,
            'is_borrowable' => false,
            'status' => 'active',
            'notes' => '',
        ];
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.campus.assets.index');
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.asset_code' => 'required|string|max:50|unique:campus_assets,asset_code',
            'form.name' => 'required|string|max:255',
            'form.category' => 'required|string',
            'form.room_id' => 'nullable|exists:rooms,id',
            'form.brand' => 'nullable|string|max:100',
            'form.model_number' => 'nullable|string|max:100',
            'form.serial_number' => 'nullable|string|max:100',
            'form.condition' => 'required|in:good,minor_damage,major_damage,in_repair',
            'form.purchase_date' => 'nullable|date',
            'form.purchase_cost' => 'nullable|numeric',
            'form.is_borrowable' => 'boolean',
            'form.status' => 'required|in:active,maintenance,disposed',
            'form.notes' => 'nullable|string',
        ]);

        $payload = $validated['form'];
        $payload['room_id'] = $payload['room_id'] ?: null;
        $payload['purchase_date'] = $payload['purchase_date'] ?: null;
        $payload['created_by'] = auth()->id();

        CampusAsset::create($payload);

        session()->flash('success', 'Aset baru berhasil ditambahkan!');
        $this->redirectRoute('admin.campus.assets.index');
    }

    public function render()
    {
        $rooms = Room::where('is_active', true)->with('building')->orderBy('name')->get();

        return $this->view([
            'rooms' => $rooms,
        ])->layout('layouts.app', [
            'menus' => 'Campus Management',
            'pages' => 'Tambah Aset Kampus',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.campus.header
        title="Tambah Aset Kampus"
        description="Tambahkan inventaris aset, peralatan IT, alat laboratorium, atau perlengkapan perkuliahan."
        icon="boxes-packing"
    >
        <a href="{{ route('admin.campus.assets.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.campus.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-box-open fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Aset & Fasilitas Baru</h4>
                            <div class="text-muted small">Kelola penempatan ruangan dan kondisi aset secara rinci.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form wire:submit.prevent="save">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Kode Aset <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3 @error('form.asset_code') is-invalid @enderror" wire:model="form.asset_code">
                                @error('form.asset_code') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Nama Barang / Aset <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3 @error('form.name') is-invalid @enderror" wire:model="form.name" placeholder="Misal: Proyektor Epson EB-X500">
                                @error('form.name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Kategori Aset <span class="text-danger">*</span></label>
                                <select class="form-select rounded-3 @error('form.category') is-invalid @enderror" wire:model="form.category">
                                    <option value="electronics">Elektronik & IT</option>
                                    <option value="audio_video">Audio Video & Multimedia</option>
                                    <option value="furniture">Furniture / Mebel</option>
                                    <option value="lab">Peralatan Laboratorium</option>
                                    <option value="general">Umum / Lainnya</option>
                                </select>
                                @error('form.category') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Lokasi Ruangan Penempatan</label>
                                <select class="form-select rounded-3 @error('form.room_id') is-invalid @enderror" wire:model="form.room_id">
                                    <option value="">-- Belum Ditempatkan / Gudang --</option>
                                    @foreach($rooms as $room)
                                        <option value="{{ $room->id }}">{{ $room->name }} ({{ $room->building?->name ?? 'Gedung' }})</option>
                                    @endforeach
                                </select>
                                @error('form.room_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-4">
                                <label class="form-label fw-semibold">Merk / Brand</label>
                                <input type="text" class="form-control rounded-3 @error('form.brand') is-invalid @enderror" wire:model="form.brand" placeholder="Epson, Sony, dll.">
                                @error('form.brand') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-4">
                                <label class="form-label fw-semibold">Nomor Model</label>
                                <input type="text" class="form-control rounded-3 @error('form.model_number') is-invalid @enderror" wire:model="form.model_number">
                                @error('form.model_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-4">
                                <label class="form-label fw-semibold">Serial Number (S/N)</label>
                                <input type="text" class="form-control rounded-3 @error('form.serial_number') is-invalid @enderror" wire:model="form.serial_number">
                                @error('form.serial_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Kondisi Barang <span class="text-danger">*</span></label>
                                <select class="form-select rounded-3 @error('form.condition') is-invalid @enderror" wire:model="form.condition">
                                    <option value="good">Baik (Normal)</option>
                                    <option value="minor_damage">Rusak Ringan</option>
                                    <option value="major_damage">Rusak Berat</option>
                                    <option value="in_repair">Sedang Dalam Perbaikan</option>
                                </select>
                                @error('form.condition') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Status Aset <span class="text-danger">*</span></label>
                                <select class="form-select rounded-3 @error('form.status') is-invalid @enderror" wire:model="form.status">
                                    <option value="active">Aktif DIGUNAKAN</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="disposed">Penghapusan / Afkir</option>
                                </select>
                                @error('form.status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Tanggal Pengadaan</label>
                                <input type="date" class="form-control rounded-3 @error('form.purchase_date') is-invalid @enderror" wire:model="form.purchase_date">
                                @error('form.purchase_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Biaya Pengadaan (Rp)</label>
                                <input type="number" step="any" class="form-control rounded-3 @error('form.purchase_cost') is-invalid @enderror" wire:model="form.purchase_cost">
                                @error('form.purchase_cost') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="is_borrowable" wire:model="form.is_borrowable">
                                        <label class="form-check-label fw-semibold" for="is_borrowable">Dapat Dipinjam Secara Terpisah (Borrowable)</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="cancel">
                                <i class="fa fa-times me-2"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="fa fa-save me-2"></i> Simpan Aset
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
                            <h5 class="fw-bold mb-1">Panduan Aset</h5>
                            <div class="text-muted small">Tracking inventaris barang kampus.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pastikan Kode Aset unik untuk mempermudah identifikasi barcode/QR.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Aset yang ditempatkan di ruangan dapat dilihat pada detail rincian ruangan.</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
