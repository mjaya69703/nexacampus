<?php

use App\Models\Financial\FinancialHold;
use App\Support\ActivePermission;
use App\Support\Financial\FinancialClearanceService;
use Livewire\Component;

new class extends Component
{
    public FinancialHold $hold;

    public ?string $releaseNotes = null;

    public ?string $waivedUntil = null;

    public function mount($id): void
    {
        $this->hold = FinancialHold::with([
            'studentProfile.user',
            'studentProfile.studyProgram',
            'invoice',
            'policy',
            'releasedBy',
        ])->findOrFail($id);

        $this->waivedUntil = now()->addDays(7)->toDateString();
    }

    public function release(FinancialClearanceService $clearanceService): void
    {
        abort_unless(ActivePermission::check('financial-hold.update'), 403);

        $clearanceService->release($this->hold, auth()->id(), $this->releaseNotes);
        session()->flash('success', 'Blokir akademik berhasil dilepas (released).');
        $this->reloadHold();
    }

    public function waive(FinancialClearanceService $clearanceService): void
    {
        abort_unless(ActivePermission::check('financial-hold.update'), 403);

        $this->validate([
            'waivedUntil' => ['required', 'date', 'after_or_equal:today'],
            'releaseNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $clearanceService->waive($this->hold, $this->waivedUntil, auth()->id(), $this->releaseNotes);
        session()->flash('success', 'Dispensasi sementara berhasil diberikan.');
        $this->reloadHold();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Detail Pemblokiran Akses',
        ]);
    }

    public function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    private function reloadHold(): void
    {
        $this->hold->refresh()->load([
            'studentProfile.user',
            'studentProfile.studyProgram',
            'invoice',
            'policy',
            'releasedBy',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Detail & Penanganan Blokir Akses"
        description="Tinjau rincian tunggakan mahasiswa dan lakukan penanganan dispensasi sementara atau pelepasan blokir akademik."
        icon="lock"
    >
        <a href="{{ route('admin.financial.financial-holds.index') }}" class="btn btn-sm btn-light text-secondary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </a>
    </x-admin.financial.header>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fa fa-user-lock fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-0 text-dark">Informasi Blokir: {{ match($hold->hold_type) {
                                'registration' => 'Pendaftaran Ulang / KRS',
                                'study_plan' => 'Rencana Studi',
                                'exam_card' => 'Kartu Ujian Akhir',
                                default => str($hold->hold_type)->replace('_', ' ')->title()->toString()
                            } }}</h4>
                            <span class="text-muted small">{{ $hold->studentProfile?->user?->name ?? '-' }} • NIM: {{ $hold->studentProfile?->nim ?? '-' }}</span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Nama Mahasiswa</div>
                            <div class="fs-5 fw-bold text-dark">{{ $hold->studentProfile?->user?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Program Studi</div>
                            <div class="fs-5 fw-bold text-dark">{{ $hold->studentProfile?->studyProgram?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Nomor Invoice Terkait</div>
                            <div class="fs-6 fw-bold text-primary">{{ $hold->invoice?->invoice_number ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Jenis Tagihan</div>
                            <div class="fs-6 fw-bold text-dark">{{ match($hold->invoice?->invoice_type) {
                                'tuition' => 'SPP / Uang Kuliah',
                                'registration' => 'Biaya Pendaftaran',
                                'exam' => 'Biaya Ujian Akhir',
                                default => str($hold->invoice?->invoice_type ?? '-')->replace('_', ' ')->title()->toString()
                            } }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Sisa Tunggakan (Outstanding)</div>
                            <div class="fs-5 fw-bold text-danger">{{ $this->money($hold->invoice?->outstanding_amount) }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Status Saat Ini</div>
                            <div>
                                <span class="badge {{ $hold->isBlocking() ? 'bg-danger text-white' : ($hold->status === 'waived' ? 'bg-info text-white' : 'bg-success text-white') }} rounded-pill px-3 py-2 fs-7 fw-semibold">
                                    {{ $hold->isBlocking() ? 'Aktif (Terblokir)' : match($hold->status) {
                                        'waived' => 'Dispensasi Sementara',
                                        'released' => 'Dilepas (Bebas Blokir)',
                                        default => str($hold->status)->title()->toString()
                                    } }}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Waktu Pemicu Terdeteksi</div>
                            <div class="fs-6 fw-medium text-dark">{{ $hold->starts_at?->format('d F Y, H:i') ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Waktu Blokir Berlaku</div>
                            <div class="fs-6 fw-medium text-dark">{{ $hold->blocked_at?->format('d F Y, H:i') ?? '-' }}</div>
                        </div>
                        <div class="col-12">
                            <div class="text-secondary small fw-semibold mb-1">Alasan Penahanan (Reason)</div>
                            <div class="alert alert-light border rounded-3 p-3 mb-0 text-dark fw-medium">{{ $hold->reason ?: '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            @if($hold->status !== 'active')
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white border-bottom p-3 p-md-4">
                        <h5 class="card-title fw-bold mb-0 text-dark"><i class="fa fa-history text-primary me-2"></i>Riwayat Penyelesaian / Dispensasi</h5>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-secondary small fw-semibold">Diproses Oleh</div>
                                <div class="fs-6 fw-bold text-dark">{{ $hold->releasedBy?->name ?? '-' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-secondary small fw-semibold">Waktu Proses</div>
                                <div class="fs-6 fw-bold text-dark">{{ $hold->released_at?->format('d F Y, H:i') ?? '-' }}</div>
                            </div>
                            @if($hold->status === 'waived')
                                <div class="col-md-6">
                                    <div class="text-secondary small fw-semibold">Berlaku Sampai Dengan</div>
                                    <div class="fs-6 fw-bold text-info">{{ $hold->waived_until?->format('d F Y') ?? '-' }}</div>
                                </div>
                            @endif
                            <div class="col-12">
                                <div class="text-secondary small fw-semibold mb-1">Catatan Petugas (Notes)</div>
                                <div class="p-3 bg-light rounded-3 text-dark">{{ $hold->release_notes ?: '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-3 p-md-4">
                    <h5 class="card-title fw-bold mb-0 text-dark"><i class="fas fa-gavel text-primary me-2"></i>Tindakan Administrasi</h5>
                </div>
                <div class="card-body p-3 p-md-4">
                    @if($hold->status === 'active')
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Catatan Keputusan / Alasan</label>
                            <textarea wire:model="releaseNotes" class="form-control" rows="4" placeholder="Tuliskan keterangan surat penangguhan atau alasan pelepasan blokir..."></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Tenggat Dispensasi (Waive Until)</label>
                            <input type="date" wire:model="waivedUntil" class="form-control">
                            @error('waivedUntil') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-info rounded-pill py-2 fw-semibold shadow-sm text-white" wire:click="waive">
                                <i class="fas fa-clock me-2"></i> Berikan Dispensasi Sementara
                            </button>
                            <button type="button" class="btn btn-success rounded-pill py-2 fw-semibold shadow-sm" wire:click="release">
                                <i class="fas fa-check-circle me-2"></i> Lepas Blokir Sepenuhnya
                            </button>
                        </div>
                    @else
                        <div class="alert alert-success border-0 rounded-3 text-center mb-0 p-4">
                            <i class="fas fa-check-circle fs-2 text-success mb-2"></i>
                            <div class="fw-bold fs-6">Blokir Telah Ditangani</div>
                            <div class="small text-secondary mt-1">Status saat ini: {{ match($hold->status) {
                                'waived' => 'Dispensasi Sementara',
                                'released' => 'Dilepas (Released)',
                                default => str($hold->status)->title()->toString()
                            } }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
