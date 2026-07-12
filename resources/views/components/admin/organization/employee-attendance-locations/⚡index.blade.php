<?php

use App\Models\Organization\EmployeeAttendanceLocation;
use App\Models\Organization\EmployeeAttendanceRecord;
use App\Support\ActivePermission;
use Livewire\Component;

new class extends Component
{
    public string $search = '';

    public function stats(): array
    {
        return [
            'total' => EmployeeAttendanceLocation::count(),
            'active' => EmployeeAttendanceLocation::where('is_active', true)->count(),
            'inactive' => EmployeeAttendanceLocation::where('is_active', false)->count(),
        ];
    }

    public function render()
    {
        $locations = EmployeeAttendanceLocation::query()
            ->when(filled($this->search), function ($query) {
                $search = '%' . $this->search . '%';
                $query->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhere('address', 'like', $search);
            })
            ->orderBy('name')
            ->get();

        return $this->view([
            'locations' => $locations,
        ])->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Lokasi Absensi',
        ]);
    }

    public function toggleActive(int $id): void
    {
        if (! ActivePermission::check('employee-attendance-location.update')) {
            session()->flash('error', 'Anda tidak memiliki izin mengubah status lokasi.');
            return;
        }

        $location = EmployeeAttendanceLocation::findOrFail($id);
        $location->update([
            'is_active' => ! $location->is_active,
            'updated_by' => auth()->id(),
        ]);
    }

    public function delete(int $id): void
    {
        if (! ActivePermission::check('employee-attendance-location.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus lokasi absensi.');
            return;
        }

        $location = EmployeeAttendanceLocation::findOrFail($id);

        if ($location->checkInRecords()->exists() || $location->checkOutRecords()->exists()) {
            session()->flash('error', 'Lokasi absensi tidak dapat dihapus karena masih digunakan pada riwayat absensi pegawai.');
            return;
        }

        $location->update(['deleted_by' => auth()->id()]);
        $location->delete();
        session()->flash('success', 'Lokasi absensi berhasil dihapus.');
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Daftar Lokasi Absensi"
        description="Kelola koordinat GPS dan radius geofence area kampus atau kantor sebagai titik validasi absensi kehadiran pegawai."
        icon="map-marker-alt"
    >
        @activecan('employee-attendance-location.create')
            <a href="{{ route('admin.organization.employee-attendance-locations.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Lokasi Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-map-marked-alt fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Lokasi</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Lokasi Aktif</div>
                        <div class="fw-bold">{{ number_format($this->stats()['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-pause fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Nonaktif</div>
                        <div class="fw-bold">{{ number_format($this->stats()['inactive']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.organization.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-3 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Titik Koordinat & Geofence</h4>
                    <span class="text-muted small">Daftar lokasi absensi resmi yang aktif dan dapat dipilih oleh sistem presensi mobile/web.</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2" style="min-width: 280px;">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa fa-search"></i></span>
                    <input type="search" class="form-control bg-light border-start-0" wire:model.live.debounce.300ms="search" placeholder="Cari lokasi, kode, atau alamat...">
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-secondary text-uppercase small fw-bold">Nama Lokasi & Alamat</th>
                            <th class="py-3 text-secondary text-uppercase small fw-bold">Koordinat GPS</th>
                            <th class="py-3 text-secondary text-uppercase small fw-bold">Radius Validasi</th>
                            <th class="py-3 text-secondary text-uppercase small fw-bold text-center">Status</th>
                            <th class="pe-4 py-3 text-end text-secondary text-uppercase small fw-bold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($locations as $location)
                            <tr class="border-bottom">
                                <td class="ps-4 py-3">
                                    <div class="fw-bold text-dark fs-6">{{ $location->name }}</div>
                                    <div class="small text-muted">
                                        <span class="badge bg-light text-secondary border me-1">{{ $location->code }}</span>
                                        {{ $location->address ?: 'Tanpa keterangan alamat detail' }}
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="font-monospace small bg-light px-2 py-1 rounded text-dark border">
                                        <i class="fas fa-satellite text-primary me-1"></i> {{ $location->latitude }}, {{ $location->longitude }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-pill fw-bold">
                                        <i class="fas fa-dot-circle me-1"></i> {{ $location->radius_meters }} Meter
                                    </span>
                                </td>
                                <td class="py-3 text-center">
                                    @activecan('employee-attendance-location.update')
                                        <div class="form-check form-switch d-inline-block m-0">
                                            <input class="form-check-input" type="checkbox" role="switch" style="cursor: pointer;"
                                                wire:click="toggleActive({{ $location->id }})"
                                                @checked($location->is_active)>
                                        </div>
                                    @else
                                        <span class="badge {{ $location->is_active ? 'bg-success' : 'bg-secondary' }} rounded-pill px-3 py-1">
                                            {{ $location->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    @endactivecan
                                </td>
                                <td class="pe-4 py-3 text-end">
                                    <div class="d-inline-flex gap-1">
                                        @activecan('employee-attendance-location.update')
                                            <a href="{{ route('admin.organization.employee-attendance-locations.edit', ['id' => $location->id]) }}" class="btn btn-sm btn-light text-primary rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;" title="Edit Lokasi">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        @endactivecan
                                        @activecan('employee-attendance-location.delete')
                                            <button type="button" class="btn btn-sm btn-light text-danger rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;" wire:click="delete({{ $location->id }})" wire:confirm="Hapus lokasi absensi {{ $location->name }}?" title="Hapus Lokasi">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @endactivecan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <div class="fs-1 text-secondary mb-2"><i class="fas fa-map-marked-alt"></i></div>
                                    <h6 class="fw-bold text-dark">Belum Ada Lokasi Absensi</h6>
                                    <p class="small text-muted mb-0">Klik tombol Tambah Lokasi Baru untuk mendaftarkan koordinat kantor atau kampus.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
