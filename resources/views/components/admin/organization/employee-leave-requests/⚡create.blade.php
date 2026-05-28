<?php

use App\Models\Organization\EmployeeLeaveType;
use App\Models\Organization\EmployeeProfile;
use App\Support\Organization\EmployeeLeaveRequestService;
use Livewire\Component;

new class extends Component
{
    public array $form = [];
    public ?int $selectedEmployeeProfileId = null;
    public string $employeeSearch = '';

    public function mount(): void
    {
        $this->form = [
            'employee_leave_type_id' => '',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->toDateString(),
            'reason' => '',
            'employee_notes' => '',
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

    public function save(EmployeeLeaveRequestService $service): void
    {
        $validated = $this->validate([
            'selectedEmployeeProfileId' => ['required', 'exists:employee_profiles,id'],
            'form.employee_leave_type_id' => ['required', 'exists:employee_leave_types,id'],
            'form.starts_at' => ['required', 'date'],
            'form.ends_at' => ['required', 'date', 'after_or_equal:form.starts_at'],
            'form.reason' => ['required', 'string', 'max:2000'],
            'form.employee_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $employee = EmployeeProfile::findOrFail($validated['selectedEmployeeProfileId']);
        $type = EmployeeLeaveType::findOrFail($validated['form']['employee_leave_type_id']);

        $request = $service->create($employee, $type, $validated['form'], auth()->id());
        $request = $service->submit($request, auth()->id());

        session()->flash('success', 'Pengajuan cuti pegawai berhasil dibuat.');
        $this->redirectRoute('admin.organization.employee-leave-requests.show', ['id' => $request->id]);
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.employee-leave-requests.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Ajukan Cuti Pegawai',
        ]);
    }

    public function getSelectedEmployeeProperty()
    {
        return $this->selectedEmployeeProfileId ? EmployeeProfile::with(['user', 'primaryWorkUnit'])->find($this->selectedEmployeeProfileId) : null;
    }

    public function getSearchableEmployeesProperty()
    {
        return EmployeeProfile::query()
            ->with(['user', 'primaryWorkUnit'])
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

    public function getLeaveTypesProperty()
    {
        return EmployeeLeaveType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'default_days_per_year']);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Ajukan Cuti Pegawai</h3>
            <a href="{{ route('admin.organization.employee-leave-requests.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
        <div class="card-body">
            <div class="row row-cards">
                <div class="col-12">
                    <label class="form-label required">Pegawai</label>
                    @if ($this->selectedEmployee)
                        <div class="border rounded p-3 d-flex justify-content-between align-items-center bg-body">
                            <div class="d-flex align-items-center gap-3">
                                <span class="avatar avatar-md">{{ str($this->selectedEmployee->user?->name ?? 'P')->substr(0, 2)->upper() }}</span>
                                <div>
                                    <div class="fw-bold">{{ $this->selectedEmployee->user?->name }}</div>
                                    <div class="text-secondary">
                                        {{ $this->selectedEmployee->employee_number ?: 'Nomor pegawai belum diisi' }}
                                        @if ($this->selectedEmployee->primaryWorkUnit)
                                            - {{ $this->selectedEmployee->primaryWorkUnit->name }}
                                        @endif
                                    </div>
                                </div>
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
                                    <span class="text-secondary">
                                        {{ $employee->employee_number ?: 'Nomor pegawai belum diisi' }}
                                        {{ $employee->user?->email ? ' - '.$employee->user?->email : '' }}
                                    </span>
                                </button>
                            @empty
                                <div class="list-group-item text-secondary">Tidak ada pegawai aktif yang cocok.</div>
                            @endforelse
                        </div>
                    @endif
                    @error('selectedEmployeeProfileId') <span class="text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="col-lg-4">
                    <label class="form-label required">Jenis Cuti</label>
                    <select class="form-select" wire:model.defer="form.employee_leave_type_id">
                        <option value="">Pilih jenis cuti</option>
                        @foreach ($this->leaveTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}{{ (float) $type->default_days_per_year > 0 ? ' - '.$type->default_days_per_year.' hari/tahun' : '' }}</option>
                        @endforeach
                    </select>
                    @error('form.employee_leave_type_id') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="col-lg-4">
                    <label class="form-label required">Mulai</label>
                    <input type="date" class="form-control" wire:model.defer="form.starts_at">
                    @error('form.starts_at') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="col-lg-4">
                    <label class="form-label required">Selesai</label>
                    <input type="date" class="form-control" wire:model.defer="form.ends_at">
                    @error('form.ends_at') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="col-12">
                    <label class="form-label required">Alasan</label>
                    <textarea class="form-control" rows="4" wire:model.defer="form.reason" placeholder="Alasan pengajuan cuti"></textarea>
                    @error('form.reason') <span class="text-danger">{{ $message }}</span> @enderror
                    @error('balance') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan Pegawai</label>
                    <textarea class="form-control" rows="3" wire:model.defer="form.employee_notes"></textarea>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-primary" wire:click="save">
                        <i class="fas fa-paper-plane me-2"></i>Simpan & Submit
                    </button>
                    <button type="button" class="btn btn-secondary" wire:click="cancel">Batal</button>
                </div>
            </div>
        </div>
    </div>
</div>
