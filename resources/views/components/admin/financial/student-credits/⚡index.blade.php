<?php

use App\Models\Financial\StudentCreditBalance;
use App\Models\Financial\StudentCreditTransaction;
use App\Support\Financial\StudentCreditService;
use App\Models\Academic\StudentProfile;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];
    public array $students = [];
    public string $studentSearch = '';
    public array $selectedStudentProfileIds = [];
    public array $form = [
        'transaction_type' => 'refund',
        'amount' => '',
        'notes' => '',
    ];

    public function mount(): void
    {
        $this->reloadStats();
    }

    public function updatedStudentSearch(): void
    {
        $this->reloadStats();
    }

    public function record(StudentCreditService $creditService): void
    {
        $validated = $this->validate([
            'selectedStudentProfileIds' => ['required', 'array', 'min:1'],
            'selectedStudentProfileIds.*' => ['exists:student_profiles,id'],
            'form.transaction_type' => ['required', Rule::in(['refund', 'manual_adjustment'])],
            'form.amount' => ['required', 'numeric', 'min:1'],
            'form.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $created = 0;
        $errors = [];
        try {
            foreach ($this->selectedStudentProfileIds as $studentProfileId) {
                $studentProfile = StudentProfile::findOrFail($studentProfileId);

                try {
                    if ($validated['form']['transaction_type'] === 'refund') {
                        $creditService->refund($studentProfile, (float) $validated['form']['amount'], auth()->id(), $validated['form']['notes'] ?: null);
                    } else {
                        $creditService->manualAdjustment($studentProfile, (float) $validated['form']['amount'], auth()->id(), $validated['form']['notes'] ?: null);
                    }

                    $created++;
                } catch (\Throwable $exception) {
                    $errors[] = ($studentProfile->nim ?: $studentProfile->id).': '.$exception->getMessage();
                }
            }

            $this->form = [
                'transaction_type' => 'refund',
                'amount' => '',
                'notes' => '',
            ];
            $this->selectedStudentProfileIds = [];
            $this->reloadStats();
            $this->dispatch('pg:eventRefresh-studentCreditTable');

            if ($created > 0) {
                session()->flash('success', $created.' transaksi deposit berhasil dicatat.');
            }

            if (! empty($errors)) {
                session()->flash('error', implode(' ', array_slice($errors, 0, 5)));
            }
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function reloadStats(): void
    {
        $this->stats = [
            'students' => StudentCreditBalance::where('balance', '>', 0)->count(),
            'balance' => (float) StudentCreditBalance::sum('balance'),
            'overpayment' => (float) StudentCreditTransaction::where('transaction_type', 'overpayment')->sum('amount'),
            'refund' => abs((float) StudentCreditTransaction::where('transaction_type', 'refund')->sum('amount')),
        ];

        $creditBalances = StudentCreditBalance::query()
            ->pluck('balance', 'student_profile_id');

        $this->students = StudentProfile::query()
            ->with('user')
            ->where('is_active', true)
            ->when($this->studentSearch !== '', function ($query) {
                $search = '%'.$this->studentSearch.'%';

                $query->where(function ($query) use ($search) {
                    $query->where('nim', 'like', $search)
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhere('email', 'like', $search));
                });
            })
            ->orderBy('nim')
            ->limit(25)
            ->get()
            ->map(fn (StudentProfile $student) => [
                'id' => $student->id,
                'label' => ($student->nim ?: '-').' - '.$student->user?->name.' (Saldo: '.$this->money($creditBalances[$student->id] ?? 0).')',
            ])
            ->toArray();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Saldo Deposit & Kredit Mahasiswa',
        ]);
    }

    public function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Saldo Deposit & Kredit Mahasiswa"
        description="Kelola kelebihan pembayaran, saldo deposit, serta pengembalian dana (refund) mahasiswa untuk pemotongan otomatis tagihan berikutnya."
        icon="wallet"
    >
        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-users fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Pemilik Saldo</div>
                        <div class="fw-bold">{{ number_format($stats['students']) }} Mahasiswa</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-wallet fs-6 text-success"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Saldo Kredit</div>
                        <div class="fw-bold">{{ $this->money($stats['balance']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-hand-holding-dollar fs-6 text-info"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Overpayment</div>
                        <div class="fw-bold">{{ $this->money($stats['overpayment']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-rotate-left fs-6 text-warning"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Refund</div>
                        <div class="fw-bold">{{ $this->money($stats['refund']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.financial.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Saldo & Mutasi Deposit</h4>
                <div class="text-muted small">Akumulasi kelebihan bayar, saldo tersimpan, serta pengembalian ke rekening mahasiswa.</div>
            </div>
            <div class="text-muted small">
                Gunakan formulir di bawah jika ingin mencatat penambahan saldo manual atau pencairan refund.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Mahasiswa Punya Saldo</span>
                            <i class="fa fa-users fs-4 text-primary opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($stats['students']) }}</div>
                        <div class="text-muted small mt-2">Dapat dipotong otomatis</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Total Saldo Tersimpan</span>
                            <i class="fa fa-wallet fs-4 text-success opacity-75"></i>
                        </div>
                        <div class="fs-5 fw-bold text-success lh-1">{{ $this->money($stats['balance']) }}</div>
                        <div class="text-muted small mt-2">Saldo aktif saat ini</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Total Kelebihan Bayar</span>
                            <i class="fa fa-hand-holding-dollar fs-4 text-info opacity-75"></i>
                        </div>
                        <div class="fs-5 fw-bold text-info lh-1">{{ $this->money($stats['overpayment']) }}</div>
                        <div class="text-muted small mt-2">Akumulasi historis overpayment</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Total Pengembalian</span>
                            <i class="fa fa-rotate-left fs-4 text-warning opacity-75"></i>
                        </div>
                        <div class="fs-5 fw-bold text-warning lh-1">{{ $this->money($stats['refund']) }}</div>
                        <div class="text-muted small mt-2">Pencairan dana yang telah diproses</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-plus-circle fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Catat Transaksi Deposit / Pencairan Saldo (Refund)</h4>
                    <span class="text-muted small">Kelola penambahan saldo kredit manual atau pencairan dana pengembalian ke rekening mahasiswa.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4 bg-light bg-opacity-50">
            <form wire:submit.prevent="record" class="row g-3 align-items-start">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Pilih Mahasiswa <span class="text-danger">*</span></label>
                    <input type="text" class="form-control mb-2 rounded-pill px-3" wire:model.live.debounce.300ms="studentSearch" placeholder="Cari berdasarkan NIM, Nama, atau Email...">
                    <div class="border rounded-3 p-3 bg-white" style="max-height: 180px; overflow-y: auto;">
                        @forelse($students as $student)
                            <label class="form-check mb-2 d-block" wire:key="credit-student-{{ $student['id'] }}">
                                <input class="form-check-input" type="checkbox" wire:model="selectedStudentProfileIds" value="{{ $student['id'] }}">
                                <span class="form-check-label fw-medium">{{ $student['label'] }}</span>
                            </label>
                        @empty
                            <div class="text-secondary small text-center py-2">Mahasiswa tidak ditemukan.</div>
                        @endforelse
                    </div>
                    <div class="small text-secondary mt-1">
                        <i class="fas fa-check-circle text-primary me-1"></i> {{ count($selectedStudentProfileIds) }} mahasiswa terpilih.
                    </div>
                    @error('selectedStudentProfileIds') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    @error('selectedStudentProfileIds.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Jenis Transaksi <span class="text-danger">*</span></label>
                    <select class="form-select @error('form.transaction_type') is-invalid @enderror" wire:model="form.transaction_type">
                        <option value="refund">Pencairan / Refund</option>
                        <option value="manual_adjustment">Tambah Saldo Manual</option>
                    </select>
                    @error('form.transaction_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Nominal (Rp) <span class="text-danger">*</span></label>
                    <input type="number" min="1" step="1" class="form-control @error('form.amount') is-invalid @enderror" wire:model="form.amount" placeholder="0">
                    @error('form.amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Keterangan / Referensi</label>
                    <input type="text" class="form-control @error('form.notes') is-invalid @enderror" wire:model="form.notes" placeholder="Contoh: Refund ke Rek BCA 123...">
                    @error('form.notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 d-flex justify-content-end pt-2 border-top">
                    <button class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm" type="submit">
                        <i class="fas fa-save me-1"></i> Simpan Transaksi Saldo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Riwayat Transaksi Saldo Deposit & Kredit</h4>
                    <span class="text-muted small">Daftar lengkap mutasi saldo deposit mahasiswa, kelebihan pembayaran, potongan otomatis pada invoice, dan pencairan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:financial.student-credit-table />
        </div>
    </div>
</div>
