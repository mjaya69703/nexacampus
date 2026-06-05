<?php

use App\Models\Organization\EmployeeLeaveBalance;
use App\Models\Organization\EmployeeLeaveType;
use App\Models\Organization\EmployeeProfile;
use App\Support\ActivePermission;
use Livewire\Component;

new class extends Component
{
    public array $form = [];
    public string $employeeSearch = '';
    public ?int $selectedEmployeeProfileId = null;

    public function mount(): void
    {
        $this->form = [
            'employee_leave_type_id' => '',
            'year' => now()->year,
            'allocated_days' => 12,
            'carried_over_days' => 0,
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

    public function save(): void
    {
        if (! ActivePermission::any(['employee-leave-balance.create', 'employee-leave-balance.update'])) {
            return;
        }

        $validated = $this->validate([
            'selectedEmployeeProfileId' => ['required', 'exists:employee_profiles,id'],
            'form.employee_leave_type_id' => ['required', 'exists:employee_leave_types,id'],
            'form.year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'form.allocated_days' => ['required', 'numeric', 'min:0'],
            'form.carried_over_days' => ['nullable', 'numeric', 'min:0'],
            'form.notes' => ['nullable', 'string'],
        ]);

        EmployeeLeaveBalance::updateOrCreate(
            [
                'employee_profile_id' => $validated['selectedEmployeeProfileId'],
                'employee_leave_type_id' => $validated['form']['employee_leave_type_id'],
                'year' => $validated['form']['year'],
            ],
            [
                'allocated_days' => $validated['form']['allocated_days'],
                'carried_over_days' => $validated['form']['carried_over_days'] ?: 0,
                'notes' => $validated['form']['notes'] ?: null,
                'updated_by' => auth()->id(),
                'created_by' => auth()->id(),
            ],
        );

        session()->flash('success', 'Saldo cuti pegawai berhasil disimpan.');
    }

    public function render()
    {
        return $this->view([
            'selectedEmployee' => $this->selectedEmployee(),
            'employees' => $this->employees(),
            'leaveTypes' => EmployeeLeaveType::where('is_active', true)->orderBy('name')->get(),
            'balances' => $this->balances(),
        ])->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Saldo Cuti Pegawai',
        ]);
    }

    private function selectedEmployee(): ?EmployeeProfile
    {
        return $this->selectedEmployeeProfileId ? EmployeeProfile::with('user')->find($this->selectedEmployeeProfileId) : null;
    }

    private function employees()
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
            ->limit(10)
            ->get();
    }

    private function balances()
    {
        return EmployeeLeaveBalance::query()
            ->with(['employeeProfile.user', 'leaveType'])
            ->latest('year')
            ->latest()
            ->limit(30)
            ->get();
    }
};
?>

<div>
    <x-alert />
    <div class="row row-cards">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Atur Saldo Cuti</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required">Pegawai</label>
                        @if ($selectedEmployee)
                            <div class="border rounded p-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold">{{ $selectedEmployee->user?->name }}</div>
                                    <div class="text-secondary">{{ $selectedEmployee->employee_number ?: 'Nomor pegawai belum diisi' }}</div>
                                </div>
                                <button type="button" class="btn btn-outline-secondary" wire:click="clearEmployee">Ganti</button>
                            </div>
                        @else
                            <div class="input-icon mb-2">
                                <span class="input-icon-addon"><i class="fas fa-search"></i></span>
                                <input type="search" class="form-control" wire:model.live.debounce.350ms="employeeSearch" placeholder="Cari pegawai">
                            </div>
                            <div class="list-group list-group-flush border rounded">
                                @foreach ($employees as $employee)
                                    <button type="button" class="list-group-item list-group-item-action" wire:click="selectEmployee({{ $employee->id }})">
                                        <span class="fw-semibold d-block">{{ $employee->user?->name }}</span>
                                        <span class="text-secondary">{{ $employee->employee_number ?: '-' }}{{ $employee->user?->email ? ' - '.$employee->user?->email : '' }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        @error('selectedEmployeeProfileId') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Jenis Cuti</label>
                        <select class="form-select" wire:model.defer="form.employee_leave_type_id">
                            <option value="">Pilih jenis cuti</option>
                            @foreach ($leaveTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                        @error('form.employee_leave_type_id') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Tahun</label>
                            <input type="number" class="form-control" wire:model.defer="form.year">
                            @error('form.year') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Jatah</label>
                            <input type="number" step="0.5" class="form-control" wire:model.defer="form.allocated_days">
                            @error('form.allocated_days') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Carry Over</label>
                            <input type="number" step="0.5" class="form-control" wire:model.defer="form.carried_over_days">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea class="form-control" rows="3" wire:model.defer="form.notes"></textarea>
                    </div>
                    @activecanany(['employee-leave-balance.create', 'employee-leave-balance.update'])
                        <button type="button" class="btn btn-primary w-100" wire:click="save">
                            <i class="fas fa-save me-2"></i>Simpan Saldo
                        </button>
                    @endactivecanany
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Saldo Terbaru</h3>
                </div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead>
                            <tr>
                                <th>Pegawai</th>
                                <th>Jenis</th>
                                <th>Tahun</th>
                                <th>Jatah</th>
                                <th>Pending</th>
                                <th>Terpakai</th>
                                <th>Tersedia</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($balances as $balance)
                                <tr>
                                    <td>{{ $balance->employeeProfile?->user?->name }}</td>
                                    <td>{{ $balance->leaveType?->name }}</td>
                                    <td>{{ $balance->year }}</td>
                                    <td>{{ number_format((float) $balance->allocated_days, 1) }}</td>
                                    <td>{{ number_format((float) $balance->pending_days, 1) }}</td>
                                    <td>{{ number_format((float) $balance->used_days, 1) }}</td>
                                    <td>{{ number_format($balance->availableDays(), 1) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
