<?php

use App\Models\Alumni\EmployerPartner;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public array $form = [];
    public $logo;

    public function cancel(): void
    {
        $this->redirectRoute('admin.alumni.employer-partners.index');
    }

    public function mount(): void
    {
        $this->form = [
            'name' => '',
            'industry' => '',
            'website' => '',
            'contact_person' => '',
            'contact_email' => '',
            'contact_phone' => '',
            'address' => '',
            'city' => '',
            'province' => '',
            'description' => '',
            'is_active' => true,
        ];
    }

    public function createEmployerPartner(): void
    {
        $validated = $this->validate([
            'form.name' => 'required|string|max:255',
            'form.industry' => 'required|string|max:255',
            'form.website' => 'nullable|url|max:500',
            'form.contact_person' => 'nullable|string|max:255',
            'form.contact_email' => 'nullable|email|max:255',
            'form.contact_phone' => 'nullable|string|max:30',
            'form.address' => 'nullable|string',
            'form.city' => 'nullable|string|max:255',
            'form.province' => 'nullable|string|max:255',
            'form.description' => 'nullable|string',
            'form.is_active' => 'boolean',
            'logo' => 'nullable|image|max:2048',
        ]);

        $logoPath = null;
        if ($this->logo) {
            $logoPath = $this->logo->store('employer-partners/logos', 'public');
        }

        EmployerPartner::create(array_merge(
            collect($validated['form'])->filter(fn ($v) => $v !== '')->toArray(),
            [
                'logo_path' => $logoPath,
                'created_by' => auth()->id(),
            ]
        ));

        session()->flash('success', 'Mitra perusahaan berhasil ditambahkan.');
        $this->redirectRoute('admin.alumni.employer-partners.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni Management',
            'pages' => 'Tambah Mitra Perusahaan',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.alumni.header
        title="Tambah Mitra Perusahaan Baru"
        description="Daftarkan perusahaan atau instansi mitra untuk kerjasama publikasi lowongan karir dan rekrutmen lulusan."
        icon="building-circle-check"
    >
        <a href="{{ route('admin.alumni.employer-partners.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </a>
    </x-admin.alumni.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-handshake fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Identitas & Kontak Mitra</h4>
                            <div class="text-muted small">Pastikan nama dan sektor industri dituliskan dengan tepat untuk kemudahan filter lowongan.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="fa fa-building me-2 text-primary"></i>Profil Perusahaan</h6>
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="name" class="form-label fw-semibold">Nama Perusahaan <span class="text-danger">*</span></label>
                            <input type="text" id="name" class="form-control rounded-3" placeholder="Contoh: PT Teknologi Nusantara" wire:model.defer="form.name">
                            @error('form.name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="industry" class="form-label fw-semibold">Sektor Industri <span class="text-danger">*</span></label>
                            <input type="text" id="industry" class="form-control rounded-3" placeholder="Contoh: Teknologi Informasi / Perbankan" wire:model.defer="form.industry">
                            @error('form.industry') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="website" class="form-label fw-semibold">Website Resmi</label>
                            <input type="url" id="website" class="form-control rounded-3" wire:model.defer="form.website" placeholder="https://www.company.com">
                            @error('form.website') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="logo" class="form-label fw-semibold">Logo Perusahaan (Maks. 2MB)</label>
                            <input type="file" id="logo" class="form-control rounded-3" wire:model="logo" accept="image/*">
                            <div wire:loading wire:target="logo" class="text-muted small mt-1"><i class="fa fa-spinner fa-spin me-1"></i> Mengunggah logo...</div>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label fw-semibold">Deskripsi Singkat Perusahaan</label>
                            <textarea id="description" class="form-control rounded-3" rows="3" wire:model.defer="form.description" placeholder="Profil singkat atau fokus bisnis instansi..."></textarea>
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="fa fa-address-book me-2 text-info"></i>Personil Kontak & Domisili</h6>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="contact_person" class="form-label fw-semibold">Nama Kontak Person</label>
                            <input type="text" id="contact_person" class="form-control rounded-3" placeholder="Contoh: Budi Santoso (HR Manager)" wire:model.defer="form.contact_person">
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="contact_email" class="form-label fw-semibold">Email Kontak</label>
                            <input type="email" id="contact_email" class="form-control rounded-3" placeholder="hr@company.com" wire:model.defer="form.contact_email">
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="contact_phone" class="form-label fw-semibold">No. Telepon Kontak</label>
                            <input type="text" id="contact_phone" class="form-control rounded-3" placeholder="021-xxxx / 08xxxxxxxx" wire:model.defer="form.contact_phone">
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="city" class="form-label fw-semibold">Kota</label>
                            <input type="text" id="city" class="form-control rounded-3" placeholder="Contoh: Jakarta Pusat" wire:model.defer="form.city">
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="province" class="form-label fw-semibold">Provinsi</label>
                            <input type="text" id="province" class="form-control rounded-3" placeholder="Contoh: DKI Jakarta" wire:model.defer="form.province">
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label fw-semibold">Alamat Kantor</label>
                            <textarea id="address" class="form-control rounded-3" rows="2" wire:model.defer="form.address" placeholder="Gedung, Jalan, RT/RW, Kode Pos..."></textarea>
                        </div>

                        <div class="col-12 mt-3">
                            <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="form.is_active">
                                    <label class="form-check-label fw-semibold" for="is_active">Mitra Aktif & Siap Menerima Lowongan</label>
                                </div>
                                <div class="text-muted small mt-1">Mitra yang aktif dapat dikaitkan dengan lowongan baru dan ditampilkan pada direktori karir.</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="cancel">
                            <i class="fa fa-times me-2"></i> Batal
                        </button>
                        <button type="button" class="btn btn-primary rounded-pill px-4" wire:click="createEmployerPartner">
                            <i class="fa fa-save me-2"></i> Simpan Mitra Perusahaan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-circle-info fs-5"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Panduan Mitra</h5>
                            <div class="text-muted small">Kerjasama yang terverifikasi memudahkan validasi lowongan kerja.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2.5">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pastikan kontak person adalah staf penanggung jawab resmi HR/Rekrutmen.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Sektor industri akan digunakan sebagai salah satu kategori filter pencarian lowongan.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Kerja Sama</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Aktif (Default)</div>
                    <p class="text-muted small mb-0">Perusahaan baru langsung siap ditambahkan lowongan kerja untuk disebarkan kepada alumni.</p>
                </div>
            </div>
        </div>
    </div>
</div>
