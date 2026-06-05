<?php

use App\Models\Organization\EmployeeLeaveBalance;
use App\Models\Organization\EmployeeLeaveRequest;
use App\Support\ActivePermission;
use App\Support\Organization\EmployeeLeaveRequestService;
use Livewire\Component;

new class extends Component
{
    public EmployeeLeaveRequest $request;
    public string $cancelNotes = '';

    public function mount(int $id): void
    {
        $this->request = EmployeeLeaveRequest::with([
            'employeeProfile.user',
            'employeeProfile.primaryWorkUnit',
            'leaveType',
            'approvalRequest.steps',
            'approvalRequest.actions.actor',
        ])->findOrFail($id);
    }

    public function cancelRequest(EmployeeLeaveRequestService $service): void
    {
        if (! ActivePermission::check('employee-leave-request.update')) {
            return;
        }

        $service->cancel($this->request, auth()->id(), $this->cancelNotes ?: 'Dibatalkan dari admin cuti pegawai.');
        session()->flash('success', 'Pengajuan cuti pegawai dibatalkan.');
        $this->redirectRoute('admin.organization.employee-leave-requests.show', ['id' => $this->request->id]);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Detail Cuti Pegawai',
        ]);
    }

    public function getBalanceProperty()
    {
        return EmployeeLeaveBalance::where('employee_profile_id', $this->request->employee_profile_id)
            ->where('employee_leave_type_id', $this->request->employee_leave_type_id)
            ->where('year', $this->request->starts_at->year)
            ->first();
    }

    public function getCanCancelProperty(): bool
    {
        return in_array($this->request->status, ['draft', 'submitted', 'in_approval'], true)
            && ActivePermission::check('employee-leave-request.update');
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

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">{{ $request->request_number }}</h3>
                <small class="text-muted">Detail pengajuan cuti pegawai</small>
            </div>
            <a href="{{ route('admin.organization.employee-leave-requests.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
        <div class="card-body">
            <div class="row row-cards">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="avatar avatar-lg">{{ str($request->employeeProfile?->user?->name ?? 'P')->substr(0, 2)->upper() }}</span>
                        <div>
                            <div class="h3 mb-1">{{ $request->employeeProfile?->user?->name }}</div>
                            <div class="text-secondary">
                                {{ $request->employeeProfile?->employee_number ?: 'Nomor pegawai belum diisi' }}
                                @if ($request->employeeProfile?->primaryWorkUnit)
                                    - {{ $request->employeeProfile->primaryWorkUnit->name }}
                                @endif
                            </div>
                        </div>
                    </div>

                    <dl class="row mb-0">
                        <dt class="col-sm-3">Jenis Cuti</dt>
                        <dd class="col-sm-9">{{ $request->leaveType?->name }}</dd>
                        <dt class="col-sm-3">Periode</dt>
                        <dd class="col-sm-9">{{ $request->starts_at->format('d M Y') }} - {{ $request->ends_at->format('d M Y') }}</dd>
                        <dt class="col-sm-3">Total Hari</dt>
                        <dd class="col-sm-9">{{ number_format((float) $request->total_days, 1) }}</dd>
                        <dt class="col-sm-3">Status</dt>
                        <dd class="col-sm-9">{!! $this->statusBadge($request->status) !!}</dd>
                        <dt class="col-sm-3">Alasan</dt>
                        <dd class="col-sm-9">{{ $request->reason }}</dd>
                        @if ($request->employee_notes)
                            <dt class="col-sm-3">Catatan Pegawai</dt>
                            <dd class="col-sm-9">{{ $request->employee_notes }}</dd>
                        @endif
                        @if ($request->admin_notes)
                            <dt class="col-sm-3">Catatan Admin</dt>
                            <dd class="col-sm-9">{{ $request->admin_notes }}</dd>
                        @endif
                    </dl>
                </div>

                <div class="col-lg-4">
                    <div class="border rounded p-3 mb-3">
                        <div class="text-secondary mb-2">Saldo Tahun {{ $request->starts_at->year }}</div>
                        @if ($this->balance)
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="h2 mb-0">{{ number_format($this->balance->availableDays(), 1) }}</div>
                                    <div class="text-secondary">Tersedia</div>
                                </div>
                                <div class="col-6">
                                    <div class="h2 mb-0">{{ number_format((float) $this->balance->used_days, 1) }}</div>
                                    <div class="text-secondary">Terpakai</div>
                                </div>
                                <div class="col-6">
                                    <div class="fw-semibold">{{ number_format((float) $this->balance->allocated_days, 1) }}</div>
                                    <div class="text-secondary">Jatah</div>
                                </div>
                                <div class="col-6">
                                    <div class="fw-semibold">{{ number_format((float) $this->balance->pending_days, 1) }}</div>
                                    <div class="text-secondary">Pending</div>
                                </div>
                            </div>
                        @else
                            <div class="text-secondary">Saldo belum dibuat untuk jenis cuti ini.</div>
                        @endif
                    </div>

                    @if ($request->approvalRequest)
                        <div class="border rounded p-3 mb-3">
                            <div class="text-secondary mb-2">Approval</div>
                            <div class="fw-semibold mb-2">{{ $request->approvalRequest->subject }}</div>
                            <div class="mb-3">{!! $this->statusBadge($request->approvalRequest->status) !!}</div>
                            <a class="btn btn-outline-primary w-100" href="{{ route('admin.organization.approval-requests.show', ['id' => $request->approvalRequest->id]) }}">
                                <i class="fas fa-clipboard-check me-2"></i>Buka Approval
                            </a>
                        </div>
                    @endif

                    @if ($this->canCancel)
                        <div class="border rounded p-3">
                            <label class="form-label">Catatan Pembatalan</label>
                            <textarea class="form-control mb-3" rows="3" wire:model.defer="cancelNotes"></textarea>
                            <button type="button" class="btn btn-danger w-100" wire:click="cancelRequest">
                                <i class="fas fa-ban me-2"></i>Batalkan Pengajuan
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
