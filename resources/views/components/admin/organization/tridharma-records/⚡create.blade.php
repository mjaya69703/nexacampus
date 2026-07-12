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

    public function save(TridharmaRecordService $service): void
    {
        $validated = $this->validate($this->rules());

        DB::transaction(function () use ($validated, $service) {
            $owner = User::with(['lecturerProfile', 'employeeProfile'])->findOrFail($validated['selectedUserId']);
            $record = TridharmaRecord::create($this->payload($validated['form'], $owner));

            if ($this->attachmentFile) {
                $service->storeAttachment($record, $this->attachmentFile, 'proposal', auth()->user());
            }
        });

        session()->flash('success', 'Record Tridharma berhasil dibuat.');
        $this->redirectRoute('admin.organization.tridharma-records.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.tridharma-records.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Tambah Tridharma',
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
            ->where(function ($query) {
                $query->whereHas('lecturerProfile')
                    ->orWhereHas('employeeProfile', fn ($query) => $query->where('is_active', true));
            })
            ->when(filled($this->userSearch), function ($query) {
                $search = '%'.$this->userSearch.'%';
                $query->where(fn ($query) => $query
                    ->where('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('username', 'like', $search)
                    ->orWhere('code', 'like', $search));
            })
            ->orderBy('first_name')
            ->limit(12)
            ->get(['id', 'first_name', 'last_name', 'email', 'username', 'code']);
    }

    private function emptyForm(): array
    {
        return [
            'type' => 'research',
            'title' => '',
            'scheme' => '',
            'abstract' => '',
            'starts_at' => '',
            'ends_at' => '',
            'status' => 'draft',
            'funding_amount' => 0,
            'funding_source' => '',
            'admin_notes' => '',
        ];
    }

    private function rules(): array
    {
        return [
            'selectedUserId' => ['required', 'integer', 'exists:users,id'],
            'form.type' => ['required', 'in:research,community_service,publication'],
            'form.title' => ['required', 'string', 'max:255'],
            'form.scheme' => ['nullable', 'string', 'max:255'],
            'form.abstract' => ['nullable', 'string'],
            'form.starts_at' => ['nullable', 'date'],
            'form.ends_at' => ['nullable', 'date', 'after_or_equal:form.starts_at'],
            'form.status' => ['required', 'in:draft,approved,active,completed,archived'],
            'form.funding_amount' => ['nullable', 'numeric', 'min:0'],
            'form.funding_source' => ['nullable', 'string', 'max:255'],
            'form.admin_notes' => ['nullable', 'string'],
            'attachmentFile' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp,xlsx', 'max:8192'],
        ];
    }

    private function payload(array $form, User $owner): array
    {
        return [
            'user_id' => $owner->id,
            'lecturer_profile_id' => $owner->lecturerProfile?->id,
            'employee_profile_id' => $owner->employeeProfile?->id,
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
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'approved_by' => in_array($form['status'], ['approved', 'active', 'completed'], true) ? auth()->id() : null,
            'approved_at' => in_array($form['status'], ['approved', 'active', 'completed'], true) ? now() : null,
            'completed_by' => $form['status'] === 'completed' ? auth()->id() : null,
            'completed_at' => $form['status'] === 'completed' ? now() : null,
        ];
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Tambah Kegiatan Tri Dharma Baru"
        description="Daftarkan portofolio penelitian, pengabdian masyarakat, atau publikasi baru beserta lampiran proposal dan rincian anggarannya."
        icon="plus-circle"
    >
        <a href="{{ route('admin.organization.tridharma-records.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
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
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Rekor Tri Dharma Dosen / Pegawai</h4>
                            <div class="text-muted small">Pilih dosen/pegawai penanggung jawab (owner), jenis kegiatan, rentang waktu, dan lampiran berkas pendukung.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.organization.tridharma-records._form', ['isEdit' => false])
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
                            <h5 class="fw-bold mb-1">Pedoman Tri Dharma</h5>
                            <div class="text-muted small">Panduan pencatatan portofolio.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pilih <strong>Dosen / Pegawai</strong> pemilik kegiatan Tri Dharma ini untuk menautkan portofolio ke profil BKD mereka.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pastikan <strong>Skema / Hibah</strong> dan <strong>Anggaran Dana</strong> dituliskan sesuai dengan kontrak atau proposal resmi.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Unggah <strong>Proposal / Berkas</strong> (Maks. 8MB) dalam format PDF atau DOCX sebagai bukti autentikasi kegiatan.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Kegiatan</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Siklus Persetujuan</div>
                    <p class="text-muted small mb-0">Kegiatan yang disimpan dengan status "Approved" atau "Active" akan otomatis mencatat tanggal dan verifikator penyetujuan pada sistem.</p>
                </div>
            </div>
        </div>
    </div>
</div>
