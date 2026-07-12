<?php

use App\Models\Financial\StudentInvoice;
use App\Support\Financial\InvoicePublishingService;
use App\Support\Financial\InvoiceStatusService;
use App\Support\Financial\InvoiceAdjustmentService;
use App\Support\Financial\ScholarshipApplicationService;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public StudentInvoice $invoice;

    public array $adjustmentForm = [
        'adjustment_type' => 'discount',
        'amount' => '',
        'reason' => '',
    ];

    public ?int $scholarshipAssignmentId = null;

    public function mount($id): void
    {
        $this->invoice = StudentInvoice::with([
            'studentProfile.user',
            'studentProfile.studyProgram',
            'academicYear',
            'items',
            'adjustments.createdBy',
            'adjustments.source',
            'payments.verifiedBy',
            'installments',
            'installmentRequests.reviewedBy',
            'issuedBy',
            'cancelledBy',
        ])->findOrFail($id);

        app(InvoiceStatusService::class)->refresh($this->invoice);
        $this->reloadInvoice();
    }

    public function applyAdjustment(InvoiceAdjustmentService $adjustmentService): void
    {
        abort_unless(\App\Support\ActivePermission::check('student-invoice.update'), 403);

        $validated = $this->validate([
            'adjustmentForm.adjustment_type' => ['required', Rule::in(array_keys(InvoiceAdjustmentService::TYPES))],
            'adjustmentForm.amount' => ['required', 'numeric', 'min:1'],
            'adjustmentForm.reason' => ['nullable', 'string', 'max:1000'],
        ])['adjustmentForm'];

        try {
            $adjustmentService->apply(
                invoice: $this->invoice,
                type: $validated['adjustment_type'],
                amount: (float) $validated['amount'],
                reason: $validated['reason'] ?: null,
                createdBy: auth()->id()
            );

            $this->adjustmentForm = [
                'adjustment_type' => 'discount',
                'amount' => '',
                'reason' => '',
            ];
            session()->flash('success', 'Penyesuaian / potongan tagihan berhasil diterapkan.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reloadInvoice();
    }

    public function applyScholarship(ScholarshipApplicationService $scholarshipService): void
    {
        abort_unless(\App\Support\ActivePermission::check('student-invoice.update'), 403);

        $validated = $this->validate([
            'scholarshipAssignmentId' => ['required', 'exists:student_scholarships,id'],
        ]);

        try {
            $assignment = \App\Models\Financial\StudentScholarship::with('scholarship')->findOrFail($validated['scholarshipAssignmentId']);
            $scholarshipService->applyAssignmentToInvoice($assignment, $this->invoice, auth()->id());
            $this->scholarshipAssignmentId = null;
            session()->flash('success', 'Alokasi beasiswa berhasil diterapkan ke tagihan ini.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reloadInvoice();
    }

    public function issue(InvoicePublishingService $publishingService): void
    {
        try {
            $this->invoice = $publishingService->issue($this->invoice, auth()->id());
            session()->flash('success', 'Tagihan resmi berhasil diterbitkan.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reloadInvoice();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Detail Tagihan Mahasiswa',
        ]);
    }

    public function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'paid' => 'bg-green-lt text-green',
            'partially_paid' => 'bg-blue-lt text-blue',
            'overdue' => 'bg-red-lt text-red',
            'cancelled' => 'bg-secondary-lt text-secondary',
            'issued' => 'bg-primary-lt text-primary',
            'draft' => 'bg-warning-lt text-warning',
            'verified', 'approved' => 'bg-green-lt text-green',
            'pending', 'submitted' => 'bg-warning-lt text-warning',
            'rejected' => 'bg-red-lt text-red',
            default => 'bg-warning-lt text-warning',
        };
    }

    private function reloadInvoice(): void
    {
        $this->invoice->refresh()->load([
            'studentProfile.user',
            'studentProfile.studyProgram',
            'academicYear',
            'items',
            'adjustments.createdBy',
            'adjustments.source',
            'payments.verifiedBy',
            'installments',
            'installmentRequests.reviewedBy',
            'issuedBy',
            'cancelledBy',
        ]);
    }

    public function adjustmentTypes(): array
    {
        return InvoiceAdjustmentService::TYPES;
    }

    public function availableScholarshipAssignments(): array
    {
        return \App\Models\Financial\StudentScholarship::query()
            ->with('scholarship')
            ->where('student_profile_id', $this->invoice->student_profile_id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('academic_year_id')
                    ->orWhere('academic_year_id', $this->invoice->academic_year_id);
            })
            ->where(function ($query) {
                $query->whereNull('semester')
                    ->orWhere('semester', $this->invoice->semester);
            })
            ->get()
            ->map(fn (\App\Models\Financial\StudentScholarship $assignment) => [
                'id' => $assignment->id,
                'label' => $assignment->scholarship?->name.' - '.($assignment->academicYear?->name ?? 'Semua Tahun').' / '.($assignment->semester ? 'Semester '.$assignment->semester : 'Semua Semester'),
            ])
            ->toArray();
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Rincian & Mutasi Tagihan Mahasiswa"
        description="Informasi invoice #{{ $invoice->invoice_number }} milik {{ $invoice->studentProfile?->user?->name }}, rincian biaya, penyesuaian, beasiswa, dan riwayat pembayaran."
        icon="file-invoice-dollar"
    >
        <div class="d-flex flex-wrap gap-2">
            @if($invoice->isEditable())
                <a href="{{ route('admin.financial.student-invoices.edit', ['id' => $invoice->id]) }}" class="btn btn-sm btn-light text-warning fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                    <i class="fa fa-edit"></i> <span>Edit Tagihan</span>
                </a>
            @endif
            @if($invoice->status === 'draft')
                <button type="button" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2" wire:click="issue">
                    <i class="fa fa-paper-plane"></i> <span>Terbitkan Resmi (Issue)</span>
                </button>
            @endif
            <a href="{{ route('admin.financial.student-invoices.index') }}" class="btn btn-sm btn-outline-light fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
            </a>
        </div>
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
                            <h4 class="card-title fw-bold mb-0 text-dark">{{ $invoice->invoice_number }}</h4>
                            <span class="text-muted small">Tagihan: {{ match($invoice->invoice_type) {
                                'tuition' => 'SPP / Kuliah',
                                'registration' => 'Pendaftaran / Daftar Ulang',
                                'exam' => 'Ujian Akhir',
                                default => ucfirst(str_replace('_', ' ', $invoice->invoice_type))
                            } }}</span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Nama Mahasiswa</div>
                            <div class="fs-5 fw-bold text-dark">{{ $invoice->studentProfile?->user?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">NIM</div>
                            <div class="fs-5 fw-bold text-dark">{{ $invoice->studentProfile?->nim ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Program Studi</div>
                            <div class="fs-6 fw-bold text-dark">{{ $invoice->studentProfile?->studyProgram?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small fw-semibold">Periode Akademik</div>
                            <div class="fs-6 fw-bold text-primary">
                                {{ $invoice->academicYear?->name ?? '-' }}
                                @if($invoice->semester)
                                    • Semester {{ $invoice->semester }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white p-4 border-bottom">
                    <h5 class="card-title fw-bold mb-0">Rincian Komponen Biaya Tagihan (Invoice Items)</h5>
                </div>
                <div class="table-responsive p-3">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 px-3">Keterangan Biaya</th>
                                <th class="py-3 px-3">Jenis Komponen</th>
                                <th class="py-3 px-3 text-end">Nominal (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->items->sortBy('sort_order') as $item)
                                <tr>
                                    <td class="px-3 fw-semibold text-dark">{{ $item->description }}</td>
                                    <td class="px-3">
                                        <span class="badge {{ match($item->item_type) {
                                            'discount' => 'bg-green-lt text-green',
                                            'penalty' => 'bg-red-lt text-red',
                                            'adjustment' => 'bg-info-lt text-info',
                                            default => 'bg-secondary-lt text-secondary'
                                        } }} rounded-pill px-3 py-1">
                                            {{ match($item->item_type) {
                                                'fee' => 'Biaya Pokok (Fee)',
                                                'discount' => 'Potongan (Discount)',
                                                'penalty' => 'Denda (Penalty)',
                                                'adjustment' => 'Penyesuaian',
                                                default => ucfirst($item->item_type)
                                            } }}
                                        </span>
                                    </td>
                                    <td class="px-3 text-end fw-bold text-dark">{{ $this->money($item->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="2" class="px-3 py-3 fs-6">Total Nominal Tagihan</td>
                                <td class="px-3 py-3 text-end fs-5 text-primary">{{ $this->money($invoice->total_amount) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white p-4 d-flex justify-content-between align-items-center border-bottom">
                    <div>
                        <h5 class="card-title fw-bold mb-0">Riwayat Penyesuaian & Potongan (Adjustments)</h5>
                        <small class="text-secondary">Pencatatan diskon pasca-terbit, alokasi beasiswa, penghapusan denda, atau koreksi sistem.</small>
                    </div>
                    <span class="badge bg-light text-dark rounded-pill px-3 py-2">{{ $invoice->adjustments->count() }} catatan</span>
                </div>
                <div class="card-body p-4">
                    @if($invoice->status !== 'draft' && $invoice->status !== 'cancelled')
                        <form wire:submit.prevent="applyAdjustment" class="row g-2 mb-4 p-3 bg-light rounded-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Jenis Penyesuaian</label>
                                <select class="form-select" wire:model="adjustmentForm.adjustment_type">
                                    @foreach($this->adjustmentTypes() as $value => $label)
                                        <option value="{{ $value }}">{{ match($value) {
                                            'discount' => 'Diskon / Potongan Harga',
                                            'scholarship' => 'Alokasi Beasiswa',
                                            'waiver' => 'Pembebasan Biaya (Waiver)',
                                            'penalty' => 'Denda / Sanksi Keterlambatan',
                                            'correction' => 'Koreksi Sistem / Admin',
                                            'write_off' => 'Penghapusan Tagihan (Write-off)',
                                            default => $label
                                        } }}</option>
                                    @endforeach
                                </select>
                                @error('adjustmentForm.adjustment_type') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Nominal Potongan / Koreksi (Rp)</label>
                                <input type="number" min="1" step="1000" class="form-control" wire:model="adjustmentForm.amount" placeholder="0">
                                @error('adjustmentForm.amount') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Alasan / Dasar Hukum</label>
                                <input type="text" class="form-control" wire:model="adjustmentForm.reason" placeholder="Keterangan penyesuaian...">
                                @error('adjustmentForm.reason') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary rounded-pill w-100 fw-semibold shadow-sm">
                                    <i class="fas fa-plus me-1"></i> Terapkan
                                </button>
                            </div>
                        </form>

                        @if(count($this->availableScholarshipAssignments()) > 0)
                            <form wire:submit.prevent="applyScholarship" class="row g-2 mb-4 p-3 bg-success bg-opacity-10 rounded-3">
                                <div class="col-md-9">
                                    <label class="form-label small fw-semibold text-success">Terapkan Beasiswa Mahasiswa yang Tersedia</label>
                                    <select class="form-select" wire:model="scholarshipAssignmentId">
                                        <option value="">Pilih Alokasi Beasiswa Aktif...</option>
                                        @foreach($this->availableScholarshipAssignments() as $assignment)
                                            <option value="{{ $assignment['id'] }}">{{ $assignment['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('scholarshipAssignmentId') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-success rounded-pill w-100 fw-semibold shadow-sm">
                                        <i class="fas fa-graduation-cap me-1"></i> Alokasikan Beasiswa
                                    </button>
                                </div>
                            </form>
                        @endif
                    @else
                        <div class="alert alert-light border rounded-3 mb-3 text-secondary">
                            <i class="fas fa-info-circle me-1"></i> Fitur penyesuaian tagihan (Adjustments) baru dapat digunakan setelah invoice resmi diterbitkan (Issued).
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-2 px-3">Jenis</th>
                                    <th class="py-2 px-3">Keterangan</th>
                                    <th class="py-2 px-3">Oleh Admin</th>
                                    <th class="py-2 px-3 text-end">Nominal (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($invoice->adjustments->sortByDesc('created_at') as $adjustment)
                                    <tr>
                                        <td class="px-3 fw-semibold">{{ str($adjustment->adjustment_type)->replace('_', ' ')->title() }}</td>
                                        <td class="px-3">{{ $adjustment->reason ?: '-' }}</td>
                                        <td class="px-3 text-secondary">{{ $adjustment->createdBy?->name ?? '-' }}</td>
                                        <td class="px-3 text-end fw-bold {{ in_array($adjustment->adjustment_type, ['discount', 'scholarship', 'waiver', 'write_off']) ? 'text-success' : 'text-danger' }}">{{ $this->money($adjustment->amount) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-secondary py-3">Belum ada riwayat penyesuaian tagihan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if($invoice->installments->isNotEmpty())
                <div class="card shadow-sm border-0 rounded-4 mb-4">
                    <div class="card-header bg-white p-4 border-bottom">
                        <h5 class="card-title fw-bold mb-0">Jadwal Relaksasi Cicilan Pembayaran</h5>
                    </div>
                    <div class="table-responsive p-3">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-2 px-3">Cicilan Ke-</th>
                                    <th class="py-2 px-3">Jatuh Tempo</th>
                                    <th class="py-2 px-3 text-end">Total Tagihan</th>
                                    <th class="py-2 px-3 text-end">Sudah Dibayar</th>
                                    <th class="py-2 px-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->installments->sortBy('installment_no') as $installment)
                                    <tr>
                                        <td class="px-3 fw-bold">Cicilan #{{ $installment->installment_no }}</td>
                                        <td class="px-3 fw-medium">{{ $installment->due_date?->format('d M Y') }}</td>
                                        <td class="px-3 text-end fw-semibold">{{ $this->money((float) $installment->amount + (float) $installment->fee_amount) }}</td>
                                        <td class="px-3 text-end text-success fw-bold">{{ $this->money($installment->paid_amount) }}</td>
                                        <td class="px-3">
                                            <span class="badge {{ $this->statusClass($installment->status) }} rounded-pill px-3 py-1">
                                                {{ match($installment->status) {
                                                    'paid' => 'Lunas',
                                                    'partially_paid' => 'Bayar Sebagian',
                                                    'overdue' => 'Menunggak',
                                                    default => str($installment->status)->replace('_', ' ')->title()->toString()
                                                } }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if($invoice->payments->isNotEmpty())
                <div class="card shadow-sm border-0 rounded-4 mb-4">
                    <div class="card-header bg-white p-4 border-bottom">
                        <h5 class="card-title fw-bold mb-0">Riwayat Pembayaran Masuk (Payment History)</h5>
                    </div>
                    <div class="table-responsive p-3">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-2 px-3">Nomor Bukti Setoran</th>
                                    <th class="py-2 px-3 text-end">Nominal Bayar</th>
                                    <th class="py-2 px-3">Status Verifikasi</th>
                                    <th class="py-2 px-3">Waktu Bayar</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->payments->sortByDesc('created_at') as $payment)
                                    <tr>
                                        <td class="px-3">
                                            <a href="{{ route('admin.financial.payments.show', ['id' => $payment->id]) }}" class="fw-bold text-primary">{{ $payment->payment_number }}</a>
                                        </td>
                                        <td class="px-3 text-end fw-bold text-success">{{ $this->money($payment->amount) }}</td>
                                        <td class="px-3">
                                            <span class="badge {{ $this->statusClass($payment->status) }} rounded-pill px-3 py-1">
                                                {{ match($payment->status) {
                                                    'verified' => 'Terverifikasi',
                                                    'pending' => 'Menunggu Review',
                                                    'rejected' => 'Ditolak',
                                                    default => str($payment->status)->replace('_', ' ')->title()->toString()
                                                } }}
                                            </span>
                                        </td>
                                        <td class="px-3 text-secondary">{{ $payment->paid_at?->format('d M Y, H:i') ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white p-4 border-bottom">
                    <h5 class="card-title fw-bold mb-0"><i class="fas fa-receipt text-primary me-2"></i>Ringkasan Pelunasan</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <div class="text-secondary small fw-semibold">Status Tagihan Saat Ini</div>
                        <div class="mt-1">
                            <span class="badge {{ $this->statusClass($invoice->status) }} rounded-pill px-3 py-2 fs-6 fw-bold">
                                {{ match($invoice->status) {
                                    'paid' => 'LUNAS (PAID)',
                                    'partially_paid' => 'BAYAR SEBAGIAN',
                                    'issued' => 'AKTIF (BELUM BAYAR)',
                                    'overdue' => 'JATUH TEMPO / MENUNGGAK',
                                    'cancelled' => 'DIBATALKAN',
                                    'draft' => 'DRAFT (BELUM RESMI)',
                                    default => strtoupper($invoice->status)
                                } }}
                            </span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="text-secondary small fw-semibold">Batas Waktu Pembayaran (Due Date)</div>
                        <div class="fs-6 fw-bold text-dark">{{ $invoice->due_date?->format('d F Y') }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-secondary small fw-semibold">Total Terbayar (Paid Amount)</div>
                        <div class="fs-5 fw-bold text-success">{{ $this->money($invoice->paid_amount) }}</div>
                    </div>
                    <div class="p-3 bg-light rounded-3">
                        <div class="text-secondary small fw-semibold">Sisa Tagihan (Outstanding)</div>
                        <div class="fs-4 fw-bold {{ $invoice->outstanding_amount > 0 ? 'text-danger' : 'text-success' }}">{{ $this->money($invoice->outstanding_amount) }}</div>
                    </div>
                </div>
            </div>

            @if($invoice->status === 'cancelled')
                <div class="alert alert-secondary border-0 rounded-3 shadow-sm p-4">
                    <div class="fw-bold"><i class="fas fa-ban me-1"></i> Tagihan Telah Dibatalkan</div>
                    <div class="small mt-1">Dibatalkan pada {{ $invoice->cancelled_at?->format('d F Y, H:i') }} oleh {{ $invoice->cancelledBy?->name ?? '-' }}.</div>
                </div>
            @endif

            @if($invoice->issued_at)
                <div class="alert alert-info border-0 rounded-3 shadow-sm p-4">
                    <div class="fw-bold"><i class="fas fa-check-circle me-1"></i> Tagihan Resmi Diterbitkan</div>
                    <div class="small mt-1">Diterbitkan pada {{ $invoice->issued_at?->format('d F Y, H:i') }} oleh {{ $invoice->issuedBy?->name ?? 'Sistem Otomatis' }}.</div>
                </div>
            @endif
        </div>
    </div>
</div>
