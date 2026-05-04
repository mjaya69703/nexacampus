<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\WithFileUploads;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Academic\AcademicYear;

new class extends Component {
    use WithFileUploads;

    public $tab = 'biodata';
    public $userForm = [];
    public $studentForm = [];
    public $lecturerForm = [];
    public $photo = null;
    public array $availableFaculties = [];
    public array $availableStudyPrograms = [];
    public array $availableAcademicYears = [];

    public function mount()
    {
        $user = auth()->user()->load(['studentProfile', 'lecturerProfile']);
        $this->userForm = $user->toArray();
        $this->studentForm = $user->studentProfile?->toArray() ?? [];
        $this->lecturerForm = $user->lecturerProfile?->toArray() ?? [];
        $this->availableFaculties = Faculty::where('is_active', true)->pluck('name', 'id')->toArray();
        $this->availableStudyPrograms = StudyProgram::where('is_active', true)->pluck('name', 'id')->toArray();
        $this->availableAcademicYears = AcademicYear::pluck('name', 'id')->toArray();
    }

    // Notes : Need delete image after update profile with new photo, to avoid orphaned files in storage
    public function updateProfile()
    {
        $validatedData = $this->validate([
            'userForm.first_name' => 'required|string|max:255',
            'userForm.last_name' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'userForm.username' => 'required|string|max:255|unique:users,username,' . auth()->id(),
            'userForm.phone' => 'required|string|max:20|unique:users,phone,' . auth()->id(),
            'userForm.email' => 'required|email|max:255|unique:users,email,' . auth()->id(),
            'userForm.instagram' => 'nullable|string|max:255',
            'userForm.facebook' => 'nullable|string|max:255',
            'userForm.linkedin' => 'nullable|string|max:255',
            'userForm.identity_number' => 'nullable|string|max:255|unique:users,identity_number,' . auth()->id(),
            'userForm.religion' => 'nullable|in:Islam,Kristen,Katolik,Hindu,Buddha,Khonghucu',
            'userForm.blood_type' => 'nullable|in:A,B,AB,O',
            'userForm.citizenship' => 'nullable|in:WNI,WNA',
            'userForm.gender' => 'nullable|in:Laki-laki,Perempuan',
            'userForm.height' => 'nullable|integer',
            'userForm.weight' => 'nullable|integer',
            'userForm.place_of_birth' => 'nullable|string|max:255',
            'userForm.date_of_birth' => 'nullable|date',
            'userForm.current_password' => 'nullable|string|current_password',
            'userForm.new_password' => 'nullable|string|min:8|confirmed',
            'userForm.new_password_confirmation' => 'nullable|string|min:8|same:userForm.new_password',
            'userForm.is_active' => 'nullable|boolean',
            'userForm.fst_setup' => 'boolean',
            'userForm.tfa_setup' => 'boolean',
            'studentForm.study_program_id' => 'nullable|exists:study_programs,id',
            'studentForm.entry_academic_year_id' => 'nullable|exists:academic_years,id',
            'studentForm.nim' => 'nullable|string|max:255|unique:student_profiles,nim,' . (auth()->user()->studentProfile?->id ?? 'NULL'),
            'studentForm.entry_year' => 'nullable|integer|min:1900|max:' . date('Y'),
            'studentForm.academic_status' => 'nullable|in:Aktif,Cuti,Lulus,Drop Out,Nonaktif,Keluar',
            'studentForm.entry_date' => 'nullable|date',
            'studentForm.graduation_date' => 'nullable|date|after:studentForm.entry_date',
            'studentForm.current_semester' => 'nullable|integer|min:1|max:14',
            'studentForm.is_active' => 'nullable|boolean',
            'studentForm.desc' => 'nullable|string',
            'lecturerForm.faculty_id' => 'nullable|exists:faculties,id',
            'lecturerForm.study_program_id' => 'nullable|exists:study_programs,id',
            'lecturerForm.nidn' => 'nullable|string|max:255|unique:lecturer_profiles,nidn,' . (auth()->user()->lecturerProfile?->id ?? 'NULL'),
            'lecturerForm.nidk' => 'nullable|string|max:255|unique:lecturer_profiles,nidk,' . (auth()->user()->lecturerProfile?->id ?? 'NULL'),
            'lecturerForm.nip' => 'nullable|string|max:255|unique:lecturer_profiles,nip,' . (auth()->user()->lecturerProfile?->id ?? 'NULL'),
            'lecturerForm.employment_status' => 'nullable|in:Tetap,Kontrak,Tidak Tetap,Tamu',
            'lecturerForm.join_date' => 'nullable|date',
            'lecturerForm.is_active' => 'nullable|boolean',
            'lecturerForm.desc' => 'nullable|string',
        ]);

        DB::beginTransaction();

        if($this->photo) {
            $filename = 'profile_' . auth()->id() . '_' . time() . '.' . $this->photo->getClientOriginalExtension();
            $this->photo->storeAs('images/profile', $filename, 'public');
            $photo = $filename;
        }

        $user = auth()->user();
        $user->first_name = $validatedData['userForm']['first_name'];
        $user->last_name = $validatedData['userForm']['last_name'];
        $user->username = $validatedData['userForm']['username'];
        $user->phone = $validatedData['userForm']['phone'];
        $user->email = $validatedData['userForm']['email'];
        $user->instagram = $validatedData['userForm']['instagram'];
        $user->facebook = $validatedData['userForm']['facebook'];
        $user->linkedin = $validatedData['userForm']['linkedin'];
        $user->identity_number = $validatedData['userForm']['identity_number'];
        $user->religion = $validatedData['userForm']['religion'];
        $user->blood_type = $validatedData['userForm']['blood_type'];
        $user->citizenship = $validatedData['userForm']['citizenship'];
        $user->gender = $validatedData['userForm']['gender'];
        $user->height = $validatedData['userForm']['height'];
        $user->weight = $validatedData['userForm']['weight'];
        $user->place_of_birth = $validatedData['userForm']['place_of_birth'];
        $user->date_of_birth = $validatedData['userForm']['date_of_birth'];
        $user->is_active = $validatedData['userForm']['is_active'];
        $user->fst_setup = $validatedData['userForm']['fst_setup'];
        $user->tfa_setup = $validatedData['userForm']['tfa_setup'];

        if(isset($photo)) {
            $user->photo = $photo;
        }

        // Change Password
        if (!empty($validatedData['userForm']['new_password']) && !empty($validatedData['userForm']['current_password'])) {
            if(!Hash::check($validatedData['userForm']['current_password'], $user->password)) {
                session()->flash('error', 'Password lama tidak sesuai!');
                return;
            }
            $user->password = Hash::make($validatedData['userForm']['new_password']);
        }

        $user->save();

        // Save student profile if user has student role
        $roles = $user->roles->pluck('name')->toArray();
        if (in_array('student', $roles, true) && !empty($validatedData['studentForm']) && collect($validatedData['studentForm'])->filter()->count() > 0) {
            $user->studentProfile()->updateOrCreate(
                ['user_id' => $user->id],
                array_merge($validatedData['studentForm'], ['created_by' => auth()->id()])
            );
        }

        // Save lecturer profile if user has lecturer role
        if (in_array('lecturer', $roles, true) && !empty($validatedData['lecturerForm']) && collect($validatedData['lecturerForm'])->filter()->count() > 0) {
            $user->lecturerProfile()->updateOrCreate(
                ['user_id' => $user->id],
                array_merge($validatedData['lecturerForm'], ['created_by' => auth()->id()])
            );
        }

        DB::commit();

        session()->flash('success', 'Profil berhasil diperbarui!');
    }

    public function render()
    {
        $data = [
            'menus' => 'System', // Data menu
            'pages' => 'User Profile', // Data halaman
            // 'user' => null, // Data user
        ];

        // Kirim data ke view dan layout secara langsung
        return $this->view($data)->layout('layouts.app', $data);
    }
};
?>

