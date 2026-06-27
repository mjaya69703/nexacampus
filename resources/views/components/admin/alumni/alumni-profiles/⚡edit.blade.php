<?php

use App\Enums\EmploymentStatus;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Alumni\AlumniProfile;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public int $profileId;
    public array $form = [];
    public $photo;
    public ?string $existingPhoto = null;
    public $faculties = [];
    public $studyPrograms = [];
    public array $employmentStatuses = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.alumni.profiles.index');
    }

    public function mount($id): void
    {
        $profile = AlumniProfile::findOrFail($id);
        $this->profileId = (int) $id;
        $this->existingPhoto = $profile->photo_path;
        $this->faculties = Faculty::where('is_active', true)->orderBy('name')->get();
        $this->studyPrograms = StudyProgram::where('is_active', true)->orderBy('name')->get();
        $this->employmentStatuses = EmploymentStatus::options();

        $this->form = [
            'nim' => $profile->nim,
            'full_name' => $profile->full_name,
            'graduation_date' => $profile->graduation_date?->format('Y-m-d') ?? '',
            'graduation_year' => $profile->graduation_year,
            'faculty_id' => $profile->faculty_id ?? '',
            'study_program_id' => $profile->study_program_id ?? '',
            'gpa' => $profile->gpa,
            'birth_date' => $profile->birth_date?->format('Y-m-d') ?? '',
            'gender' => $profile->gender ?? '',
            'email' => $profile->email,
            'phone' => $profile->phone ?? '',
            'address' => $profile->address ?? '',
            'current_city' => $profile->current_city ?? '',
            'current_province' => $profile->current_province ?? '',
            'employment_status' => $profile->employment_status,
            'employer_name' => $profile->employer_name ?? '',
            'job_title' => $profile->job_title ?? '',
            'job_industry' => $profile->job_industry ?? '',
            'linkedin_url' => $profile->linkedin_url ?? '',
            'is_active' => (bool) $profile->is_active,
        ];
    }

    public function updateAlumniProfile(): void
    {
        $validated = $this->validate([
            'form.nim' => ['required', 'string', 'max:50', Rule::unique('alumni_profiles', 'nim')->ignore($this->profileId)],
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

        $profile = AlumniProfile::findOrFail($this->profileId);
        $photoPath = $this->existingPhoto;

        if ($this->photo) {
            $photoPath = $this->photo->store('alumni/photos', 'public');
        }

        $profile->update(array_merge(
            collect($validated['form'])->filter(fn ($v) => $v !== '')->toArray(),
            [
                'photo_path' => $photoPath,
                'updated_by' => auth()->id(),
            ]
        ));

        session()->flash('success', 'Profil alumni berhasil diperbarui.');
        $this->redirectRoute('admin.alumni.profiles.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni',
            'pages' => 'Edit Data Alumni',
        ]);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Edit Data Alumni: {{ $form['full_name'] ?? '' }}</h3>
        </div>
        <div class="card-body row">
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="nim">NIM <span class="text-danger">*</span></label>
                <input type="text" id="nim" class="form-control" wire:model.defer="form.nim">
                @error('form.nim') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="full_name">Nama Lengkap <span class="text-danger">*</span></label>
                <input type="text" id="full_name" class="form-control" wire:model.defer="form.full_name">
                @error('form.full_name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="email">Email <span class="text-danger">*</span></label>
                <input type="email" id="email" class="form-control" wire:model.defer="form.email">
                @error('form.email') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-3 col-md-6 col-sm-12 mt-2">
                <label for="graduation_date">Tanggal Lulus <span class="text-danger">*</span></label>
                <input type="date" id="graduation_date" class="form-control" wire:model.defer="form.graduation_date">
                @error('form.graduation_date') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-3 col-md-6 col-sm-12 mt-2">
                <label for="graduation_year">Tahun Lulus <span class="text-danger">*</span></label>
                <input type="number" id="graduation_year" class="form-control" wire:model.defer="form.graduation_year">
                @error('form.graduation_year') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-3 col-md-6 col-sm-12 mt-2">
                <label for="gpa">IPK</label>
                <input type="number" step="0.01" id="gpa" class="form-control" wire:model.defer="form.gpa">
                @error('form.gpa') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-3 col-md-6 col-sm-12 mt-2">
                <label for="gender">Jenis Kelamin</label>
                <select id="gender" class="form-control" wire:model.defer="form.gender">
                    <option value="">-- Pilih --</option>
                    <option value="L">Laki-laki</option>
                    <option value="P">Perempuan</option>
                </select>
            </div>
            <div class="form-group col-lg-3 col-md-6 col-sm-12 mt-2">
                <label for="birth_date">Tanggal Lahir</label>
                <input type="date" id="birth_date" class="form-control" wire:model.defer="form.birth_date">
            </div>
            <div class="form-group col-lg-3 col-md-6 col-sm-12 mt-2">
                <label for="faculty_id">Fakultas</label>
                <select id="faculty_id" class="form-control" wire:model.live="form.faculty_id">
                    <option value="">-- Pilih Fakultas --</option>
                    @foreach ($faculties as $f)
                        <option value="{{ $f->id }}">{{ $f->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-lg-3 col-md-6 col-sm-12 mt-2">
                <label for="study_program_id">Program Studi</label>
                <select id="study_program_id" class="form-control" wire:model.defer="form.study_program_id">
                    <option value="">-- Pilih Prodi --</option>
                    @foreach ($studyPrograms as $sp)
                        <option value="{{ $sp->id }}">{{ $sp->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="phone">No. Telepon</label>
                <input type="text" id="phone" class="form-control" wire:model.defer="form.phone">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="linkedin_url">LinkedIn URL</label>
                <input type="url" id="linkedin_url" class="form-control" wire:model.defer="form.linkedin_url">
            </div>
            <div class="form-group col-12 mt-2">
                <label for="address">Alamat</label>
                <textarea id="address" class="form-control" rows="2" wire:model.defer="form.address"></textarea>
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="current_city">Kota Saat Ini</label>
                <input type="text" id="current_city" class="form-control" wire:model.defer="form.current_city">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="current_province">Provinsi Saat Ini</label>
                <input type="text" id="current_province" class="form-control" wire:model.defer="form.current_province">
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="employment_status">Status Pekerjaan <span class="text-danger">*</span></label>
                <select id="employment_status" class="form-control" wire:model.defer="form.employment_status">
                    @foreach ($employmentStatuses as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="employer_name">Nama Perusahaan</label>
                <input type="text" id="employer_name" class="form-control" wire:model.defer="form.employer_name">
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="job_title">Jabatan</label>
                <input type="text" id="job_title" class="form-control" wire:model.defer="form.job_title">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="job_industry">Industri</label>
                <input type="text" id="job_industry" class="form-control" wire:model.defer="form.job_industry">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="photo">Foto</label>
                @if ($existingPhoto)
                    <div class="mb-2"><small class="text-muted">Foto saat ini tersedia</small></div>
                @endif
                <input type="file" id="photo" class="form-control" wire:model="photo" accept="image/*">
                <div wire:loading wire:target="photo" class="text-muted mt-1">Uploading...</div>
            </div>
            <div class="form-group col-12 mt-2">
                <div class="form-check form-switch mt-2">
                    <input id="is_active" class="form-check-input" type="checkbox" wire:model.defer="form.is_active">
                    <label for="is_active" class="form-check-label">Aktif</label>
                </div>
            </div>

            <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
                <button class="btn btn-primary" wire:click="updateAlumniProfile">
                    <i class="fas fa-save me-2"></i> Simpan Perubahan
                </button>
                <button class="btn btn-secondary" wire:click="cancel">
                    <i class="fas fa-times me-2"></i> Batal
                </button>
            </div>
        </div>
    </div>
</div>
