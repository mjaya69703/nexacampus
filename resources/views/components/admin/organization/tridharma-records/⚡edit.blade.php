<?php

use App\Models\Organization\TridharmaRecord;
use App\Models\User;
use App\Support\Organization\TridharmaRecordService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public TridharmaRecord $record;
    public array $form = [];
    public $attachmentFile = null;

    public function mount(int $id): void
    {
        $this->record = TridharmaRecord::with(['owner', 'attachments'])->findOrFail($id);
        $this->form = [
            'type' => $this->record->type,
            'title' => $this->record->title,
            'scheme' => $this->record->scheme,
            'abstract' => $this->record->abstract,
            'starts_at' => $this->record->starts_at?->format('Y-m-d') ?? '',
            'ends_at' => $this->record->ends_at?->format('Y-m-d') ?? '',
            'status' => $this->record->status,
            'funding_amount' => $this->record->funding_amount,
            'funding_source' => $this->record->funding_source,
            'admin_notes' => $this->record->admin_notes,
        ];
    }

    public function save(TridharmaRecordService $service): void
    {
        $validated = $this->validate($this->rules());

        DB::transaction(function () use ($validated, $service) {
            $this->record->update($this->payload($validated['form']));

            if ($this->attachmentFile) {
                $service->storeAttachment($this->record, $this->attachmentFile, 'evidence', auth()->user());
            }
        });

        session()->flash('success', 'Record Tridharma berhasil diperbarui.');
        $this->redirectRoute('admin.organization.tridharma-records.show', ['id' => $this->record->id]);
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.tridharma-records.show', ['id' => $this->record->id]);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Edit Tridharma',
        ]);
    }

    public function getSelectedUserProperty(): ?User
    {
        return $this->record->owner;
    }

    private function rules(): array
    {
        return [
            'form.type' => ['required', 'in:research,community_service,publication'],
            'form.title' => ['required', 'string', 'max:255'],
            'form.scheme' => ['nullable', 'string', 'max:255'],
            'form.abstract' => ['nullable', 'string'],
            'form.starts_at' => ['nullable', 'date'],
            'form.ends_at' => ['nullable', 'date', 'after_or_equal:form.starts_at'],
            'form.status' => ['required', 'in:draft,approved,active,completed,archived,rejected,in_approval'],
            'form.funding_amount' => ['nullable', 'numeric', 'min:0'],
            'form.funding_source' => ['nullable', 'string', 'max:255'],
            'form.admin_notes' => ['nullable', 'string'],
            'attachmentFile' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp,xlsx', 'max:8192'],
        ];
    }

    private function payload(array $form): array
    {
        $payload = [
            'type' => $form['type'],
            'title' => $form['title'],
            'scheme' => $form['scheme'] ?: null,
            'abstract' => $form['abstract'] ?: null,
            'starts_at' => $form['starts_at'] ?: null,
            'ends_at' => $form['ends_at'] ?: null,
            'status' => $form['status'],
            'funding_amount' => $form['funding_amount'] ?: 0,
            'funding_source' => $form['funding_source'] ?: null,
            'admin_notes' => $form['admin_notes'] ?: null,
            'updated_by' => auth()->id(),
        ];

        if (in_array($form['status'], ['approved', 'active', 'completed'], true) && ! $this->record->approved_at) {
            $payload['approved_by'] = auth()->id();
            $payload['approved_at'] = now();
        }

        if ($form['status'] === 'completed' && ! $this->record->completed_at) {
            $payload['completed_by'] = auth()->id();
            $payload['completed_at'] = now();
        }

        return $payload;
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Edit Data Kegiatan Tri Dharma"
        description="Perbarui rincian kegiatan, judul, skema hibah, atau lampiran dokumen untuk: {{ str($record->title)->limit(60) }}"
        icon="edit"
    >
        <a href="{{ route('admin.organization.tridharma-records.show', $record->id) }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke detail</span>
        </a>
    </x-admin.organization.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-award fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Perubahan Data Tri Dharma</h4>
                            <div class="text-muted small">Sesuaikan judul, status siklus, jumlah pendanaan, atau keterangan admin untuk kegiatan ini.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.organization.tridharma-records._form', ['isEdit' => true])
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
                            <h5 class="fw-bold mb-1">Pedoman Perubahan</h5>
                            <div class="text-muted small">Informasi update portofolio.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Perubahan informasi pada kegiatan ini akan langsung ter-update di halaman detail dan rekap BKD dosen.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Jika status diubah menjadi <strong>Approved / Completed</strong>, sistem otomatis mencantumkan identitas dan waktu verifikasi Anda.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Anda dapat mengunggah berkas lampiran atau bukti evidence baru yang lebih mutakhir jika diperlukan.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Penyimpanan & Lampiran</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Riwayat Berkas</div>
                    <p class="text-muted small mb-0">Lampiran terdahulu tetap tersimpan dan dapat dikelola atau diunduh dari tab lampiran di halaman detail kegiatan.</p>
                </div>
            </div>
        </div>
    </div>
</div>
