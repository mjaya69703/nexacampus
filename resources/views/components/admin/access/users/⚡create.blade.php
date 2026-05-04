<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\WithFileUploads;
use App\Models\Access\Role;
use App\Models\User;

new class extends Component {
    use WithFileUploads;

    public $tab = 'biodata';
    public $userForm = [];
    public $photo = null;
    public array $availableRoles = [];
    public array $selectedRoles = [];

    public function cancel()
    {
        $this->redirect(route('admin.access.users.index'));
    }
    
    public function mount()
    {
        $this->userForm = [
            'first_name' => '',
            'last_name' => '',
            'username' => '',
            'email' => '',
            'phone' => '',
            'password' => '',
            'password_confirmation' => '',
            'instagram' => '',
            'facebook' => '',
            'linkedin' => '',
            'identity_number' => '',
            'religion' => '',
            'blood_type' => '',
            'citizenship' => '',
            'gender' => '',
            'height' => '',
            'weight' => '',
            'place_of_birth' => '',
            'date_of_birth' => '',
            'is_active' => true,
            'fst_setup' => false,
            'tfa_setup' => false,
        ];
        $this->availableRoles = Role::query()->pluck('name')->toArray();
    }

    public function createUser()
    {
        $validatedData = $this->validate([
            'userForm.first_name' => 'required|string|max:255',
            'userForm.last_name' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'userForm.username' => 'required|string|max:255|unique:users,username',
            'userForm.phone' => 'required|string|max:20|unique:users,phone',
            'userForm.email' => 'required|email|max:255|unique:users,email',
            'userForm.password' => 'required|string|min:8|confirmed',
            'userForm.password_confirmation' => 'required|string|min:8|same:userForm.password',
            'userForm.instagram' => 'nullable|string|max:255',
            'userForm.facebook' => 'nullable|string|max:255',
            'userForm.linkedin' => 'nullable|string|max:255',
            'userForm.identity_number' => 'nullable|string|max:255|unique:users,identity_number',
            'userForm.religion' => 'nullable|in:Islam,Kristen,Katolik,Hindu,Buddha,Khonghucu',
            'userForm.blood_type' => 'nullable|in:A,B,AB,O',
            'userForm.citizenship' => 'nullable|in:WNI,WNA',
            'userForm.gender' => 'nullable|in:Laki-laki,Perempuan',
            'userForm.height' => 'nullable|integer',
            'userForm.weight' => 'nullable|integer',
            'userForm.place_of_birth' => 'nullable|string|max:255',
            'userForm.date_of_birth' => 'nullable|date',
            'userForm.is_active' => 'nullable|boolean',
            'userForm.fst_setup' => 'boolean',
            'userForm.tfa_setup' => 'boolean',
            'selectedRoles' => 'nullable|array',
            'selectedRoles.*' => 'string|exists:roles,name',
        ]);

        DB::beginTransaction();

        $code = uniqid();
        $photo = 'default.jpg';
        if($this->photo) {
            $filename = 'profile_' . time() . '.' . $this->photo->getClientOriginalExtension();
            $this->photo->storeAs('images/profile', $filename, 'public');
            $photo = $filename;
        }

        $user = User::create([
            'first_name' => $validatedData['userForm']['first_name'],
            'last_name' => $validatedData['userForm']['last_name'],
            'username' => $validatedData['userForm']['username'],
            'phone' => $validatedData['userForm']['phone'],
            'email' => $validatedData['userForm']['email'],
            'password' => Hash::make($validatedData['userForm']['password']),
            'instagram' => $validatedData['userForm']['instagram'] ?: null,
            'facebook' => $validatedData['userForm']['facebook'] ?: null,
            'linkedin' => $validatedData['userForm']['linkedin'] ?: null,
            'identity_number' => $validatedData['userForm']['identity_number'] ?: null,
            'religion' => $validatedData['userForm']['religion'] ?: null,
            'blood_type' => $validatedData['userForm']['blood_type'] ?: null,
            'citizenship' => $validatedData['userForm']['citizenship'] ?: null,
            'gender' => $validatedData['userForm']['gender'] ?: null,
            'height' => $validatedData['userForm']['height'] ?: null,
            'weight' => $validatedData['userForm']['weight'] ?: null,
            'place_of_birth' => $validatedData['userForm']['place_of_birth'] ?: null,
            'date_of_birth' => $validatedData['userForm']['date_of_birth'] ?: null,
            'code' => $code,
            'photo' => $photo,
            'is_active' => $validatedData['userForm']['is_active'],
            'fst_setup' => $validatedData['userForm']['fst_setup'],
            'tfa_setup' => $validatedData['userForm']['tfa_setup'],
        ]);

        $user->syncRoles($validatedData['selectedRoles'] ?? []);

        DB::commit();

        session()->flash('success', 'User berhasil dibuat!');
        $this->redirect(route('admin.access.users.index'));
    }

    public function render()
    {
        $data = [
            'menus' => 'User Management', // Data menu
            'pages' => 'Buat User Baru', // Data halaman
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
                                <img src="https://ui-avatars.com/api/?name=New+User" alt="Profile Photo" class="profile-photo" id="previewPhoto">
                            @endif
                        </div>
                        <div class="col-md-10">
                            <h2 class="mb-0">User Baru</h2>
                            <p class="mb-1">Buat akun pengguna baru</p>
                            <p class="mb-0"><i class="fas fa-info-circle"></i> Isi semua field yang wajib diisi</p>
                        </div>
                    </div>
                </div>
                <!-- Form Create User -->
                <form wire:submit.prevent="createUser" enctype="multipart/form-data">
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
                                        <label class="form-label">Username <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" wire:model="userForm.username" placeholder="Masukkan username unik" required>
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
                                        <input type="email" class="form-control" wire:model="userForm.email" placeholder="contoh@email.com" required>
                                        @error('userForm.email') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Nomor Telepon <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" wire:model="userForm.phone" placeholder="Contoh: 081234567890" required>
                                        @error('userForm.phone') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Nomor Identitas</label>
                                        <input type="text" class="form-control" wire:model="userForm.identity_number" placeholder="Contoh: 3500000000000001">
                                        @error('userForm.identity_number') <span class="text-danger">{{ $message }}</span> @enderror
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
                                <h5 class="mb-3">Atur Password</h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" wire:model="userForm.password" placeholder="Minimal 8 karakter" required>
                                        @error('userForm.password') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" wire:model="userForm.password_confirmation" placeholder="Ulangi password" required>
                                        @error('userForm.password_confirmation') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="form-section">
                                <h5 class="mb-3">Pengaturan Khusus</h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Pilih Role</label>
                                        <div wire:ignore>
                                            <select id="roles-select" class="form-select" multiple>
                                                @foreach($availableRoles as $roleName)
                                                    <option value="{{ $roleName }}" @selected(in_array($roleName, $selectedRoles, true))>
                                                        {{ ucfirst($roleName) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @error('selectedRoles')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                        @error('selectedRoles.*')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                        <small class="text-muted">Bisa pilih lebih dari satu role</small>
                                    </div>
                                </div>
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
                    </div>

                    <!-- Submit Button -->
                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i> Buat User
                        </button>
                        <button type="button" class="btn btn-secondary" wire:click="cancel">
                            <i class="fas fa-times me-2"></i> Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('livewire:initialized', () => {
            window.initLivewireTomSelect({
                selectId: 'roles-select',
                property: 'selectedRoles',
                placeholder: 'Pilih role',
                removeButtonTitle: 'Hapus role'
            });
        });
    </script>
@endpush
