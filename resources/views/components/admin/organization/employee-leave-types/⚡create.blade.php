<?php

use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\EmployeeLeaveType;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = [];

    public function mount(): void
    {
        $this->form = [
            'name' => '',
            'code' => '',
            'approval_template_id' => '',
            'default_days_per_year' => 0,
            'requires_approval' => true,
            'is_paid' => true,
            'is_active' => true,
            'description' => '',
        ];
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());
        EmployeeLeaveType::create($this->payload($validated['form'], 'created_by'));
        session()->flash('success', 'Jenis cuti pegawai berhasil dibuat.');
        $this->redirectRoute('admin.organization.employee-leave-types.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.employee-leave-types.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Tambah Jenis Cuti Pegawai']);
    }

    public function getTemplatesProperty()
    {
        return ApprovalTemplate::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:80', Rule::unique('employee_leave_types', 'code')],
            'form.approval_template_id' => ['nullable', 'exists:approval_templates,id'],
            'form.default_days_per_year' => ['nullable', 'numeric', 'min:0'],
            'form.requires_approval' => ['boolean'],
            'form.is_paid' => ['boolean'],
            'form.is_active' => ['boolean'],
            'form.description' => ['nullable', 'string'],
        ];
    }

    private function payload(array $form, string $auditField): array
    {
        return [
            'name' => $form['name'],
            'code' => strtoupper($form['code']),
            'approval_template_id' => $form['approval_template_id'] ?: null,
            'default_days_per_year' => $form['default_days_per_year'] ?: 0,
            'requires_approval' => (bool) $form['requires_approval'],
            'is_paid' => (bool) $form['is_paid'],
            'is_active' => (bool) $form['is_active'],
            'description' => $form['description'] ?: null,
            $auditField => auth()->id(),
        ];
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Tambah Jenis Cuti Pegawai"
        description="Daftarkan klasifikasi tipe cuti baru, tentukan kuota jatah tahunan, status digaji (paid leave), serta tautkan ke template alur persetujuan."
        icon="calendar-plus"
    >
        <a href="{{ route('admin.organization.employee-leave-types.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.organization.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-calendar-check fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Jenis & Aturan Cuti</h4>
                            <div class="text-muted small">Lengkapi parameter aturan hak cuti pegawai yang akan diberlakukan di lingkungan kampus/organisasi.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.organization.employee-leave-types._form')
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
                            <h5 class="fw-bold mb-1">Pedoman Jenis Cuti</h5>
                            <div class="text-muted small">Informasi pembuatan cuti.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Tentukan <strong>Jatah Default</strong> yang akan otomatis teralokasi ke pegawai baru atau pada reset tahunan.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Aktifkan <strong>Wajib Approval</strong> jika permohonan cuti ini harus melewati persetujuan atasan atau kepegawaian.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Tautkan ke <strong>Approval Template</strong> jika memerlukan persetujuan berjenjang.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Aturan</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Paid vs Unpaid Leave</div>
                    <p class="text-muted small mb-0">Pastikan opsi "Digaji" aktif jika ketidakhadiran pegawai karena cuti ini tidak memotong gaji pokok.</p>
                </div>
            </div>
        </div>
    </div>
</div>
