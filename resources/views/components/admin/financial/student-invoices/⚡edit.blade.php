<?php

use App\Models\Academic\AcademicYear;
use App\Models\Financial\StudentInvoice;
use App\Support\Financial\InvoiceStatusService;
use Livewire\Component;

new class extends Component
{
    public StudentInvoice $invoice;
    public array $academicYears = [];
    public array $form = [];
    public array $items = [];

    public function mount($id): void
    {
        $this->invoice = StudentInvoice::with(['items', 'studentProfile.user', 'studentProfile.studyProgram'])->findOrFail($id);

        abort_unless($this->invoice->isEditable(), 403);

        $this->academicYears = AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'code'])
            ->map(fn (AcademicYear $year) => ['id' => $year->id, 'label' => $year->name.' ('.$year->code.')'])
            ->toArray();

        $this->form = [
            'academic_year_id' => $this->invoice->academic_year_id ?: '',
            'semester' => $this->invoice->semester,
            'invoice_type' => $this->invoice->invoice_type,
            'due_date' => $this->invoice->due_date?->format('Y-m-d'),
            'notes' => $this->invoice->notes,
        ];

        $this->items = $this->invoice->items()
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($item) => [
                'item_type' => in_array($item->item_type, ['discount', 'adjustment', 'penalty'], true) ? $item->item_type : 'fee',
                'description' => $item->description,
                'amount' => abs((float) $item->amount),
            ])
            ->toArray();

        if (empty($this->items)) {
            $this->items[] = ['item_type' => 'fee', 'description' => '', 'amount' => 0];
        }
    }

    public function addItem(): void
    {
        $this->items[] = ['item_type' => 'fee', 'description' => '', 'amount' => 0];
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) <= 1) {
            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(): void
    {
        abort_unless($this->invoice->fresh()->isEditable(), 403);

        $validated = $this->validate([
            'form.academic_year_id' => 'nullable|exists:academic_years,id',
            'form.semester' => 'nullable|integer|min:1|max:14',
            'form.invoice_type' => 'required|in:tuition,custom,admission,registration,leave,transfer,graduation,exam,library_fine,certificate,other',
            'form.due_date' => 'required|date',
            'form.notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.item_type' => 'required|in:fee,discount,adjustment,penalty',
            'items.*.description' => 'required|string|max:255',
            'items.*.amount' => 'required|numeric|min:0.01',
        ]);

        $this->invoice->update([
            'academic_year_id' => $validated['form']['academic_year_id'] ?: null,
            'semester' => $validated['form']['semester'] ?: null,
            'invoice_type' => $validated['form']['invoice_type'],
            'due_date' => $validated['form']['due_date'],
            'notes' => $validated['form']['notes'] ?: null,
            'updated_by' => auth()->id(),
        ]);

        $this->invoice->items()->delete();

        foreach (array_values($validated['items']) as $index => $item) {
            $amount = (float) $item['amount'];

            if ($item['item_type'] === 'discount') {
                $amount = -abs($amount);
            }

            $this->invoice->items()->create([
                'item_type' => $item['item_type'],
                'description' => $item['description'],
                'amount' => $amount,
                'sort_order' => $index + 1,
            ]);
        }

        app(InvoiceStatusService::class)->refresh($this->invoice);

        session()->flash('success', 'Tagihan berhasil diperbarui.');
        $this->redirectRoute('admin.financial.student-invoices.show', ['id' => $this->invoice->id]);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Edit Tagihan Mahasiswa',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Edit Tagihan Mahasiswa"
        description="Perbarui tanggal jatuh tempo, keterangan, atau rincian komponen tagihan #{{ $invoice->invoice_number }} milik {{ $invoice->studentProfile?->user?->name }}"
        icon="edit"
    >
        <a href="{{ route('admin.financial.student-invoices.show', ['id' => $invoice->id]) }}" class="btn btn-sm btn-light text-secondary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Detail</span>
        </a>
    </x-admin.financial.header>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fa fa-file-invoice-dollar fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-0 text-dark">Formulir Edit Tagihan (Invoice)</h4>
                            <span class="text-muted small">{{ $invoice->invoice_number }} • {{ $invoice->studentProfile?->nim }} - {{ $invoice->studentProfile?->user?->name }}</span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-3 p-md-4">
                    <form wire:submit.prevent="save">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kategori Tagihan</label>
                            <select class="form-select" wire:model="form.invoice_type">
                                <option value="tuition">SPP / Kuliah (Tuition)</option>
                                <option value="custom">Tagihan Kustom (Custom)</option>
                                <option value="admission">Pendaftaran Mahasiswa Baru</option>
                                <option value="registration">Daftar Ulang / Registrasi</option>
                                <option value="leave">Cuti Akademik (Leave)</option>
                                <option value="transfer">Pindah Jalur / Program (Transfer)</option>
                                <option value="graduation">Wisuda / Kelulusan (Graduation)</option>
                                <option value="exam">Ujian Akhir / Skripsi (Exam)</option>
                                <option value="library_fine">Denda Perpustakaan</option>
                                <option value="certificate">Legalisir / Sertifikat</option>
                                <option value="other">Lainnya (Other)</option>
                            </select>
                            @error('form.invoice_type') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tahun Akademik</label>
                            <select class="form-select" wire:model="form.academic_year_id">
                                <option value="">Tanpa Tahun Akademik</option>
                                @foreach ($academicYears as $academicYear)
                                    <option value="{{ $academicYear['id'] }}">{{ $academicYear['label'] }}</option>
                                @endforeach
                            </select>
                            @error('form.academic_year_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Semester</label>
                            <input type="number" min="1" max="14" class="form-control" wire:model="form.semester">
                            @error('form.semester') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal Jatuh Tempo <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" wire:model="form.due_date">
                            @error('form.due_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Catatan Internal / Referensi</label>
                            <textarea class="form-control" rows="2" wire:model="form.notes"></textarea>
                            @error('form.notes') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="border rounded-4 p-4 my-4 bg-light bg-opacity-50">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">Rincian Komponen Tagihan (Items)</h5>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" wire:click="addItem">
                                <i class="fas fa-plus me-1"></i> Tambah Komponen
                            </button>
                        </div>

                        @foreach ($items as $index => $item)
                            <div class="row align-items-end g-2 mb-2" wire:key="edit-invoice-item-{{ $index }}">
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">Jenis Item</label>
                                    <select class="form-select" wire:model="items.{{ $index }}.item_type">
                                        <option value="fee">Biaya Pokok (Fee)</option>
                                        <option value="discount">Potongan (Discount)</option>
                                        <option value="adjustment">Penyesuaian (Adjustment)</option>
                                        <option value="penalty">Denda (Penalty)</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-semibold">Keterangan Biaya</label>
                                    <input type="text" class="form-control" wire:model="items.{{ $index }}.description">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">Nominal (Rp)</label>
                                    <input type="number" min="0" step="1000" class="form-control" wire:model="items.{{ $index }}.amount">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-outline-danger w-100" wire:click="removeItem({{ $index }})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="{{ route('admin.financial.student-invoices.show', ['id' => $invoice->id]) }}" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold">Batal</a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="alert alert-info border-0 rounded-3 shadow-sm p-4">
            <h6 class="fw-bold mb-2"><i class="fas fa-lock me-2"></i>Aturan Pengubahan Invoice</h6>
            <p class="small mb-0">Tagihan hanya dapat diedit secara langsung selama statusnya masih berstatus <strong>Draft</strong> atau <strong>Belum Ada Pembayaran</strong> yang masuk. Apabila tagihan telah dibayar sebagian atau terbayar penuh, maka penyesuaian nominal wajib dilakukan melalui fitur <strong>Adjustments (Potongan / Koreksi)</strong> demi menjaga audit trail pembukuan.</p>
        </div>
    </div>
</div>
