<?php

use App\Models\Organization\EmployeeLeaveBalance;
use App\Models\Organization\EmployeeLeaveRequest;
use App\Models\Organization\EmployeeLeaveType;
use App\Support\Organization\EmployeeLeaveRequestService;
use Livewire\Component;

new class extends Component
{
    public array $form = [];
    public bool $showForm = false;

    public function mount(): void
    {
        abort_unless($this->employeeProfile()?->is_active, 403);

        $this->form = [
            'employee_leave_type_id' => '',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->toDateString(),
            'reason' => '',
            'employee_notes' => '',
        ];
    }

    public function openForm(): void
    {
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function submit(EmployeeLeaveRequestService $service): void
    {
        $validated = $this->validate([
            'form.employee_leave_type_id' => ['required', 'exists:employee_leave_types,id'],
            'form.starts_at' => ['required', 'date'],
            'form.ends_at' => ['required', 'date', 'after_or_equal:form.starts_at'],
            'form.reason' => ['required', 'string', 'max:2000'],
            'form.employee_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $type = EmployeeLeaveType::findOrFail($validated['form']['employee_leave_type_id']);
        $request = $service->create($this->employeeProfile(), $type, $validated['form'], auth()->id());
        $service->submit($request, auth()->id());

        $this->showForm = false;
        $this->form['reason'] = '';
        $this->form['employee_notes'] = '';
        session()->flash('success', 'Pengajuan cuti berhasil dikirim.');
    }

    public function render()
    {
        return $this->view([
            'employee' => $this->employeeProfile(),
            'leaveTypes' => EmployeeLeaveType::where('is_active', true)->orderBy('name')->get(),
            'balances' => $this->balances(),
            'requests' => $this->requests(),
        ])->layout('layouts.app', [
            'menus' => 'Kepegawaian Saya',
            'pages' => 'Cuti Saya',
        ]);
    }

    private function employeeProfile()
    {
        return auth()->user()?->employeeProfile()->with('primaryWorkUnit')->first();
    }

    private function balances()
    {
        return EmployeeLeaveBalance::query()
            ->with('leaveType')
            ->where('employee_profile_id', $this->employeeProfile()->id)
            ->where('year', now()->year)
            ->get();
    }

    private function requests()
    {
        return EmployeeLeaveRequest::query()
            ->with(['leaveType', 'approvalRequest'])
            ->where('employee_profile_id', $this->employeeProfile()->id)
            ->latest()
            ->limit(15)
            ->get();
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'approved' => 'bg-success',
            'rejected' => 'bg-danger',
            'cancelled' => 'bg-secondary',
            'in_approval', 'submitted' => 'bg-warning text-dark',
            default => 'bg-muted',
        };

        return '<span class="badge '.$class.'">'.str($status)->replace('_', ' ')->title().'</span>';
    }
};
?>

<div>
    <x-alert />

    <div class="row row-cards">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Saldo Cuti</h3>
                    <button type="button" class="btn btn-primary" wire:click="openForm">
                        <i class="fas fa-plus me-2"></i>Ajukan
                    </button>
                </div>
                <div class="card-body">
                    @forelse ($balances as $balance)
                        <div class="border rounded p-3 mb-2">
                            <div class="fw-semibold">{{ $balance->leaveType?->name }}</div>
                            <div class="row mt-2">
                                <div class="col-6">
                                    <div class="h3 mb-0">{{ number_format($balance->availableDays(), 1) }}</div>
                                    <div class="text-secondary">Tersedia</div>
                                </div>
                                <div class="col-6">
                                    <div class="h3 mb-0">{{ number_format((float) $balance->used_days, 1) }}</div>
                                    <div class="text-secondary">Terpakai</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-secondary">Saldo akan dibuat otomatis saat pengajuan cuti pertama.</div>
                    @endforelse
                </div>
            </div>

            @if ($showForm)
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Pengajuan Baru</h3>
                    </div>
                    <div class="card-body">
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
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Mulai</label>
                                <input type="date" class="form-control" wire:model.defer="form.starts_at">
                                @error('form.starts_at') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Selesai</label>
                                <input type="date" class="form-control" wire:model.defer="form.ends_at">
                                @error('form.ends_at') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Alasan</label>
                            <textarea class="form-control" rows="3" wire:model.defer="form.reason"></textarea>
                            @error('form.reason') <span class="text-danger">{{ $message }}</span> @enderror
                            @error('balance') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan</label>
                            <textarea class="form-control" rows="2" wire:model.defer="form.employee_notes"></textarea>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-primary" wire:click="submit">
                                <i class="fas fa-paper-plane me-2"></i>Kirim
                            </button>
                            <button type="button" class="btn btn-secondary" wire:click="cancelForm">Batal</button>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Riwayat Pengajuan</h3>
                </div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead>
                            <tr>
                                <th>Nomor</th>
                                <th>Jenis</th>
                                <th>Periode</th>
                                <th>Hari</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($requests as $request)
                                <tr>
                                    <td>{{ $request->request_number }}</td>
                                    <td>{{ $request->leaveType?->name }}</td>
                                    <td>{{ $request->starts_at->format('d M Y') }} - {{ $request->ends_at->format('d M Y') }}</td>
                                    <td>{{ number_format((float) $request->total_days, 1) }}</td>
                                    <td>{!! $this->statusBadge($request->status) !!}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-4">Belum ada pengajuan cuti.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
