<?php

use App\Models\Financial\InvoiceInstallmentRequest;
use App\Support\ActivePermission;
use App\Support\Financial\InstallmentApprovalService;
use Livewire\Component;

new class extends Component
{
    public InvoiceInstallmentRequest $requestModel;

    public ?string $financeNotes = null;

    public function mount($id): void
    {
        $this->requestModel = InvoiceInstallmentRequest::with([
            'invoice',
            'studentProfile.user',
            'studentProfile.studyProgram',
            'reviewedBy',
            'installments',
            'approvalRequest.steps.actedBy',
        ])->findOrFail($id);
    }

    public function approve(InstallmentApprovalService $approvalService): void
    {
        abort_unless(ActivePermission::check('installment-request.update'), 403);

        try {
            $this->requestModel = $approvalService->approve($this->requestModel, auth()->id(), $this->financeNotes);
            session()->flash('success', 'Pengajuan cicilan berhasil disetujui.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reloadRequest();
    }

    public function reject(InstallmentApprovalService $approvalService): void
    {
        abort_unless(ActivePermission::check('installment-request.update'), 403);

        try {
            $this->requestModel = $approvalService->reject($this->requestModel, auth()->id(), $this->financeNotes);
            session()->flash('success', 'Pengajuan cicilan berhasil ditolak.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reloadRequest();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Detail Pengajuan Cicilan',
        ]);
    }

    public function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    private function reloadRequest(): void
    {
        $this->requestModel->refresh()->load([
            'invoice',
            'studentProfile.user',
            'studentProfile.studyProgram',
            'reviewedBy',
            'installments',
            'approvalRequest.steps.actedBy',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Tinjauan & Persetujuan Cicilan Tagihan"
        description="Review permohonan relaksasi pembayaran cicilan mahasiswa beserta rincian simulasi angsuran dan alasan pengajuan."
        icon="clipboard-check"
    >
        <a href="{{ route('admin.financial.installment-requests.index') }}" class="btn btn-sm btn-light text-secondary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </a>
    </x-admin.financial.header>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fa fa-file-invoice-dollar fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-0 text-dark">Informasi Pengajuan: {{ $requestModel->invoice?->invoice_number }}</h4>
                            <span class="text-muted small">Permohonan relaksasi cicilan sebanyak {{ $requestModel->requested_tenor }} kali pembayaran</span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Nama Mahasiswa</div>
                            <div class="fs-5 fw-bold text-dark">{{ $requestModel->studentProfile?->user?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">NIM</div>
                            <div class="fs-5 fw-bold text-dark">{{ $requestModel->studentProfile?->nim ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Tenor Pengajuan</div>
                            <div class="fs-5 fw-bold text-primary">{{ $requestModel->requested_tenor }} Kali Cicilan</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Status Pengajuan</div>
                            <div>
                                <span class="badge {{ match($requestModel->status) {
                                    'approved' => 'bg-success text-white',
                                    'rejected' => 'bg-danger text-white',
                                    default => 'bg-warning text-dark'
                                } }} rounded-pill px-3 py-2 fs-7 fw-semibold">
                                    {{ match($requestModel->status) {
                                        'submitted', 'in_approval' => 'Menunggu Persetujuan',
                                        'approved' => 'Disetujui (Approved)',
                                        'rejected' => 'Ditolak (Rejected)',
                                        default => str($requestModel->status)->title()->toString()
                                    } }}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Biaya Administrasi Cicilan (Fee)</div>
                            <div class="fs-6 fw-bold text-dark">{{ $this->money($requestModel->requested_fee_amount) }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Total Tagihan Setelah Cicilan</div>
                            <div class="fs-5 fw-bold text-success">{{ $this->money($requestModel->simulated_total_amount) }}</div>
                        </div>
                        @if($requestModel->student_reason)
                            <div class="col-12">
                                <div class="text-secondary small fw-semibold mb-1">Alasan / Permohonan Mahasiswa</div>
                                <div class="alert alert-light border rounded-3 p-3 mb-0 text-dark fw-medium">{{ $requestModel->student_reason }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-3 p-md-4">
                    <h5 class="card-title fw-bold mb-0 text-dark"><i class="fa fa-calendar-alt text-primary me-2"></i>Simulasi Jadwal Pembayaran Cicilan</h5>
                </div>
                <div class="table-responsive p-3">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 px-3">Cicilan Ke-</th>
                                <th class="py-3 px-3">Tanggal Jatuh Tempo</th>
                                <th class="py-3 px-3 text-end">Nominal Pokok</th>
                                <th class="py-3 px-3 text-end">Biaya Admin</th>
                                <th class="py-3 px-3 text-end">Total Bayar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($requestModel->simulation_snapshot['installments'] ?? []) as $row)
                                <tr>
                                    <td class="px-3 fw-bold">Cicilan #{{ $row['installment_no'] }}</td>
                                    <td class="px-3 fw-medium">{{ \Illuminate\Support\Carbon::parse($row['due_date'])->format('d M Y') }}</td>
                                    <td class="px-3 text-end">{{ $this->money($row['amount']) }}</td>
                                    <td class="px-3 text-end text-secondary">{{ $this->money($row['fee_amount'] ?? 0) }}</td>
                                    <td class="px-3 text-end fw-bold text-primary">{{ $this->money($row['total_amount']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-4">Data simulasi tidak tersedia.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-3 p-md-4">
                    <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-clipboard-check text-primary me-2"></i>Keputusan Persetujuan</h5>
                </div>
                <div class="card-body p-3 p-md-4">
                    @if(in_array($requestModel->status, ['submitted', 'in_approval'], true))
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Catatan Keputusan Finance</label>
                            <textarea wire:model="financeNotes" class="form-control" rows="4" placeholder="Berikan catatan, arahan, atau alasan penolakan/persetujuan pengajuan ini..."></textarea>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success rounded-pill py-2 fw-semibold shadow-sm" wire:click="approve">
                                <i class="fas fa-check-circle me-2"></i> Setujui Cicilan (Approve)
                            </button>
                            <button type="button" class="btn btn-danger rounded-pill py-2 fw-semibold shadow-sm" wire:click="reject">
                                <i class="fas fa-times-circle me-2"></i> Tolak Pengajuan (Reject)
                            </button>
                        </div>
                    @else
                        <div class="mb-3">
                            <div class="text-secondary small fw-semibold">Ditinjau & Diputuskan Oleh</div>
                            <div class="fs-6 fw-bold text-dark">{{ $requestModel->reviewedBy?->name ?? '-' }}</div>
                        </div>
                        <div class="mb-3">
                            <div class="text-secondary small fw-semibold">Waktu Keputusan</div>
                            <div class="fs-6 fw-bold text-dark">{{ $requestModel->reviewed_at?->format('d F Y, H:i') ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-secondary small fw-semibold mb-1">Catatan Petugas (Finance Notes)</div>
                            <div class="p-3 bg-light rounded-3 text-dark">{{ $requestModel->finance_notes ?: '-' }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
