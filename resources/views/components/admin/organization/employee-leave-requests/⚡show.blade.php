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

    public function statusBadge(string $status): string
    {
        return match ($status) {
            'approved' => 'bg-success text-white',
            'rejected' => 'bg-danger text-white',
            'cancelled' => 'bg-secondary text-white',
            'in_approval', 'submitted' => 'bg-warning text-dark',
            default => 'bg-secondary text-white',
        };
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Pengajuan Cuti: {{ $request->request_number }}"
        description="Detail permohonan {{ $request->leaveType?->name ?? 'Cuti' }} oleh {{ $request->employeeProfile?->user?->name ?? 'Pegawai' }} &bull; Diajukan: {{ $request->created_at->format('d M Y H:i') }}"
        icon="user-clock"
    >
        <a href="{{ route('admin.organization.employee-leave-requests.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-info-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Permohonan</div>
                        <span class="badge {{ $this->statusBadge($request->status) }} rounded-pill px-3 py-1 fs-6 mt-1">{{ str($request->status)->replace('_', ' ')->title() }}</span>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-day fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Durasi</div>
                        <div class="fw-bold">{{ number_format((float) $request->total_days, 1) }} Hari</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-range fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Periode Pelaksanaan</div>
                        <div class="fw-bold">{{ $request->starts_at->format('d M') }} - {{ $request->ends_at->format('d M Y') }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.organization.header>

    <div class="row g-4 align-items-start mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-file-alt fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Rincian Permohonan Cuti</h4>
                            <div class="text-muted small">Informasi lengkap pemohon, rentang waktu, serta alasan pengajuan cuti.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-4 mb-4 border">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-5 shadow-sm" style="width: 54px; height: 54px;">
                            {{ str($request->employeeProfile?->user?->name ?? 'P')->substr(0, 2)->upper() }}
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-1 fs-6">{{ $request->employeeProfile?->user?->name }}</h5>
                            <span class="small text-muted d-block">
                                <i class="fas fa-id-badge me-1"></i> {{ $request->employeeProfile?->employee_number ?: 'Nomor pegawai belum diisi' }}
                                @if ($request->employeeProfile?->primaryWorkUnit)
                                    &bull; <i class="fas fa-building me-1"></i> {{ $request->employeeProfile->primaryWorkUnit->name }}
                                @endif
                            </span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-0">
                            <tbody>
                                <tr class="border-bottom">
                                    <th class="ps-0 py-3 text-secondary w-35">Jenis Cuti</th>
                                    <td class="pe-0 py-3 fw-bold text-dark fs-6"><span class="badge bg-light text-primary border px-3 py-1 fs-6">{{ $request->leaveType?->name }}</span></td>
                                </tr>
                                <tr class="border-bottom">
                                    <th class="ps-0 py-3 text-secondary">Tanggal Pelaksanaan</th>
                                    <td class="pe-0 py-3 fw-bold text-dark">{{ $request->starts_at->format('l, d M Y') }} &bull; <i class="fas fa-long-arrow-alt-right text-primary mx-1"></i> &bull; {{ $request->ends_at->format('l, d M Y') }}</td>
                                </tr>
                                <tr class="border-bottom">
                                    <th class="ps-0 py-3 text-secondary">Total Durasi</th>
                                    <td class="pe-0 py-3 text-dark"><span class="badge bg-info bg-opacity-10 text-info px-3 py-1 fw-bold fs-6">{{ number_format((float) $request->total_days, 1) }} Hari Kerja</span></td>
                                </tr>
                                <tr class="border-bottom">
                                    <th class="ps-0 py-3 text-secondary">Alasan Pengajuan</th>
                                    <td class="pe-0 py-3 text-dark fw-medium">{{ $request->reason }}</td>
                                </tr>
                                @if ($request->employee_notes)
                                    <tr class="border-bottom">
                                        <th class="ps-0 py-3 text-secondary">Catatan Pegawai</th>
                                        <td class="pe-0 py-3 text-muted">{{ $request->employee_notes }}</td>
                                    </tr>
                                @endif
                                @if ($request->admin_notes)
                                    <tr>
                                        <th class="ps-0 py-3 text-secondary">Catatan Admin / Approver</th>
                                        <td class="pe-0 py-3 text-muted">{{ $request->admin_notes }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 d-flex flex-column gap-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fa fa-wallet fs-5"></i>
                        </div>
                        <div>
                            <h5 class="card-title fw-bold mb-0 text-dark fs-6">Saldo Cuti Tahun {{ $request->starts_at->year }}</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @if ($this->balance)
                        <div class="row g-3 text-center">
                            <div class="col-6">
                                <div class="p-3 bg-success bg-opacity-10 rounded-4 border border-success border-opacity-25">
                                    <h3 class="fw-bold text-success mb-0">{{ number_format($this->balance->availableDays(), 1) }}</h3>
                                    <span class="small text-muted fw-medium">Sisa Tersedia</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 bg-danger bg-opacity-10 rounded-4 border border-danger border-opacity-25">
                                    <h3 class="fw-bold text-danger mb-0">{{ number_format((float) $this->balance->used_days, 1) }}</h3>
                                    <span class="small text-muted fw-medium">Hari Terpakai</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded-3">
                                    <span class="fw-bold text-dark d-block fs-6">{{ number_format((float) $this->balance->allocated_days, 1) }}</span>
                                    <span class="small text-muted">Jatah Kuota</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded-3">
                                    <span class="fw-bold text-warning d-block fs-6">{{ number_format((float) $this->balance->pending_days, 1) }}</span>
                                    <span class="small text-muted">Hari Pending</span>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-4 bg-light rounded-3">
                            <i class="fas fa-exclamation-circle text-warning fs-3 mb-2"></i>
                            <div class="small text-muted">Saldo cuti belum dibuat atau diatur untuk jenis cuti ini pada tahun {{ $request->starts_at->year }}.</div>
                        </div>
                    @endif
                </div>
            </div>

            @if ($request->approvalRequest)
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="fa fa-tasks fs-5"></i>
                            </div>
                            <div>
                                <h5 class="card-title fw-bold mb-0 text-dark fs-6">Status Persetujuan</h5>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <div class="small text-muted mb-1">Judul Approval:</div>
                            <div class="fw-bold text-dark mb-2 fs-6">{{ $request->approvalRequest->subject }}</div>
                            <span class="badge {{ $this->statusBadge($request->approvalRequest->status) }} rounded-pill px-3 py-1">{{ str($request->approvalRequest->status)->replace('_', ' ')->title() }}</span>
                        </div>
                        <a class="btn btn-outline-primary rounded-pill shadow-sm w-100 fw-medium" href="{{ route('admin.organization.approval-requests.show', ['id' => $request->approvalRequest->id]) }}">
                            <i class="fas fa-clipboard-check me-2"></i> Buka Alur Persetujuan
                        </a>
                    </div>
                </div>
            @endif

            @if ($this->canCancel)
                <div class="card border border-danger border-opacity-25 shadow-sm rounded-4 overflow-hidden bg-danger bg-opacity-10">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-danger bg-opacity-10 text-danger rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="fa fa-exclamation-triangle fs-5"></i>
                            </div>
                            <div>
                                <h5 class="card-title fw-bold mb-0 text-danger fs-6">Pembatalan Pengajuan</h5>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <label class="form-label small fw-bold text-dark">Catatan / Alasan Pembatalan</label>
                        <textarea class="form-control rounded-3 mb-3" rows="3" wire:model.defer="cancelNotes" placeholder="Tuliskan alasan pengajuan cuti ini dibatalkan..."></textarea>
                        <button type="button" class="btn btn-danger rounded-pill shadow-sm w-100 fw-medium" wire:click="cancelRequest" wire:confirm="Yakin ingin membatalkan pengajuan cuti ini?">
                            <i class="fas fa-ban me-2"></i> Batalkan Pengajuan
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
