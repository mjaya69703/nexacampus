<?php

use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\OrganizationalPosition;
use App\Models\Organization\WorkUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public array $form = [];
    public array $steps = [];

    public function mount(): void
    {
        $this->form = [
            'name' => '',
            'code' => '',
            'module' => 'organization',
            'description' => '',
            'is_active' => true,
        ];
        $this->steps = [$this->defaultStep()];
    }

    public function addStep(): void
    {
        $this->steps[] = $this->defaultStep();
    }

    public function removeStep(int $index): void
    {
        if (count($this->steps) <= 1) {
            return;
        }

        array_splice($this->steps, $index, 1);
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());
        $this->validateSteps();

        DB::transaction(function () use ($validated) {
            $template = ApprovalTemplate::create([
                'name' => $validated['form']['name'],
                'code' => strtoupper($validated['form']['code']),
                'module' => $validated['form']['module'],
                'description' => $validated['form']['description'] ?: null,
                'is_active' => (bool) $validated['form']['is_active'],
                'created_by' => auth()->id(),
            ]);

            foreach (array_values($validated['steps']) as $index => $step) {
                $template->steps()->create($this->stepPayload($step, $index + 1, 'created_by'));
            }
        });

        session()->flash('success', 'Template approval berhasil dibuat.');
        $this->redirectRoute('admin.organization.approval-templates.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.approval-templates.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Tambah Template Approval',
        ]);
    }

    public function getRolesProperty()
    {
        return Role::orderBy('name')->get(['name']);
    }

    public function getPermissionsProperty()
    {
        return Permission::orderBy('name')->get(['name']);
    }

    public function getPositionsProperty()
    {
        return OrganizationalPosition::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }

    public function getWorkUnitsProperty()
    {
        return WorkUnit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:80', Rule::unique('approval_templates', 'code')],
            'form.module' => ['required', 'string', 'max:80'],
            'form.description' => ['nullable', 'string'],
            'form.is_active' => ['boolean'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.approver_type' => ['required', 'in:role,permission,position,work_unit'],
            'steps.*.approver_role' => ['nullable', 'string', 'max:255'],
            'steps.*.approver_permission' => ['nullable', 'string', 'max:255'],
            'steps.*.organizational_position_id' => ['nullable', 'integer', 'exists:organizational_positions,id'],
            'steps.*.work_unit_id' => ['nullable', 'integer', 'exists:work_units,id'],
            'steps.*.is_required' => ['boolean'],
            'steps.*.can_reject' => ['boolean'],
            'steps.*.sla_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'steps.*.description' => ['nullable', 'string'],
        ];
    }

    private function validateSteps(): void
    {
        foreach ($this->steps as $index => $step) {
            $field = match ($step['approver_type']) {
                'role' => 'approver_role',
                'permission' => 'approver_permission',
                'position' => 'organizational_position_id',
                'work_unit' => 'work_unit_id',
            };

            if (blank($step[$field] ?? null)) {
                throw ValidationException::withMessages([
                    "steps.$index.$field" => 'Target approver wajib diisi.',
                ]);
            }
        }
    }

    private function defaultStep(): array
    {
        return [
            'name' => '',
            'approver_type' => 'permission',
            'approver_role' => '',
            'approver_permission' => '',
            'organizational_position_id' => '',
            'work_unit_id' => '',
            'is_required' => true,
            'can_reject' => true,
            'sla_hours' => '',
            'description' => '',
        ];
    }

    private function stepPayload(array $step, int $order, string $auditField): array
    {
        return [
            'step_order' => $order,
            'name' => $step['name'],
            'approver_type' => $step['approver_type'],
            'approver_role' => $step['approver_type'] === 'role' ? $step['approver_role'] : null,
            'approver_permission' => $step['approver_type'] === 'permission' ? $step['approver_permission'] : null,
            'organizational_position_id' => $step['approver_type'] === 'position' ? $step['organizational_position_id'] : null,
            'work_unit_id' => $step['approver_type'] === 'work_unit' ? $step['work_unit_id'] : null,
            'is_required' => (bool) $step['is_required'],
            'can_reject' => (bool) $step['can_reject'],
            'sla_hours' => $step['sla_hours'] ?: null,
            'description' => $step['description'] ?: null,
            $auditField => auth()->id(),
        ];
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Tambah Template Approval"
        description="Buat konfigurasi alur persetujuan baru lengkap dengan penetapan urutan langkah dan verifikator penanggung jawab."
        icon="layer-group"
    >
        <a href="{{ route('admin.organization.approval-templates.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.organization.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-diagram-project fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Konfigurasi Template</h4>
                            <div class="text-muted small">Lengkapi data utama template dan susun langkah-langkah approval sesuai kebutuhan alur kerja.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.organization.approval-templates._form')
                </div>
                <div class="card-footer bg-light bg-opacity-50 border-top p-4 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-medium d-inline-flex align-items-center gap-2" wire:click="cancel">
                        <i class="fa fa-times"></i> <span>Batal</span>
                    </button>
                    <button type="button" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium d-inline-flex align-items-center gap-2" wire:click="save">
                        <i class="fa fa-save"></i> <span>Simpan Konfigurasi</span>
                    </button>
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
                            <h5 class="fw-bold mb-1">Panduan Alur Persetujuan</h5>
                            <div class="text-muted small">Cara kerja multi-step workflow.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Berurutan (Sequential)</strong>: Setiap pengajuan akan diproses mulai dari Step 1. Jika verifikator menyetujui, pengajuan berlanjut ke Step 2, dst.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Tipe Approver</strong>: Anda dapat menentukan penanggung jawab step berdasarkan hak akses (Permission), Role, Jabatan struktural, atau Unit Kerja.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Bisa Reject & Wajib</strong>: Aktifkan opsi ini untuk memberi wewenang penolakan atau kewajiban persetujuan pada tingkatan tertentu.</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
