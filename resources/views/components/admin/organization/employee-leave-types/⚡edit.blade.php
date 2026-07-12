<?php

use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\EmployeeLeaveType;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public EmployeeLeaveType $type;
    public array $form = [];

    public function mount(int $id): void
    {
        $this->type = EmployeeLeaveType::findOrFail($id);
        $this->form = [
            'name' => $this->type->name,
            'code' => $this->type->code,
            'approval_template_id' => $this->type->approval_template_id ? (string) $this->type->approval_template_id : '',
            'default_days_per_year' => $this->type->default_days_per_year,
            'requires_approval' => (bool) $this->type->requires_approval,
            'is_paid' => (bool) $this->type->is_paid,
            'is_active' => (bool) $this->type->is_active,
            'description' => $this->type->description,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());
        $this->type->update($this->payload($validated['form'], 'updated_by'));
        session()->flash('success', 'Jenis cuti pegawai berhasil diperbarui.');
        $this->redirectRoute('admin.organization.employee-leave-types.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.employee-leave-types.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Edit Jenis Cuti Pegawai']);
    }

    public function getTemplatesProperty()
    {
        return ApprovalTemplate::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:80', Rule::unique('employee_leave_types', 'code')->ignore($this->type->id)],
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
        title="Edit Jenis Cuti Pegawai"
        description="Perbarui nama, kode klasifikasi, jatah tahunan, atau template persetujuan untuk jenis cuti: {{ $type->name }} ({{ $type->code }})"
        icon="edit"
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
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Perubahan Aturan Cuti</h4>
                            <div class="text-muted small">Sesuaikan parameter hak dan alur persetujuan jenis cuti ini.</div>
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
                            <h5 class="fw-bold mb-1">Pedoman Perubahan</h5>
                            <div class="text-muted small">Informasi pembaruan cuti.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Perubahan <strong>Jatah Default</strong> akan berlaku untuk alokasi baru atau saat periode reset cuti berikutnya.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pengajuan cuti yang sedang diproses tidak akan terpengaruh jika Anda mengubah status <strong>Wajib Approval</strong>.</span></li>
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
