<?php

use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\WorkUnit;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public EmployeeProfile $profile;
    public array $form = [];

    public function mount(int $id): void
    {
        $this->profile = EmployeeProfile::with('user')->findOrFail($id);
        $this->form = [
            'employee_number' => $this->profile->employee_number,
            'employment_type' => $this->profile->employment_type,
            'employment_status' => $this->profile->employment_status,
            'join_date' => $this->profile->join_date?->format('Y-m-d') ?? '',
            'end_date' => $this->profile->end_date?->format('Y-m-d') ?? '',
            'primary_work_unit_id' => $this->profile->primary_work_unit_id ? (string) $this->profile->primary_work_unit_id : '',
            'notes' => $this->profile->notes,
            'is_active' => (bool) $this->profile->is_active,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        $this->profile->update([
            'employee_number' => $validated['form']['employee_number'] ?: null,
            'employment_type' => $validated['form']['employment_type'],
            'employment_status' => $validated['form']['employment_status'],
            'join_date' => $validated['form']['join_date'] ?: null,
            'end_date' => $validated['form']['end_date'] ?: null,
            'primary_work_unit_id' => $validated['form']['primary_work_unit_id'] ?: null,
            'notes' => $validated['form']['notes'] ?: null,
            'is_active' => (bool) $validated['form']['is_active'],
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Profil pegawai berhasil diperbarui.');
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
            'pages' => 'Edit Pegawai',
        ]);
    }

    public function getSelectedUserProperty()
    {
        return $this->profile->user;
    }

    public function getWorkUnitsProperty()
    {
        return WorkUnit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }

    private function rules(): array
    {
        return [
            'form.employee_number' => ['nullable', 'string', 'max:50', Rule::unique('employee_profiles', 'employee_number')->ignore($this->profile->id)],
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
        title="Edit Profil Pegawai"
        description="Perbarui informasi kepegawaian untuk: {{ $profile->user?->name }} ({{ $profile->employee_number ?: 'Tanpa No. Pegawai' }})"
        icon="user-edit"
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
                            <i class="fa fa-user-edit fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Perubahan Data Pegawai</h4>
                            <div class="text-muted small">Sesuaikan nomor pegawai, status aktif/nonaktif, unit kerja, atau masa kontrak kerja.</div>
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
                            <h5 class="fw-bold mb-1">Informasi Kepegawaian</h5>
                            <div class="text-muted small">Kelola data status kerja secara berkala.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Perubahan <strong>Unit Kerja Utama</strong> akan langsung memperbarui filter absensi dan penugasan utama.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Perubahan dari Dosen ke Tendik/Staff akan mengubah akses menu fungsional akademik.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Keaktifan</div>
                    <div class="fw-bold fs-5 {{ $profile->is_active ? 'text-success' : 'text-danger' }} mb-2">{{ $profile->is_active ? 'Aktif' : 'Nonaktif' }}</div>
                    <p class="text-muted small mb-0">Jika dinonaktifkan, pegawai tidak dapat login atau menerima penugasan baru.</p>
                </div>
            </div>
        </div>
    </div>
</div>
