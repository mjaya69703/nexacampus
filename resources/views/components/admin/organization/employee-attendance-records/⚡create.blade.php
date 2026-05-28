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
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Input Absensi Pegawai</h3>
            <a href="{{ route('admin.organization.employee-attendance-records.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
        <div class="card-body">
            <div class="row row-cards">
                <div class="col-12">
                    <label class="form-label required">Pegawai</label>
                    @if ($this->selectedEmployee)
                        <div class="border rounded p-3 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold">{{ $this->selectedEmployee->user?->name }}</div>
                                <div class="text-secondary">{{ $this->selectedEmployee->employee_number ?: 'Nomor pegawai belum diisi' }}</div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary" wire:click="clearEmployee">Ganti</button>
                        </div>
                    @else
                        <div class="input-icon mb-2">
                            <span class="input-icon-addon"><i class="fas fa-search"></i></span>
                            <input type="search" class="form-control" wire:model.live.debounce.350ms="employeeSearch" placeholder="Cari nama, email, username, kode, atau nomor pegawai">
                        </div>
                        <div class="list-group list-group-flush border rounded">
                            @forelse ($this->searchableEmployees as $employee)
                                <button type="button" class="list-group-item list-group-item-action" wire:click="selectEmployee({{ $employee->id }})">
                                    <span class="fw-semibold d-block">{{ $employee->user?->name }}</span>
                                    <span class="text-secondary">{{ $employee->employee_number ?: 'Nomor pegawai belum diisi' }}{{ $employee->user?->email ? ' - '.$employee->user?->email : '' }}</span>
                                </button>
                            @empty
                                <div class="list-group-item text-secondary">Tidak ada pegawai aktif yang cocok.</div>
                            @endforelse
                        </div>
                    @endif
                    @error('selectedEmployeeProfileId') <span class="text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="col-lg-4">
                    <label class="form-label required">Tanggal</label>
                    <input type="date" class="form-control" wire:model.defer="form.attendance_date">
                    @error('form.attendance_date') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="col-lg-4">
                    <label class="form-label required">Status</label>
                    <select class="form-select" wire:model.defer="form.status">
                        <option value="present">Present</option>
                        <option value="late">Late</option>
                        <option value="absent">Absent</option>
                        <option value="leave">Leave</option>
                        <option value="sick">Sick</option>
                        <option value="remote">Remote</option>
                    </select>
                    @error('form.status') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="col-lg-4">
                    <label class="form-label">Unit Kerja</label>
                    <select class="form-select" wire:model.defer="form.work_unit_id">
                        <option value="">Ikuti unit utama pegawai</option>
                        @foreach ($this->workUnits as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}{{ $unit->code ? ' - '.$unit->code : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-6">
                    <label class="form-label">Check In</label>
                    <input type="datetime-local" class="form-control" wire:model.defer="form.check_in_at">
                </div>
                <div class="col-lg-6">
                    <label class="form-label">Check Out</label>
                    <input type="datetime-local" class="form-control" wire:model.defer="form.check_out_at">
                    @error('form.check_out_at') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan</label>
                    <textarea class="form-control" rows="3" wire:model.defer="form.notes"></textarea>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-primary" wire:click="save"><i class="fas fa-save me-2"></i>Simpan</button>
                    <button type="button" class="btn btn-secondary" wire:click="cancel">Batal</button>
                </div>
            </div>
        </div>
    </div>
</div>
