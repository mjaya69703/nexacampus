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

    public function stats(): array
    {
        $currentYear = now()->year;
        return [
            'total_balances' => EmployeeLeaveBalance::where('year', $currentYear)->count(),
            'total_employees' => EmployeeLeaveBalance::where('year', $currentYear)->distinct('employee_profile_id')->count('employee_profile_id'),
            'total_allocated' => EmployeeLeaveBalance::where('year', $currentYear)->sum('allocated_days'),
            'total_used' => EmployeeLeaveBalance::where('year', $currentYear)->sum('used_days'),
        ];
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

    <x-admin.organization.header
        title="Manajemen Saldo Cuti Pegawai"
        description="Atur kuota cuti tahunan, carry-over sisa cuti tahun lalu, serta pantau jumlah pemakaian hak cuti masing-masing pegawai."
        icon="calendar-check"
    >
        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-users fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Pegawai Bersaldo ({{ now()->year }})</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total_employees']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-layer-group fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Catatan Saldo</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total_balances']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-plus fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Kuota Dialokasikan</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total_allocated'], 1) }} Hari</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-minus fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Hari Terpakai</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total_used'], 1) }} Hari</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.organization.header>

    <div class="row g-4 mb-4 align-items-start">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-sliders-h fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Atur & Penyesuaian Saldo</h4>
                            <div class="text-muted small">Tentukan kuota hak cuti pegawai pada periode tahun terpilih.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark required">Pilih Pegawai</label>
                        @if ($selectedEmployee)
                            <div class="border border-primary rounded-4 p-3 bg-primary bg-opacity-10 d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                        <i class="fas fa-user fs-6"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0 fs-6">{{ $selectedEmployee->user?->name }}</h6>
                                        <span class="small text-muted">{{ $selectedEmployee->employee_number ?: 'Nomor pegawai belum diisi' }}</span>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-medium" wire:click="clearEmployee">
                                    <i class="fas fa-sync-alt me-1"></i> Ganti
                                </button>
                            </div>
                        @else
                            <div class="input-group mb-2">
                                <span class="input-group-text bg-light text-secondary rounded-start-3"><i class="fas fa-search"></i></span>
                                <input type="search" class="form-control rounded-end-3" wire:model.live.debounce.350ms="employeeSearch" placeholder="Cari nama atau nomor pegawai...">
                            </div>
                            <div class="list-group list-group-flush border rounded-3 overflow-hidden shadow-sm">
                                @forelse ($employees as $employee)
                                    <button type="button" class="list-group-item list-group-item-action py-3 px-3 d-flex justify-content-between align-items-center" wire:click="selectEmployee({{ $employee->id }})">
                                        <div>
                                            <span class="fw-bold text-dark d-block">{{ $employee->user?->name }}</span>
                                            <span class="small text-muted">{{ $employee->employee_number ?: '-' }}{{ $employee->user?->email ? ' - '.$employee->user?->email : '' }}</span>
                                        </div>
                                        <i class="fas fa-chevron-right text-muted small"></i>
                                    </button>
                                @empty
                                    <div class="list-group-item text-muted text-center py-3 small">
                                        Tidak ada pegawai cocok dengan pencarian.
                                    </div>
                                @endforelse
                            </div>
                        @endif
                        @error('selectedEmployeeProfileId') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark required">Jenis Cuti</label>
                        <select class="form-select rounded-3 @error('form.employee_leave_type_id') is-invalid @enderror" wire:model.defer="form.employee_leave_type_id">
                            <option value="">-- Pilih Jenis Cuti --</option>
                            @foreach ($leaveTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }} ({{ $type->code }})</option>
                            @endforeach
                        </select>
                        @error('form.employee_leave_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark required">Tahun</label>
                            <input type="number" class="form-control rounded-3 @error('form.year') is-invalid @enderror" wire:model.defer="form.year">
                            @error('form.year') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark required">Jatah Cuti</label>
                            <input type="number" step="0.5" class="form-control rounded-3 @error('form.allocated_days') is-invalid @enderror" wire:model.defer="form.allocated_days" placeholder="12">
                            @error('form.allocated_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark">Carry Over</label>
                            <input type="number" step="0.5" class="form-control rounded-3" wire:model.defer="form.carried_over_days" placeholder="0">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark">Catatan / Keterangan</label>
                        <textarea class="form-control rounded-3" rows="3" wire:model.defer="form.notes" placeholder="Alasan penyesuaian saldo atau keterangan khusus cuti..."></textarea>
                    </div>

                    @activecanany(['employee-leave-balance.create', 'employee-leave-balance.update'])
                        <button type="button" class="btn btn-primary rounded-pill shadow-sm w-100 py-2 fw-medium" wire:click="save">
                            <i class="fas fa-save me-2"></i> Simpan Penyesuaian Saldo
                        </button>
                    @endactivecanany
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-history fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Daftar Saldo Cuti Terbaru</h4>
                            <div class="text-muted small">Daftar alokasi dan sisa hak cuti pegawai yang baru saja diperbarui.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-secondary text-uppercase small fw-bold">Pegawai</th>
                                    <th class="py-3 text-secondary text-uppercase small fw-bold">Jenis & Tahun</th>
                                    <th class="py-3 text-center text-secondary text-uppercase small fw-bold">Jatah</th>
                                    <th class="py-3 text-center text-secondary text-uppercase small fw-bold">Pending</th>
                                    <th class="py-3 text-center text-secondary text-uppercase small fw-bold">Terpakai</th>
                                    <th class="pe-4 py-3 text-end text-secondary text-uppercase small fw-bold">Sisa Tersedia</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($balances as $balance)
                                    <tr class="border-bottom">
                                        <td class="ps-4 py-3">
                                            <div class="fw-bold text-dark">{{ $balance->employeeProfile?->user?->name }}</div>
                                            <div class="small text-muted">{{ $balance->employeeProfile?->employee_number ?: '-' }}</div>
                                        </td>
                                        <td class="py-3">
                                            <span class="badge bg-light text-dark border me-1">{{ $balance->leaveType?->name }}</span>
                                            <span class="badge bg-primary bg-opacity-10 text-primary">{{ $balance->year }}</span>
                                        </td>
                                        <td class="py-3 text-center fw-semibold text-dark">{{ number_format((float) $balance->allocated_days, 1) }}</td>
                                        <td class="py-3 text-center text-warning fw-semibold">{{ number_format((float) $balance->pending_days, 1) }}</td>
                                        <td class="py-3 text-center text-danger fw-semibold">{{ number_format((float) $balance->used_days, 1) }}</td>
                                        <td class="pe-4 py-3 text-end">
                                            <span class="badge bg-success text-white px-3 py-2 rounded-pill fs-6 fw-bold">
                                                {{ number_format($balance->availableDays(), 1) }} Hari
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i class="fas fa-folder-open fs-3 d-block mb-2 text-secondary"></i>
                                            Belum ada data saldo cuti yang tercatat di sistem.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
