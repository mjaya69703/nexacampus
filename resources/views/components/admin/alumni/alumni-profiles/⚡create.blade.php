<?php

use App\Enums\EmploymentStatus;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Alumni\AlumniProfile;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public array $form = [];
    public $photo;
    public $faculties = [];
    public $studyPrograms = [];
    public array $employmentStatuses = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.alumni.profiles.index');
    }

    public function mount(): void
    {
        $this->faculties = Faculty::where('is_active', true)->orderBy('name')->get();
        $this->studyPrograms = StudyProgram::where('is_active', true)->orderBy('name')->get();
        $this->employmentStatuses = EmploymentStatus::options();
        $this->form = [
            'nim' => '',
            'full_name' => '',
            'graduation_date' => '',
            'graduation_year' => '',
            'faculty_id' => '',
            'study_program_id' => '',
            'gpa' => '',
            'birth_date' => '',
            'gender' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'current_city' => '',
            'current_province' => '',
            'employment_status' => 'unemployed',
            'employer_name' => '',
            'job_title' => '',
            'job_industry' => '',
            'linkedin_url' => '',
            'is_active' => true,
        ];
    }

    public function createAlumniProfile(): void
    {
        $validated = $this->validate([
            'form.nim' => 'required|string|max:50|unique:alumni_profiles,nim',
            'form.full_name' => 'required|string|max:255',
            'form.graduation_date' => 'required|date',
            'form.graduation_year' => 'required|integer|min:1990|max:2100',
            'form.faculty_id' => 'nullable|exists:faculties,id',
            'form.study_program_id' => 'nullable|exists:study_programs,id',
            'form.gpa' => 'nullable|numeric|min:0|max:4',
            'form.birth_date' => 'nullable|date',
            'form.gender' => 'nullable|in:L,P',
            'form.email' => 'required|email|max:255',
            'form.phone' => 'nullable|string|max:30',
            'form.address' => 'nullable|string',
            'form.current_city' => 'nullable|string|max:255',
            'form.current_province' => 'nullable|string|max:255',
            'form.employment_status' => 'required|string',
            'form.employer_name' => 'nullable|string|max:255',
            'form.job_title' => 'nullable|string|max:255',
            'form.job_industry' => 'nullable|string|max:255',
            'form.linkedin_url' => 'nullable|url|max:500',
            'form.is_active' => 'boolean',
            'photo' => 'nullable|image|max:2048',
        ]);

        $photoPath = null;
        if ($this->photo) {
            $photoPath = $this->photo->store('alumni/photos', 'public');
        }

        DB::transaction(function () use ($validated, $photoPath) {
            AlumniProfile::create(array_merge(
                collect($validated['form'])->filter(fn ($v) => $v !== '')->toArray(),
                [
                    'photo_path' => $photoPath,
                    'created_by' => auth()->id(),
                ]
            ));
        });

        session()->flash('success', 'Profil alumni berhasil ditambahkan.');
        $this->redirectRoute('admin.alumni.profiles.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni Management',
            'pages' => 'Tambah Data Alumni',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.alumni.header
        title="Tambah Profil Alumni Baru"
        description="Lengkapi data diri lulusan, informasi akademik, dan riwayat karir awal untuk registrasi di dalam direktori alumni."
        icon="user-plus"
    >
        <a href="{{ route('admin.alumni.profiles.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </a>
    </x-admin.alumni.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-id-card fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Biodata & Karir Alumni</h4>
                            <div class="text-muted small">Pastikan NIM dan alamat email valid untuk kebutuhan autentikasi dan survei.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="fa fa-user me-2 text-primary"></i>Informasi Pribadi & Akademik</h6>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="nim" class="form-label fw-semibold">NIM <span class="text-danger">*</span></label>
                            <input type="text" id="nim" class="form-control rounded-3" placeholder="Contoh: 2020101001" wire:model.defer="form.nim">
                            @error('form.nim') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="full_name" class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" id="full_name" class="form-control rounded-3" placeholder="Nama alumni sesuai ijazah" wire:model.defer="form.full_name">
                            @error('form.full_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="email" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" id="email" class="form-control rounded-3" placeholder="email@domain.com" wire:model.defer="form.email">
                            @error('form.email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label for="graduation_date" class="form-label fw-semibold">Tanggal Lulus <span class="text-danger">*</span></label>
                            <input type="date" id="graduation_date" class="form-control rounded-3" wire:model.defer="form.graduation_date">
                            @error('form.graduation_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label for="graduation_year" class="form-label fw-semibold">Tahun Lulus <span class="text-danger">*</span></label>
                            <input type="number" id="graduation_year" class="form-control rounded-3" placeholder="2024" wire:model.defer="form.graduation_year">
                            @error('form.graduation_year') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label for="gpa" class="form-label fw-semibold">IPK</label>
                            <input type="number" step="0.01" id="gpa" class="form-control rounded-3" wire:model.defer="form.gpa" placeholder="0.00 - 4.00">
                            @error('form.gpa') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label for="gender" class="form-label fw-semibold">Jenis Kelamin</label>
                            <select id="gender" class="form-control rounded-3" wire:model.defer="form.gender">
                                <option value="">-- Pilih --</option>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="birth_date" class="form-label fw-semibold">Tanggal Lahir</label>
                            <input type="date" id="birth_date" class="form-control rounded-3" wire:model.defer="form.birth_date">
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="faculty_id" class="form-label fw-semibold">Fakultas</label>
                            <select id="faculty_id" class="form-control rounded-3" wire:model.live="form.faculty_id">
                                <option value="">-- Pilih Fakultas --</option>
                                @foreach ($faculties as $f)
                                    <option value="{{ $f->id }}">{{ $f->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="study_program_id" class="form-label fw-semibold">Program Studi</label>
                            <select id="study_program_id" class="form-control rounded-3" wire:model.defer="form.study_program_id">
                                <option value="">-- Pilih Prodi --</option>
                                @foreach ($studyPrograms as $sp)
                                    <option value="{{ $sp->id }}">{{ $sp->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="fa fa-map-marker-alt me-2 text-info"></i>Kontak & Domisili</h6>
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="phone" class="form-label fw-semibold">No. Telepon / WhatsApp</label>
                            <input type="text" id="phone" class="form-control rounded-3" placeholder="08xxxxxxxxx" wire:model.defer="form.phone">
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="linkedin_url" class="form-label fw-semibold">LinkedIn URL</label>
                            <input type="url" id="linkedin_url" class="form-control rounded-3" wire:model.defer="form.linkedin_url" placeholder="https://linkedin.com/in/username">
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label fw-semibold">Alamat Lengkap</label>
                            <textarea id="address" class="form-control rounded-3" rows="2" wire:model.defer="form.address" placeholder="Jalan, RT/RW, Kelurahan, Kecamatan..."></textarea>
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="current_city" class="form-label fw-semibold">Kota Saat Ini</label>
                            <input type="text" id="current_city" class="form-control rounded-3" placeholder="Contoh: Jakarta Selatan" wire:model.defer="form.current_city">
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="current_province" class="form-label fw-semibold">Provinsi Saat Ini</label>
                            <input type="text" id="current_province" class="form-control rounded-3" placeholder="Contoh: DKI Jakarta" wire:model.defer="form.current_province">
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="fa fa-briefcase me-2 text-success"></i>Status Kepekerjaan & Karir</h6>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="employment_status" class="form-label fw-semibold">Status Pekerjaan <span class="text-danger">*</span></label>
                            <select id="employment_status" class="form-control rounded-3" wire:model.defer="form.employment_status">
                                @foreach ($employmentStatuses as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="employer_name" class="form-label fw-semibold">Nama Perusahaan / Instansi</label>
                            <input type="text" id="employer_name" class="form-control rounded-3" placeholder="Contoh: PT Nexa Indonesia" wire:model.defer="form.employer_name">
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="job_title" class="form-label fw-semibold">Jabatan / Posisi</label>
                            <input type="text" id="job_title" class="form-control rounded-3" placeholder="Contoh: Software Engineer" wire:model.defer="form.job_title">
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="job_industry" class="form-label fw-semibold">Bidang Industri</label>
                            <input type="text" id="job_industry" class="form-control rounded-3" placeholder="Contoh: Teknologi Informasi / Pendidikan" wire:model.defer="form.job_industry">
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="photo" class="form-label fw-semibold">Foto Profil (Maks. 2MB)</label>
                            <input type="file" id="photo" class="form-control rounded-3" wire:model="photo" accept="image/*">
                            <div wire:loading wire:target="photo" class="text-muted small mt-1"><i class="fa fa-spinner fa-spin me-1"></i> Mengunggah foto...</div>
                        </div>

                        <div class="col-12 mt-3">
                            <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="form.is_active">
                                    <label class="form-check-label fw-semibold" for="is_active">Profil Aktif & Ditampilkan</label>
                                </div>
                                <div class="text-muted small mt-1">Jika nonaktif, profil alumni tidak akan muncul dalam penelusuran publik atau laporan statistik aktif.</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="cancel">
                            <i class="fa fa-times me-2"></i> Batal
                        </button>
                        <button type="button" class="btn btn-primary rounded-pill px-4" wire:click="createAlumniProfile">
                            <i class="fa fa-save me-2"></i> Simpan Profil Alumni
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
                            <h5 class="fw-bold mb-1">Panduan Input</h5>
                            <div class="text-muted small">Kelengkapan data sangat penting untuk akurasi laporan IKU kampus.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2.5">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>NIM & Email</strong> digunakan sebagai identifier unik dan pengiriman kuesioner tracer study.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Status Pekerjaan</strong> akan memperbarui statistik serapan lulusan perguruan tinggi secara otomatis.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Foto Profil</strong> profesional membantu branding alumni di portal jejaring karir kampus.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Keanggotaan</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Aktif (Default)</div>
                    <p class="text-muted small mb-0">Alumni yang baru ditambahkan langsung dapat diundang ke acara dan survei karir kampus.</p>
                </div>
            </div>
        </div>
    </div>
</div>
