<?php

use App\Models\Organization\UserDevelopmentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public array $form = [];
    public ?int $selectedUserId = null;
    public string $userSearch = '';
    public $attachmentFile = null;

    public function mount(): void
    {
        $this->form = $this->emptyForm();
    }

    public function selectUser(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->userSearch = '';
    }

    public function clearUser(): void
    {
        $this->selectedUserId = null;
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        DB::transaction(function () use ($validated) {
            $record = UserDevelopmentRecord::create($this->recordPayload($validated['form']) + [
                'user_id' => $validated['selectedUserId'],
            ]);

            $this->storeAttachment($record);
        });

        session()->flash('success', 'Riwayat sertifikasi/pelatihan berhasil dibuat.');
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
            'pages' => 'Tambah Sertifikasi & Pelatihan',
        ]);
    }

    public function getSelectedUserProperty(): ?User
    {
        return $this->selectedUserId ? User::find($this->selectedUserId) : null;
    }

    public function getSearchableUsersProperty()
    {
        return User::query()
            ->where('is_active', true)
            ->when(filled($this->userSearch), function ($query) {
                $search = '%'.$this->userSearch.'%';
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('username', 'like', $search)
                        ->orWhere('code', 'like', $search)
                        ->orWhere('identity_number', 'like', $search);
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(12)
            ->get(['id', 'first_name', 'last_name', 'email', 'username', 'code', 'identity_number']);
    }

    private function emptyForm(): array
    {
        return [
            'type' => 'certification',
            'title' => '',
            'organizer' => '',
            'credential_number' => '',
            'start_date' => '',
            'end_date' => '',
            'expires_at' => '',
            'cost' => '',
            'description' => '',
            'is_verified' => false,
            'verification_notes' => '',
        ];
    }

    private function rules(): array
    {
        return [
            'selectedUserId' => ['required', 'integer', 'exists:users,id'],
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
            'verified_by' => $isVerified ? auth()->id() : null,
            'verified_at' => $isVerified ? now() : null,
            'verification_notes' => $form['verification_notes'] ?: null,
        ];
    }

    private function storeAttachment(UserDevelopmentRecord $record): void
    {
        if (! $this->attachmentFile) {
            return;
        }

        $originalName = $this->attachmentFile->getClientOriginalName();
        $fileSize = $this->attachmentFile->getSize();
        $filename = 'development_'.$record->user_id.'_'.time().'.'.$this->attachmentFile->getClientOriginalExtension();
        $path = $this->attachmentFile->storeAs('private/user-developments', $filename);

        $record->attachments()->create([
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
        title="Tambah Sertifikasi & Pelatihan"
        description="Catat riwayat keikutsertaan sertifikasi profesi, pelatihan, workshop, atau seminar bagi dosen/pegawai."
        icon="plus-circle"
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
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Riwayat Pengembangan Kompetensi</h4>
                            <div class="text-muted small">Pilih pegawai bersangkutan, jenis kegiatan, nomor sertifikat, waktu pelaksanaan, serta unggah bukti sertifikat.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.organization.user-development-records._form', ['isEdit' => false])
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
                            <h5 class="fw-bold mb-1">Pedoman Pengembangan SDM</h5>
                            <div class="text-muted small">Informasi pencatatan sertifikat.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pastikan <strong>Tanggal Pelaksanaan</strong> dan <strong>Masa Berlaku</strong> sesuai dengan yang tertera pada dokumen resmi.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Unggah <strong>Berkas Sertifikat/Piagam</strong> dengan format PDF, JPG, atau PNG (Maks. 5MB) untuk memudahkan proses verifikasi asesor.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Jika berkas sudah diverifikasi keasliannya oleh tim SDM, aktifkan tombol switch <strong>Sudah Diverifikasi Keabsahannya</strong>.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Kredensial & Lisensi</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Pentingnya Nomor Kredensial</div>
                    <p class="text-muted small mb-0">Nomor kredensial atau SK digunakan untuk proses validasi eksternal saat pelaporan kinerja atau kepangkatan pegawai.</p>
                </div>
            </div>
        </div>
    </div>
</div>
