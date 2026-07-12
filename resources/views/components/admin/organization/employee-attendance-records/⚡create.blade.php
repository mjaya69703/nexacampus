<?php

use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\WorkUnit;
use App\Support\Organization\EmployeeAttendanceService;
use Livewire\Component;

new class extends Component
{
    public array $form = [];
    public ?int $selectedEmployeeProfileId = null;
    public string $employeeSearch = '';

    public function mount(): void
    {
        $this->form = [
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
            'check_in_at' => now()->format('Y-m-d\TH:i'),
            'check_out_at' => '',
            'work_unit_id' => '',
            'notes' => '',
        ];
    }

    public function selectEmployee(int $employeeId): void
    {
        $this->selectedEmployeeProfileId = $employeeId;
        $this->employeeSearch = '';
    }

    public function clearEmployee(): void
    {
        $this->selectedEmployeeProfileId = null;
    }

    public function save(EmployeeAttendanceService $service): void
    {
        $validated = $this->validate([
            'selectedEmployeeProfileId' => ['required', 'exists:employee_profiles,id'],
            'form.attendance_date' => ['required', 'date'],
            'form.status' => ['required', 'in:present,late,absent,leave,sick,remote'],
            'form.check_in_at' => ['nullable', 'date'],
            'form.check_out_at' => ['nullable', 'date', 'after_or_equal:form.check_in_at'],
            'form.work_unit_id' => ['nullable', 'exists:work_units,id'],
            'form.notes' => ['nullable', 'string'],
        ]);

        $employee = EmployeeProfile::findOrFail($validated['selectedEmployeeProfileId']);
        $service->recordManual($employee, $validated['form'], auth()->id());

        session()->flash('success', 'Absensi pegawai berhasil disimpan.');
        $this->redirectRoute('admin.organization.employee-attendance-records.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.employee-attendance-records.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Input Absensi Pegawai',
        ]);
    }

    public function getSelectedEmployeeProperty()
    {
        return $this->selectedEmployeeProfileId ? EmployeeProfile::with('user')->find($this->selectedEmployeeProfileId) : null;
    }

    public function getSearchableEmployeesProperty()
    {
        return EmployeeProfile::query()
            ->with('user')
            ->where('is_active', true)
            ->whereHas('user', function ($query) {
                $search = '%'.$this->employeeSearch.'%';
                $query->when(filled($this->employeeSearch), fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('username', 'like', $search)
                        ->orWhere('code', 'like', $search);
                }));
            })
            ->limit(12)
            ->get();
    }

    public function getWorkUnitsProperty()
    {
        return WorkUnit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Input Absensi Pegawai Manual"
        description="Catat dan verifikasi kehadiran pegawai yang melakukan check-in/out secara manual atau penyesuaian khusus operator kepegawaian."
        icon="clock"
    >
        <a href="{{ route('admin.organization.employee-attendance-records.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.organization.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-user-clock fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Catatan Kehadiran</h4>
                            <div class="text-muted small">Pilih nama pegawai dan masukkan waktu check-in serta status presensi yang tepat.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark required">Pilih Pegawai</label>
                            @if ($this->selectedEmployee)
                                <div class="border border-primary rounded-4 p-3 bg-primary bg-opacity-10 d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                            <i class="fas fa-user-check fs-5"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0 fs-6">{{ $this->selectedEmployee->user?->name }}</h6>
                                            <span class="small text-muted">{{ $this->selectedEmployee->employee_number ?: 'Nomor pegawai belum diisi' }} &bull; {{ $this->selectedEmployee->user?->email }}</span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-medium" wire:click="clearEmployee">
                                        <i class="fas fa-sync-alt me-1"></i> Ganti Pegawai
                                    </button>
                                </div>
                            @else
                                <div class="input-group mb-2">
                                    <span class="input-group-text bg-light text-secondary rounded-start-3"><i class="fas fa-search"></i></span>
                                    <input type="search" class="form-control rounded-end-3" wire:model.live.debounce.350ms="employeeSearch" placeholder="Cari nama, email, username, kode, atau nomor pegawai...">
                                </div>
                                <div class="list-group list-group-flush border rounded-3 overflow-hidden shadow-sm">
                                    @forelse ($this->searchableEmployees as $employee)
                                        <button type="button" class="list-group-item list-group-item-action py-3 px-4 d-flex justify-content-between align-items-center" wire:click="selectEmployee({{ $employee->id }})">
                                            <div>
                                                <span class="fw-bold text-dark d-block">{{ $employee->user?->name }}</span>
                                                <span class="small text-muted">{{ $employee->employee_number ?: 'No. Pegawai belum diisi' }}{{ $employee->user?->email ? ' - '.$employee->user?->email : '' }}</span>
                                            </div>
                                            <span class="badge bg-light text-primary rounded-pill px-3 py-1">Pilih <i class="fas fa-chevron-right ms-1"></i></span>
                                        </button>
                                    @empty
                                        <div class="list-group-item text-muted text-center py-4 small">
                                            <i class="fas fa-user-slash fs-4 d-block mb-1 text-secondary"></i> Tidak ada pegawai aktif yang cocok dengan pencarian di atas.
                                        </div>
                                    @endforelse
                                </div>
                            @endif
                            @error('selectedEmployeeProfileId') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-bold text-dark required">Tanggal Absensi</label>
                            <input type="date" class="form-control rounded-3 @error('form.attendance_date') is-invalid @enderror" wire:model.defer="form.attendance_date">
                            @error('form.attendance_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-bold text-dark required">Status Presensi</label>
                            <select class="form-select rounded-3 @error('form.status') is-invalid @enderror" wire:model.defer="form.status">
                                <option value="present">Hadir (Present)</option>
                                <option value="late">Terlambat (Late)</option>
                                <option value="absent">Tanpa Keterangan (Absent)</option>
                                <option value="leave">Cuti / Izin (Leave)</option>
                                <option value="sick">Sakit (Sick)</option>
                                <option value="remote">Kerja Jarak Jauh (Remote)</option>
                            </select>
                            @error('form.status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-bold text-dark">Unit Kerja Penugasan</label>
                            <select class="form-select rounded-3 @error('form.work_unit_id') is-invalid @enderror" wire:model.defer="form.work_unit_id">
                                <option value="">-- Ikuti Unit Utama Pegawai --</option>
                                @foreach ($this->workUnits as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->name }}{{ $unit->code ? ' ('.$unit->code.')' : '' }}</option>
                                @endforeach
                            </select>
                            @error('form.work_unit_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label fw-bold text-dark">Waktu Check-In</label>
                            <input type="datetime-local" class="form-control rounded-3 @error('form.check_in_at') is-invalid @enderror" wire:model.defer="form.check_in_at">
                            @error('form.check_in_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label fw-bold text-dark">Waktu Check-Out</label>
                            <input type="datetime-local" class="form-control rounded-3 @error('form.check_out_at') is-invalid @enderror" wire:model.defer="form.check_out_at">
                            @error('form.check_out_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">Catatan / Keterangan Manual</label>
                            <textarea class="form-control rounded-3 @error('form.notes') is-invalid @enderror" rows="3" wire:model.defer="form.notes" placeholder="Tuliskan keterangan seperti alasan penyesuaian jam atau kendala absensi..."></textarea>
                            @error('form.notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 border-top pt-4 mt-4 d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" wire:click="cancel">
                                <i class="fas fa-times me-2"></i> Batal
                            </button>
                            <button type="button" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium" wire:click="save">
                                <i class="fas fa-save me-2"></i> Simpan Absensi
                            </button>
                        </div>
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
                            <h5 class="fw-bold mb-1">Catatan Presensi Manual</h5>
                            <div class="text-muted small">Informasi input manual operator.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Input manual akan tercatat dengan sumber (source) <strong>Manual/System</strong>.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Perhitungkan durasi menit kerja (work_minutes) yang akan dikalkulasi otomatis oleh sistem berdasarkan waktu masuk dan keluar.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Validasi</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Manual Override</div>
                    <p class="text-muted small mb-0">Input manual berguna saat terjadi gangguan pada mesin presensi atau aplikasi check-in mobile pegawai.</p>
                </div>
            </div>
        </div>
    </div>
</div>
