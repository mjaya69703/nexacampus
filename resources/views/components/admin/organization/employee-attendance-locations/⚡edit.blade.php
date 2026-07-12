<?php

use App\Models\Organization\EmployeeAttendanceLocation;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public EmployeeAttendanceLocation $location;
    public array $form = [];

    public function mount(int $id): void
    {
        $this->location = EmployeeAttendanceLocation::findOrFail($id);
        $this->form = $this->location->only(['name', 'code', 'address', 'latitude', 'longitude', 'radius_meters', 'is_active']);
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());
        $this->location->update($this->payload($validated['form'], 'updated_by'));
        session()->flash('success', 'Lokasi absensi berhasil diperbarui.');
        $this->redirectRoute('admin.organization.employee-attendance-locations.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.employee-attendance-locations.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Edit Lokasi Absensi']);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:80', Rule::unique('employee_attendance_locations', 'code')->ignore($this->location->id)],
            'form.address' => ['nullable', 'string'],
            'form.latitude' => ['required', 'numeric', 'between:-90,90'],
            'form.longitude' => ['required', 'numeric', 'between:-180,180'],
            'form.radius_meters' => ['required', 'integer', 'min:10'],
            'form.is_active' => ['boolean'],
        ];
    }

    private function payload(array $form, string $auditField): array
    {
        return [
            'name' => $form['name'],
            'code' => strtoupper($form['code']),
            'address' => $form['address'] ?: null,
            'latitude' => $form['latitude'],
            'longitude' => $form['longitude'],
            'radius_meters' => $form['radius_meters'],
            'is_active' => (bool) $form['is_active'],
            $auditField => auth()->id(),
        ];
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Edit Lokasi Absensi"
        description="Perbarui informasi koordinat, radius toleransi, atau alamat untuk titik absensi: {{ $location->name }} ({{ $location->code }})"
        icon="edit"
    >
        <a href="{{ route('admin.organization.employee-attendance-locations.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.organization.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-map-marked-alt fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Perubahan Koordinat</h4>
                            <div class="text-muted small">Sesuaikan parameter GPS dan radius toleransi jarak geofence presensi.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.organization.employee-attendance-locations._form')
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
                            <h5 class="fw-bold mb-1">Dampak Perubahan</h5>
                            <div class="text-muted small">Efek perubahan parameter geofence.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Perubahan <strong>Koordinat GPS</strong> atau <strong>Radius</strong> akan langsung berlaku pada absensi pegawai berikutnya.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Data riwayat absensi yang sudah terjadi sebelumnya tidak akan terpengaruh.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Keaktifan</div>
                    <div class="fw-bold fs-5 {{ $location->is_active ? 'text-success' : 'text-danger' }} mb-2">{{ $location->is_active ? 'Aktif' : 'Nonaktif' }}</div>
                    <p class="text-muted small mb-0">Jika dinonaktifkan, pegawai tidak dapat memilih lokasi ini lagi saat melakukan check-in / check-out presensi.</p>
                </div>
            </div>
        </div>
    </div>
</div>
