<?php

use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\WorkUnit;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = [];
    public ?int $selectedUserId = null;
    public string $userSearch = '';

    public function mount(): void
    {
        $this->form = [
            'employee_number' => '',
            'employment_type' => 'staff',
            'employment_status' => 'active',
            'join_date' => '',
            'end_date' => '',
            'primary_work_unit_id' => '',
            'notes' => '',
            'is_active' => true,
        ];
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

        EmployeeProfile::create(array_merge($validated['form'], [
            'user_id' => $validated['selectedUserId'],
            'employee_number' => $validated['form']['employee_number'] ?: null,
            'join_date' => $validated['form']['join_date'] ?: null,
            'end_date' => $validated['form']['end_date'] ?: null,
            'primary_work_unit_id' => $validated['form']['primary_work_unit_id'] ?: null,
            'notes' => $validated['form']['notes'] ?: null,
            'created_by' => auth()->id(),
        ]));

        session()->flash('success', 'Profil pegawai berhasil dibuat.');
        $this->redirectRoute('admin.organization.employee-profiles.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.employee-profiles.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Tambah Pegawai',
        ]);
    }

    public function getSelectedUserProperty(): ?User
    {
        return $this->selectedUserId ? User::find($this->selectedUserId) : null;
    }

    public function getSearchableUsersProperty()
    {
        $usedUserIds = EmployeeProfile::query()->pluck('user_id')->all();

        return User::query()
            ->where('is_active', true)
            ->when($usedUserIds !== [], fn ($query) => $query->whereNotIn('id', $usedUserIds))
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

    public function getWorkUnitsProperty()
    {
        return WorkUnit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }

    private function rules(): array
    {
        return [
            'selectedUserId' => ['required', 'integer', 'exists:users,id', Rule::unique('employee_profiles', 'user_id')],
            'form.employee_number' => ['nullable', 'string', 'max:50', Rule::unique('employee_profiles', 'employee_number')],
            'form.employment_type' => ['required', 'string', 'in:lecturer,tendik,admin_staff,contract,guest,staff'],
            'form.employment_status' => ['required', 'string', 'in:active,inactive,suspended,resigned'],
            'form.join_date' => ['nullable', 'date'],
            'form.end_date' => ['nullable', 'date', 'after_or_equal:form.join_date'],
            'form.primary_work_unit_id' => ['nullable', 'exists:work_units,id'],
            'form.notes' => ['nullable', 'string'],
            'form.is_active' => ['boolean'],
        ];
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Tambah Pegawai Baru"
        description="Buat profil data induk kepegawaian baru untuk pengguna/akun aktif di sistem."
        icon="user-plus"
    >
        <a href="{{ route('admin.organization.employee-profiles.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.organization.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-users fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Profil Kepegawaian</h4>
                            <div class="text-muted small">Tautkan akun user, isi nomor pegawai, tipe ikatan kerja, dan unit kerja utama.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.organization.employee-profiles._form')
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
                            <h5 class="fw-bold mb-1">Panduan Registrasi</h5>
                            <div class="text-muted small">Ketentuan pendaftaran profil pegawai baru.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Satu akun pengguna (user) hanya dapat dikaitkan dengan satu profil pegawai.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Nomor Pegawai</strong> (NIP/NIDN/NIPY) harus unik dan digunakan sebagai referensi utama kepegawaian.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Unit Kerja Utama akan menjadi lokasi default absensi dan penugasan utama pegawai.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Awal</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Aktif</div>
                    <p class="text-muted small mb-0">Pegawai aktif dapat ditugaskan pada jabatan organisasi, absensi, dan pengajuan cuti.</p>
                </div>
            </div>
        </div>
    </div>
</div>
