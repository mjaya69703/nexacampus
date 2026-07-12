<?php

use App\Models\Organization\ApprovalTemplate;
use App\Support\Organization\ApprovalEngine;
use Livewire\Component;

new class extends Component
{
    public array $form = [];

    public function mount(): void
    {
        $this->form = [
            'approval_template_id' => '',
            'subject' => '',
            'reference' => '',
            'notes' => '',
        ];
    }

    public function save(ApprovalEngine $engine): void
    {
        $validated = $this->validate([
            'form.approval_template_id' => ['required', 'exists:approval_templates,id'],
            'form.subject' => ['required', 'string', 'max:255'],
            'form.reference' => ['nullable', 'string', 'max:255'],
            'form.notes' => ['nullable', 'string'],
        ]);

        $template = ApprovalTemplate::findOrFail($validated['form']['approval_template_id']);

        $request = $engine->submitFromTemplate(
            template: $template,
            subject: $validated['form']['subject'],
            requester: auth()->user(),
            reference: $validated['form']['reference'] ?: null,
            notes: $validated['form']['notes'] ?: null,
            createdBy: auth()->id(),
        );

        session()->flash('success', 'Request approval berhasil dibuat.');
        $this->redirectRoute('admin.organization.approval-requests.show', ['id' => $request->id]);
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.approval-requests.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Buat Request Approval',
        ]);
    }

    public function getTemplatesProperty()
    {
        return ApprovalTemplate::where('is_active', true)->orderBy('module')->orderBy('name')->get(['id', 'name', 'code', 'module']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Buat Permohonan Approval"
        description="Ajukan permohonan baru secara manual berdasarkan template alur persetujuan yang telah dikonfigurasi di sistem."
        icon="plus-circle"
    >
        <a href="{{ route('admin.organization.approval-requests.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.organization.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-file-signature fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Permohonan</h4>
                            <div class="text-muted small">Lengkapi informasi permohonan dengan teliti sebelum mengirimkan untuk proses verifikasi.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <label class="form-label fw-bold text-dark required">Template Approval</label>
                            <select class="form-select rounded-3 @error('form.approval_template_id') is-invalid @enderror" wire:model.defer="form.approval_template_id">
                                <option value="">-- Pilih Template --</option>
                                @foreach ($this->templates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }} (Kode: {{ $template->code }})</option>
                                @endforeach
                            </select>
                            @error('form.approval_template_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label fw-bold text-dark">Referensi / Nomor Dokumen</label>
                            <input type="text" class="form-control rounded-3 @error('form.reference') is-invalid @enderror" wire:model.defer="form.reference" placeholder="Contoh: SURAT/2026/001 atau Kode Internal">
                            @error('form.reference') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold text-dark required">Judul Permohonan (Subject)</label>
                            <input type="text" class="form-control rounded-3 @error('form.subject') is-invalid @enderror" wire:model.defer="form.subject" placeholder="Tuliskan subjek permohonan dengan singkat dan jelas">
                            @error('form.subject') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">Catatan Tambahan / Alasan Permohonan</label>
                            <textarea class="form-control rounded-3 @error('form.notes') is-invalid @enderror" rows="4" wire:model.defer="form.notes" placeholder="Tuliskan keterangan detail pendukung pengajuan permohonan ini..."></textarea>
                            @error('form.notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light bg-opacity-50 border-top p-4 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-medium d-inline-flex align-items-center gap-2" wire:click="cancel">
                        <i class="fa fa-times"></i> <span>Batal</span>
                    </button>
                    <button type="button" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium d-inline-flex align-items-center gap-2" wire:click="save">
                        <i class="fa fa-paper-plane"></i> <span>Kirim Permohonan</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-circle-info"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Panduan Pengajuan</h5>
                            <div class="text-muted small">Alur proses persetujuan.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Template Approval</strong>: Pilih alur verifikasi yang sesuai dengan jenis pengajuan Anda (misal: Cuti, Reimbursement, atau Perubahan Data).</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Referensi Dokumen</strong>: Cantumkan nomor surat, kode transaksi, atau referensi internal agar memudahkan verifikator melakukan pengecekan.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Notifikasi Otomatis</strong>: Setelah dikirim, sistem akan langsung mengarahkan Anda ke halaman status dan mengirim notifikasi ke verifikator langkah pertama.</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
