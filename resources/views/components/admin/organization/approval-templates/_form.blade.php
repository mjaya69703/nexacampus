<div class="row row-cards">
    <div class="col-lg-6">
        <label class="form-label required">Nama Template</label>
        <input type="text" class="form-control" wire:model.defer="form.name" placeholder="Contoh: Cuti Pegawai">
        @error('form.name') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-lg-3">
        <label class="form-label required">Kode</label>
        <input type="text" class="form-control" wire:model.defer="form.code" placeholder="EMPLOYEE_LEAVE">
        @error('form.code') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-lg-3">
        <label class="form-label required">Module</label>
        <input type="text" class="form-control" wire:model.defer="form.module" placeholder="organization">
        @error('form.module') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-12">
        <label class="form-label">Deskripsi</label>
        <textarea class="form-control" rows="2" wire:model.defer="form.description"></textarea>
        @error('form.description') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="template_is_active" wire:model.defer="form.is_active">
            <label class="form-check-label" for="template_is_active">Aktif</label>
        </div>
        @error('form.is_active') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div>
                <h3 class="card-title mb-1">Step Approval</h3>
                <div class="form-hint">Step berjalan berurutan dari atas ke bawah.</div>
            </div>
            <button type="button" class="btn btn-outline-primary" wire:click="addStep">
                <i class="fas fa-plus me-2"></i> Tambah Step
            </button>
        </div>

        <div class="row row-cards">
            @foreach ($steps as $index => $step)
                <div class="col-12" wire:key="approval-template-step-{{ $index }}">
                    <div class="border rounded p-3">
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary">{{ $index + 1 }}</span>
                                <strong>{{ $step['name'] ?: 'Step approval' }}</strong>
                            </div>
                            @if (count($steps) > 1)
                                <button type="button" class="btn btn-outline-danger" wire:click="removeStep({{ $index }})">
                                    <i class="fas fa-trash me-2"></i> Hapus
                                </button>
                            @endif
                        </div>

                        <div class="row g-3">
                            <div class="col-lg-4">
                                <label class="form-label required">Nama Step</label>
                                <input type="text" class="form-control" wire:model.defer="steps.{{ $index }}.name" placeholder="Contoh: Approval Atasan">
                                @error('steps.'.$index.'.name') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-lg-4">
                                <label class="form-label required">Tipe Approver</label>
                                <select class="form-select" wire:model.live="steps.{{ $index }}.approver_type">
                                    <option value="permission">Permission</option>
                                    <option value="role">Role</option>
                                    <option value="position">Jabatan</option>
                                    <option value="work_unit">Unit Kerja</option>
                                </select>
                                @error('steps.'.$index.'.approver_type') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>

                            @if ($step['approver_type'] === 'permission')
                                <div class="col-lg-4">
                                    <label class="form-label required">Permission</label>
                                    <select class="form-select" wire:model.defer="steps.{{ $index }}.approver_permission">
                                        <option value="">Pilih permission</option>
                                        @foreach ($this->permissions as $permission)
                                            <option value="{{ $permission->name }}">{{ $permission->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('steps.'.$index.'.approver_permission') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            @endif

                            @if ($step['approver_type'] === 'role')
                                <div class="col-lg-4">
                                    <label class="form-label required">Role</label>
                                    <select class="form-select" wire:model.defer="steps.{{ $index }}.approver_role">
                                        <option value="">Pilih role</option>
                                        @foreach ($this->roles as $role)
                                            <option value="{{ $role->name }}">{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('steps.'.$index.'.approver_role') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            @endif

                            @if ($step['approver_type'] === 'position')
                                <div class="col-lg-4">
                                    <label class="form-label required">Jabatan</label>
                                    <select class="form-select" wire:model.defer="steps.{{ $index }}.organizational_position_id">
                                        <option value="">Pilih jabatan</option>
                                        @foreach ($this->positions as $position)
                                            <option value="{{ $position->id }}">{{ $position->name }} - {{ $position->code }}</option>
                                        @endforeach
                                    </select>
                                    @error('steps.'.$index.'.organizational_position_id') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            @endif

                            @if ($step['approver_type'] === 'work_unit')
                                <div class="col-lg-4">
                                    <label class="form-label required">Unit Kerja</label>
                                    <select class="form-select" wire:model.defer="steps.{{ $index }}.work_unit_id">
                                        <option value="">Pilih unit kerja</option>
                                        @foreach ($this->workUnits as $unit)
                                            <option value="{{ $unit->id }}">{{ $unit->name }}{{ $unit->code ? ' - '.$unit->code : '' }}</option>
                                        @endforeach
                                    </select>
                                    @error('steps.'.$index.'.work_unit_id') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            @endif

                            <div class="col-lg-3">
                                <label class="form-label">SLA Jam</label>
                                <input type="number" min="1" class="form-control" wire:model.defer="steps.{{ $index }}.sla_hours" placeholder="Opsional">
                                @error('steps.'.$index.'.sla_hours') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-lg-3 d-flex align-items-end gap-4">
                                <label class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" wire:model.defer="steps.{{ $index }}.is_required">
                                    <span class="form-check-label">Wajib</span>
                                </label>
                                <label class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" wire:model.defer="steps.{{ $index }}.can_reject">
                                    <span class="form-check-label">Bisa reject</span>
                                </label>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Catatan Step</label>
                                <textarea class="form-control" rows="2" wire:model.defer="steps.{{ $index }}.description"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="col-12 d-flex align-items-center justify-content-end gap-2">
        <button type="button" class="btn btn-primary" wire:click="save">
            <i class="fas fa-save me-2"></i> Simpan
        </button>
        <button type="button" class="btn btn-secondary" wire:click="cancel">
            <i class="fas fa-times me-2"></i> Batal
        </button>
    </div>
</div>
