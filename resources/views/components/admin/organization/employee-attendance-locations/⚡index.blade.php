<?php

use App\Models\Organization\EmployeeAttendanceLocation;
use App\Support\ActivePermission;
use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view([
            'locations' => EmployeeAttendanceLocation::orderBy('name')->get(),
        ])->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Lokasi Absensi',
        ]);
    }

    public function delete(int $id): void
    {
        if (! ActivePermission::check('employee-attendance-location.delete')) {
            return;
        }

        $location = EmployeeAttendanceLocation::findOrFail($id);
        $location->update(['deleted_by' => auth()->id()]);
        $location->delete();
        session()->flash('success', 'Lokasi absensi berhasil dihapus.');
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Lokasi Absensi</h3>
                <small class="text-muted">Titik kantor/kampus yang menjadi acuan radius absensi GPS.</small>
            </div>
            @activecan('employee-attendance-location.create')
                <a href="{{ route('admin.organization.employee-attendance-locations.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Tambah Lokasi
                </a>
            @endactivecan
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead>
                    <tr>
                        <th>Lokasi</th>
                        <th>Koordinat</th>
                        <th>Radius</th>
                        <th>Status</th>
                        <th class="w-1">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($locations as $location)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $location->name }}</div>
                                <div class="text-secondary">{{ $location->code }}{{ $location->address ? ' - '.$location->address : '' }}</div>
                            </td>
                            <td>{{ $location->latitude }}, {{ $location->longitude }}</td>
                            <td>{{ $location->radius_meters }} m</td>
                            <td><span class="badge {{ $location->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $location->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    @activecan('employee-attendance-location.update')
                                        <a href="{{ route('admin.organization.employee-attendance-locations.edit', ['id' => $location->id]) }}" class="btn btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    @endactivecan
                                    @activecan('employee-attendance-location.delete')
                                        <button type="button" class="btn btn-danger" wire:click="delete({{ $location->id }})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    @endactivecan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-secondary py-4">Belum ada lokasi absensi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
