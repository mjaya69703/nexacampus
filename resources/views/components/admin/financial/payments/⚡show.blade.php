<?php

use App\Models\Financial\Payment;
use App\Support\ActivePermission;
use App\Support\Financial\PaymentProcessingService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

new class extends Component
{
    public Payment $payment;

    public ?string $verificationNotes = null;

    public function mount($id): void
    {
        $this->payment = Payment::with([
            'invoice',
            'studentProfile.user',
            'studentProfile.studyProgram',
            'installment',
            'creditTransactions',
            'submittedBy',
            'verifiedBy',
        ])->findOrFail($id);
    }

    public function verify(PaymentProcessingService $paymentService): void
    {
        abort_unless(ActivePermission::check('payment.update'), 403);

        try {
            $this->payment = $paymentService->verify($this->payment, auth()->id(), $this->verificationNotes);
            session()->flash('success', 'Pembayaran berhasil diverifikasi.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reloadPayment();
    }

    public function reject(PaymentProcessingService $paymentService): void
    {
        abort_unless(ActivePermission::check('payment.update'), 403);

        try {
            $this->payment = $paymentService->reject($this->payment, auth()->id(), $this->verificationNotes);
            session()->flash('success', 'Pembayaran berhasil ditolak.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reloadPayment();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Detail Bukti Pembayaran',
        ]);
    }

    public function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    public function proofUrl(): ?string
    {
        if (! $this->payment->proof_file_path) {
            return null;
        }

        return Storage::disk('public')->url($this->payment->proof_file_path);
    }

    public function proofExtension(): ?string
    {
        if (! $this->payment->proof_file_path) {
            return null;
        }

        return strtolower(pathinfo($this->payment->proof_file_path, PATHINFO_EXTENSION));
    }

    public function isProofImage(): bool
    {
        return in_array($this->proofExtension(), ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    public function isProofPdf(): bool
    {
        return $this->proofExtension() === 'pdf';
    }

    private function reloadPayment(): void
    {
        $this->payment->refresh()->load([
            'invoice',
            'studentProfile.user',
            'studentProfile.studyProgram',
            'installment',
            'creditTransactions',
            'submittedBy',
            'verifiedBy',
        ]);
    }
};
?>

@push('styles')
    <style>
        .proof-preview-shell {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            background: #f8fafc;
        }

        .proof-preview-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e5e7eb;
            background: #fff;
        }

        .proof-preview-image {
            display: block;
            width: 100%;
            max-height: 680px;
            object-fit: contain;
            background: #111827;
        }

        .proof-preview-pdf {
            display: block;
            width: 100%;
            min-height: 680px;
            border: 0;
            background: #fff;
        }
    </style>
@endpush

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Verifikasi Bukti & Rincian Pembayaran"
        description="Tinjau konfirmasi pembayaran #{{ $payment->payment_number }}, periksa lampiran bukti bayar, dan lakukan persetujuan atau penolakan."
        icon="check-double"
    >
        <a href="{{ route('admin.financial.payments.index') }}" class="btn btn-sm btn-light text-secondary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </a>
    </x-admin.financial.header>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fa fa-receipt fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-0 text-dark">Informasi Transaksi: {{ $payment->payment_number }}</h4>
                            <span class="text-muted small">Verifikasi konfirmasi pembayaran dan bukti setoran bank</span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Nama Mahasiswa</div>
                            <div class="fs-5 fw-bold text-dark">{{ $payment->studentProfile?->user?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">NIM</div>
                            <div class="fs-5 fw-bold text-dark">{{ $payment->studentProfile?->nim ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Nomor Invoice Terkait</div>
                            <div class="fs-6 fw-bold text-primary">{{ $payment->invoice?->invoice_number ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Target Alokasi Pembayaran</div>
                            <div class="fs-6 fw-bold text-dark">{{ $payment->installment ? 'Cicilan Ke-'.$payment->installment->installment_no : 'Pelunasan Penuh Invoice' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Nominal Pembayaran</div>
                            <div class="fs-4 fw-bold text-success">{{ $this->money($payment->amount) }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Status Transaksi</div>
                            <div>
                                <span class="badge {{ match($payment->status) {
                                    'verified' => 'bg-green-lt text-green',
                                    'rejected', 'failed' => 'bg-red-lt text-red',
                                    default => 'bg-warning-lt text-warning'
                                } }} rounded-pill px-3 py-2 fs-7 fw-semibold">
                                    {{ match($payment->status) {
                                        'pending' => 'Menunggu Verifikasi',
                                        'verified' => 'Terverifikasi (Verified)',
                                        'rejected' => 'Ditolak (Rejected)',
                                        'failed' => 'Gagal / Dibatalkan',
                                        default => str($payment->status)->title()->toString()
                                    } }}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Metode Pembayaran</div>
                            <div class="fs-6 fw-medium text-dark">{{ match($payment->payment_method) {
                                'bank_transfer' => 'Transfer Bank Manual',
                                'virtual_account' => 'Virtual Account (VA)',
                                'credit_balance' => 'Saldo Deposit Mahasiswa',
                                'cash' => 'Tunai / Kasir',
                                default => str($payment->payment_method)->replace('_', ' ')->title()->toString()
                            } }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Nomor Referensi / Bukti Transfer</div>
                            <div class="fs-6 fw-medium text-dark">{{ $payment->transaction_reference ?: '-' }}</div>
                        </div>
                        @if($payment->notes)
                            <div class="col-12">
                                <div class="text-secondary small fw-semibold mb-1">Catatan dari Mahasiswa</div>
                                <div class="alert alert-light border rounded-3 p-3 mb-0 text-dark fw-medium">{{ $payment->notes }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white p-4 border-bottom">
                    <h5 class="card-title fw-bold mb-0">Lampiran Bukti Pembayaran</h5>
                </div>
                <div class="card-body p-4">
                    @if($this->proofUrl())
                        <div class="proof-preview-shell shadow-sm">
                            <div class="proof-preview-toolbar">
                                <div>
                                    <div class="fw-bold fs-6 text-dark">File Bukti Setoran / Transfer</div>
                                    <div class="text-secondary small">Format: {{ strtoupper($this->proofExtension() ?? 'FILE') }}</div>
                                </div>
                                <a href="{{ $this->proofUrl() }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold shadow-sm">
                                    <i class="fas fa-up-right-from-square me-1"></i> Buka di Tab Baru
                                </a>
                            </div>

                            @if($this->isProofImage())
                                <a href="{{ $this->proofUrl() }}" target="_blank" title="Klik untuk memperbesar">
                                    <img src="{{ $this->proofUrl() }}" alt="Bukti pembayaran {{ $payment->payment_number }}" class="proof-preview-image">
                                </a>
                            @elseif($this->isProofPdf())
                                <iframe src="{{ $this->proofUrl() }}#toolbar=1&navpanes=0" class="proof-preview-pdf" title="Bukti pembayaran {{ $payment->payment_number }}"></iframe>
                            @else
                                <div class="p-5 text-center">
                                    <i class="fas fa-file-lines text-secondary mb-3" style="font-size: 3rem;"></i>
                                    <div class="fw-bold fs-6">Preview tidak tersedia untuk format file ini.</div>
                                    <div class="text-secondary small mt-1">Gunakan tombol 'Buka di Tab Baru' untuk mengunduh dan melihat file.</div>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="alert alert-warning border-0 rounded-3 text-center py-4 mb-0">
                            <i class="fas fa-file-excel fs-3 text-warning mb-2"></i>
                            <div class="fw-bold">Tidak ada bukti pembayaran yang dilampirkan.</div>
                            <div class="small text-secondary mt-1">Mahasiswa mungkin membayar melalui channel otomatis atau kasir.</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white p-4 border-bottom">
                    <h5 class="card-title fw-bold mb-0"><i class="fas fa-check-to-slot text-primary me-2"></i>Tindakan Verifikasi</h5>
                </div>
                <div class="card-body p-4">
                    @if($payment->status === 'pending')
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Catatan Verifikasi / Alasan Penolakan</label>
                            <textarea wire:model="verificationNotes" class="form-control" rows="4" placeholder="Tuliskan keterangan verifikasi, atau alasan penolakan jika bukti transfer tidak valid/nominal tidak sesuai..."></textarea>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success rounded-pill py-2 fw-semibold shadow-sm" wire:click="verify">
                                <i class="fas fa-check-circle me-2"></i> Verifikasi Pembayaran (Approve)
                            </button>
                            <button type="button" class="btn btn-danger rounded-pill py-2 fw-semibold shadow-sm" wire:click="reject">
                                <i class="fas fa-times-circle me-2"></i> Tolak Pembayaran (Reject)
                            </button>
                        </div>
                    @else
                        @if($payment->status === 'verified')
                            <a href="{{ route('admin.financial.payments.receipt', ['payment' => $payment->id]) }}" target="_blank" class="btn btn-primary rounded-pill w-100 py-2 fw-semibold shadow-sm mb-4">
                                <i class="fas fa-file-pdf me-2"></i> Buka Kuitansi Resmi (PDF)
                            </a>
                        @endif
                        <div class="mb-3">
                            <div class="text-secondary small fw-semibold">Diverifikasi Oleh</div>
                            <div class="fs-6 fw-bold text-dark">{{ $payment->verifiedBy?->name ?? '-' }}</div>
                        </div>
                        <div class="mb-3">
                            <div class="text-secondary small fw-semibold">Waktu Verifikasi</div>
                            <div class="fs-6 fw-bold text-dark">{{ $payment->verified_at?->format('d F Y, H:i') ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-secondary small fw-semibold mb-1">Catatan Petugas Keuangan</div>
                            <div class="p-3 bg-light rounded-3 text-dark">{{ $payment->verification_notes ?: '-' }}</div>
                        </div>
                        @if($payment->creditTransactions->isNotEmpty())
                            <div class="alert alert-info border-0 rounded-3 mt-4 mb-0">
                                <div class="fw-bold mb-1"><i class="fas fa-wallet me-1"></i> Kelebihan Saldo Masuk ke Deposit</div>
                                @foreach($payment->creditTransactions as $transaction)
                                    <div class="small">
                                        {{ str($transaction->transaction_type)->replace('_', ' ')->title() }}:
                                        <strong>{{ $this->money($transaction->amount) }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