@push('styles')
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            border-radius: 10px;
            color: white;
            margin-bottom: 2rem;
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid white;
            object-fit: cover;
        }

        .nav-tabs .nav-link.active {
            background-color: #667eea;
            color: white;
            border-color: #667eea;
        }

        .form-section {
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
    </style>
@endpush

<div class="row">
    <div class="col-12">
        <x-alert />
        <div class="card">
            <div class="card-body">
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            @if($photo instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile && $photo->isPreviewable())
                                <img src="{{ $photo->temporaryUrl() }}" alt="Profile Photo" class="preview-image" id="previewPhoto">
                            @else
                                <img src="{{ $user->photo }}" alt="Profile Photo" class="profile-photo" id="previewPhoto">
                            @endif
                        </div>
                        <div class="col-md-10">
                            <h2 class="mb-0">{{ $user->name }}</h2>
                            <p class="mb-1">{{ ucfirst($activeRole) }}</p>
                            <p class="mb-0"><i class="fas fa-envelope"></i> {{ $user->email }} | <i class="fas fa-phone"></i> {{ $user->phone }}</p>
                        </div>
                    </div>
                </div>
                <!-- Form Update Profile -->
                <form wire:submit.prevent="updateProfile" enctype="multipart/form-data">
                    @csrf

                    <!-- Nav Tabs -->
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === 'biodata' ? 'active' : '' }}" wire:click="$set('tab', 'biodata')" href="#biodata" role="tab">
                                <i class="fas fa-user me-2"></i> Biodata Umum
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link {{ $tab === 'keamanan' ? 'active' : '' }}" wire:click="$set('tab', 'keamanan')" href="#keamanan" role="tab">
                                <i class="fas fa-lock me-2"></i> Keamanan
                            </a>
                        </li>

                        @if(session('active_role') === 'student')
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === 'student' ? 'active' : '' }}" wire:click="$set('tab', 'student')" href="#student" role="tab">
                                <i class="fas fa-book me-2"></i> Profil Mahasiswa
                            </a>
                        </li>
                        @endif

                        @if(session('active_role') === 'lecturer')
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === 'lecturer' ? 'active' : '' }}" wire:click="$set('tab', 'lecturer')" href="#lecturer" role="tab">
                                <i class="fas fa-chalkboard-user me-2"></i> Profil Dosen
                            </a>
                        </li>
                        @endif

                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content p-3">

                        <!-- Tab Biodata Umum -->
                        <div class="tab-pane {{ $tab === 'biodata' ? 'active show' : '' }}" id="biodata" role="tabpanel">
                            <div class="form-section">
                                <h5 class="mb-3">Biodata Umum</h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Nama Depan <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" wire:model="userForm.first_name" placeholder="Masukkan nama lengkap" required>
                                        @error('userForm.first_name') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Nama Belakang <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" wire:model="userForm.last_name" placeholder="Masukkan nama belakang" required>
                                        @error('userForm.last_name') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" wire:model="userForm.username" {{ !empty($userForm['username']) ? 'disabled' : '' }} placeholder="Masukkan username unik">
                                        @error('userForm.username') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Jenis Kelamin</label>
                                        <select class="form-select" wire:model="userForm.gender">
                                            <option value="">Pilih Jenis Kelamin</option>
                                            <option value="Laki-laki">Laki-laki</option>
                                            <option value="Perempuan">Perempuan</option>
                                        </select>
                                        @error('userForm.gender') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Tempat Lahir</label>
                                        <input type="text" class="form-control" wire:model="userForm.place_of_birth" placeholder="Contoh: Jakarta">
                                        @error('userForm.place_of_birth') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Tanggal Lahir</label>
                                        <input type="date" class="form-control" wire:model="userForm.date_of_birth" placeholder="YYYY-MM-DD">
                                        @error('userForm.date_of_birth') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Agama</label>
                                        <select class="form-select" wire:model="userForm.religion">
                                            <option value="">Pilih Agama</option>
                                            <option value="Islam">Islam</option>
                                            <option value="Katolik">Katolik</option>
                                            <option value="Protestan">Protestan</option>
                                            <option value="Hindu">Hindu</option>
                                            <option value="Buddha">Buddha</option>
                                            <option value="Khonghucu">Khonghucu
                                            </option>
                                        </select>
                                        @error('userForm.religion') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Golongan Darah</label>
                                        <select class="form-select" wire:model="userForm.blood_type">
                                            <option value="">Pilih Golongan Darah</option>
                                            <option value="A">A</option>
                                            <option value="B">B</option>
                                            <option value="AB">AB</option>
                                            <option value="O">O</option>
                                        </select>
                                        @error('userForm.blood_type') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Kewarganegaraan</label>
                                        <select class="form-select" wire:model="userForm.citizenship">
                                            <option value="">Pilih Kewarganegaraan</option>
                                            <option value="WNI" >Warga Negara
                                                Indonesia (WNI)</option>
                                            <option value="WNA" >Warga Negara Asing
                                                (WNA)</option>
                                        </select>
                                        @error('userForm.citizenship') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Tinggi Badan (cm)</label>
                                        <input type="text" class="form-control" wire:model="userForm.height" placeholder="Contoh: 170">
                                        @error('userForm.height') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Berat Badan (kg)</label>
                                        <input type="text" class="form-control" wire:model="userForm.weight" placeholder="Contoh: 65">
                                        @error('userForm.weight') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Foto Profile</label>
                                        <input type="file" class="form-control" wire:model="photo" accept="image/*" onchange="previewImage(this)">
                                        <small class="text-muted">Kosongkan jika tidak ingin mengubah foto</small>
                                        @error('photo') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h5 class="mb-3">Informasi Lainnya</h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" wire:model="userForm.email" {{ !empty($userForm['email']) ? 'disabled' : '' }} placeholder="contoh@email.com" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Nomor Telepon <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" wire:model="userForm.phone" {{ !empty($userForm['phone']) ? 'disabled' : '' }} placeholder="Contoh: 081234567890" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Nomor Identitas <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" wire:model="userForm.identity_number" {{ !empty($userForm['identity_number']) ? 'disabled' : '' }} placeholder="Contoh: 3500000000000001" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><i class="fab fa-instagram"></i> Instagram</label>
                                        <input type="text" class="form-control" wire:model="userForm.instagram" placeholder="@username_instagram">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><i class="fab fa-facebook"></i> Facebook</label>
                                        <input type="text" class="form-control" wire:model="userForm.facebook" placeholder="facebook.com/username">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><i class="fab fa-linkedin"></i> LinkedIn</label>
                                        <input type="text" class="form-control" wire:model="userForm.linkedin" placeholder="linkedin.com/in/username">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Keamanan -->
                        <div class="tab-pane {{ $tab === 'keamanan' ? 'active show' : '' }}" id="keamanan" role="tabpanel">
                            <div class="form-section">
                                <h5 class="mb-3">Ubah Password</h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Password Lama</label>
                                        <input type="password" class="form-control" wire:model="userForm.current_password" placeholder="Masukkan password lama">
                                        @error('userForm.current_password') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Password Baru</label>
                                        <input type="password" class="form-control" wire:model="userForm.new_password" placeholder="Minimal 8 karakter">
                                        @error('userForm.new_password') <span class="text-danger">{{ $message }}</span> @enderror
                                    
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Konfirmasi Password Baru</label>
                                        <input type="password" class="form-control" wire:model="userForm.new_password_confirmation" placeholder="Ulangi password baru">
                                        @error('userForm.new_password_confirmation') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <small class="text-muted">Kosongkan jika tidak ingin mengubah password</small>
                            </div>

                            <div class="form-section">
                                <h5 class="mb-3">Pengaturan Keamanan</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" wire:model="userForm.fst_setup">
                                            <label class="form-check-label">First Time Setup</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" wire:model="userForm.tfa_setup">
                                            <label class="form-check-label">Two Factor Authentication</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Profil Mahasiswa -->
                        @if(session('active_role') === 'student')
                        <div class="tab-pane {{ $tab === 'student' ? 'active show' : '' }}" id="student" role="tabpanel">
                            <div class="form-section">
                                <h5 class="mb-3">Data Akademik</h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Program Studi</label>
                                        <select class="form-select" wire:model="studentForm.study_program_id" disabled>
                                            <option value="">Pilih Program Studi</option>
                                            @foreach($availableStudyPrograms as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                        @error('studentForm.study_program_id') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Tahun Akademik Masuk</label>
                                        <select class="form-select" wire:model="studentForm.entry_academic_year_id" disabled>
                                            <option value="">Pilih Tahun Akademik</option>
                                            @foreach($availableAcademicYears as $id => $year)
                                                <option value="{{ $id }}">{{ $year }}</option>
                                            @endforeach
                                        </select>
                                        @error('studentForm.entry_academic_year_id') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Nomor Induk Mahasiswa (NIM)</label>
                                        <input type="text" class="form-control" wire:model="studentForm.nim" placeholder="Contoh: 2024001" disabled>
                                        @error('studentForm.nim') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Tahun Masuk</label>
                                        <input type="number" class="form-control" wire:model="studentForm.entry_year" placeholder="Contoh: 2024" min="1900" max="{{ date('Y') }}" disabled>
                                        @error('studentForm.entry_year') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Semester Saat Ini</label>
                                        <input type="number" class="form-control" wire:model="studentForm.current_semester" placeholder="Contoh: 1" min="1" max="14" disabled>
                                        @error('studentForm.current_semester') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Status Akademik</label>
                                        <select class="form-select" wire:model="studentForm.academic_status" disabled>
                                            <option value="">Pilih Status</option>
                                            <option value="Aktif">Aktif</option>
                                            <option value="Cuti">Cuti</option>
                                            <option value="Lulus">Lulus</option>
                                            <option value="Drop Out">Drop Out</option>
                                            <option value="Nonaktif">Nonaktif</option>
                                            <option value="Keluar">Keluar</option>
                                        </select>
                                        @error('studentForm.academic_status') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Tanggal Masuk</label>
                                        <input type="date" class="form-control" wire:model="studentForm.entry_date" disabled>
                                        @error('studentForm.entry_date') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Tanggal Lulus</label>
                                        <input type="date" class="form-control" wire:model="studentForm.graduation_date" disabled>
                                        @error('studentForm.graduation_date') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" wire:model="studentForm.is_active" disabled>
                                            <label class="form-check-label">Status Aktif</label>
                                        </div>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Keterangan</label>
                                        <textarea class="form-control" wire:model="studentForm.desc" rows="3" placeholder="Tambahkan catatan jika diperlukan" disabled></textarea>
                                        @error('studentForm.desc') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Tab Profil Dosen -->
                        @if(session('active_role') === 'lecturer')
                        <div class="tab-pane {{ $tab === 'lecturer' ? 'active show' : '' }}" id="lecturer" role="tabpanel">
                            <div class="form-section">
                                <h5 class="mb-3">Data Kepegawaian</h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Fakultas</label>
                                        <select class="form-select" wire:model="lecturerForm.faculty_id" disabled>
                                            <option value="">Pilih Fakultas</option>
                                            @foreach($availableFaculties as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                        @error('lecturerForm.faculty_id') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Program Studi</label>
                                        <select class="form-select" wire:model="lecturerForm.study_program_id" disabled>
                                            <option value="">Pilih Program Studi</option>
                                            @foreach($availableStudyPrograms as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                        @error('lecturerForm.study_program_id') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">NIDN</label>
                                        <input type="text" class="form-control" wire:model="lecturerForm.nidn" placeholder="Nomor Induk Dosen Nasional" disabled>
                                        @error('lecturerForm.nidn') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">NIDK</label>
                                        <input type="text" class="form-control" wire:model="lecturerForm.nidk" placeholder="Nomor Induk Dosen Kopertis" disabled>
                                        @error('lecturerForm.nidk') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">NIP</label>
                                        <input type="text" class="form-control" wire:model="lecturerForm.nip" placeholder="Nomor Induk Pegawai" disabled>
                                        @error('lecturerForm.nip') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Status Kepegawaian</label>
                                        <select class="form-select" wire:model="lecturerForm.employment_status" disabled>
                                            <option value="">Pilih Status</option>
                                            <option value="Tetap">Tetap</option>
                                            <option value="Kontrak">Kontrak</option>
                                            <option value="Tidak Tetap">Tidak Tetap</option>
                                            <option value="Tamu">Tamu</option>
                                        </select>
                                        @error('lecturerForm.employment_status') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Tanggal Bergabung</label>
                                        <input type="date" class="form-control" wire:model="lecturerForm.join_date" disabled>
                                        @error('lecturerForm.join_date') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" wire:model="lecturerForm.is_active" disabled>
                                            <label class="form-check-label">Status Aktif</label>
                                        </div>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Keterangan</label>
                                        <textarea class="form-control" wire:model="lecturerForm.desc" rows="3" placeholder="Tambahkan catatan jika diperlukan" disabled></textarea>
                                        @error('lecturerForm.desc') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Submit Button -->
                    <div class="text-end mt-3">
                        <button wire:click="updateProfile" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
