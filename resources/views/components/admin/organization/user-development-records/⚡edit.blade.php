<?php

use App\Models\Organization\UserDevelopmentRecord;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public UserDevelopmentRecord $record;
    public array $form = [];
    public $attachmentFile = null;

    public function mount(int $id): void
    {
        $this->record = UserDevelopmentRecord::with(['user', 'attachments'])->findOrFail($id);
        $this->form = [
            'type' => $this->record->type,
            'title' => $this->record->title,
            'organizer' => $this->record->organizer,
            'credential_number' => $this->record->credential_number,
            'start_date' => $this->record->start_date?->format('Y-m-d') ?? '',
            'end_date' => $this->record->end_date?->format('Y-m-d') ?? '',
            'expires_at' => $this->record->expires_at?->format('Y-m-d') ?? '',
            'cost' => $this->record->cost,
            'description' => $this->record->description,
            'is_verified' => (bool) $this->record->is_verified,
            'verification_notes' => $this->record->verification_notes,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        DB::transaction(function () use ($validated) {
            $this->record->update($this->recordPayload($validated['form']));
            $this->storeAttachment();
        });

        session()->flash('success', 'Riwayat sertifikasi/pelatihan berhasil diperbarui.');
        $this->redirectRoute('admin.organization.user-development-records.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.user-development-records.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Edit Sertifikasi & Pelatihan',
        ]);
    }

    public function getSelectedUserProperty()
    {
        return $this->record->user;
    }

    private function rules(): array
    {
        return [
            'form.type' => ['required', 'string', 'in:certification,training,workshop,seminar,award,license'],
            'form.title' => ['required', 'string', 'max:255'],
            'form.organizer' => ['nullable', 'string', 'max:255'],
            'form.credential_number' => ['nullable', 'string', 'max:255'],
            'form.start_date' => ['required', 'date'],
            'form.end_date' => ['nullable', 'date', 'after_or_equal:form.start_date'],
            'form.expires_at' => ['nullable', 'date', 'after:form.start_date'],
            'form.cost' => ['nullable', 'numeric', 'min:0'],
            'form.description' => ['nullable', 'string'],
            'form.is_verified' => ['boolean'],
            'form.verification_notes' => ['nullable', 'string'],
            'attachmentFile' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    private function recordPayload(array $form): array
    {
        $isVerified = (bool) $form['is_verified'];
        $wasVerified = (bool) $this->record->is_verified;

        return [
            'type' => $form['type'],
            'title' => $form['title'],
            'organizer' => $form['organizer'] ?: null,
            'credential_number' => $form['credential_number'] ?: null,
            'start_date' => $form['start_date'],
            'end_date' => $form['end_date'] ?: null,
            'expires_at' => $form['expires_at'] ?: null,
            'cost' => $form['cost'] === '' ? null : $form['cost'],
            'description' => $form['description'] ?: null,
            'is_verified' => $isVerified,
            'verified_by' => $isVerified ? ($wasVerified ? $this->record->verified_by : auth()->id()) : null,
            'verified_at' => $isVerified ? ($wasVerified ? $this->record->verified_at : now()) : null,
            'verification_notes' => $form['verification_notes'] ?: null,
        ];
    }

    private function storeAttachment(): void
    {
        if (! $this->attachmentFile) {
            return;
        }

        $originalName = $this->attachmentFile->getClientOriginalName();
        $fileSize = $this->attachmentFile->getSize();
        $filename = 'development_'.$this->record->user_id.'_'.time().'.'.$this->attachmentFile->getClientOriginalExtension();
        $path = $this->attachmentFile->storeAs('private/user-developments', $filename);

        $this->record->attachments()->create([
            'document_type' => 'certificate',
            'file_path' => $path,
            'file_name' => $originalName,
            'file_size' => $fileSize,
        ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Edit Sertifikasi & Pelatihan Pegawai"
        description="Perbarui informasi sertifikat, lisensi, atau pelatihan untuk: {{ $record->title }} ({{ $record->user?->name }})"
        icon="edit"
    >
        <a href="{{ route('admin.organization.user-development-records.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.organization.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-certificate fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Perubahan Data Sertifikasi</h4>
                            <div class="text-muted small">Sesuaikan judul, penyelenggara, masa berlaku, atau status verifikasi dokumen berkas sertifikat.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.organization.user-development-records._form', ['isEdit' => true])
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
                            <div class="text-muted small">Informasi update riwayat.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Perubahan informasi sertifikat akan tercatat di dalam riwayat verifikasi.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Anda dapat mengunggah berkas lampiran baru jika berkas sebelumnya kurang jelas atau ada pembaruan dokumen.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Jika verifikasi dicabut atau diperbarui, status pada tabel direktori akan langsung berubah.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Verifikasi</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Asesmen Dokumen</div>
                    <p class="text-muted small mb-0">Hanya dokumen dengan status "Terverifikasi" yang akan dihitung poinnya dalam laporan kinerja pegawai per semester/tahun.</p>
                </div>
            </div>
        </div>
    </div>
</div>
