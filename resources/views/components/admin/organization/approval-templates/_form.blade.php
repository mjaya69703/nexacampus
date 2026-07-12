<div class="row g-4">
    <div class="col-lg-6">
        <label class="form-label fw-bold text-dark required">Nama Template</label>
        <input type="text" class="form-control rounded-3 @error('form.name') is-invalid @enderror" wire:model.defer="form.name" placeholder="Contoh: Cuti Pegawai">
        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-lg-3">
        <label class="form-label fw-bold text-dark required">Kode Template</label>
        <input type="text" class="form-control rounded-3 @error('form.code') is-invalid @enderror" wire:model.defer="form.code" placeholder="EMPLOYEE_LEAVE">
        @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-lg-3">
        <label class="form-label fw-bold text-dark required">Module</label>
        <input type="text" class="form-control rounded-3 @error('form.module') is-invalid @enderror" wire:model.defer="form.module" placeholder="organization">
        @error('form.module') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-bold text-dark">Deskripsi Template</label>
        <textarea class="form-control rounded-3 @error('form.description') is-invalid @enderror" rows="2" wire:model.defer="form.description" placeholder="Penjelasan singkat kegunaan dan alur template ini..."></textarea>
        @error('form.description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch p-3 bg-light rounded-3 border">
            <input class="form-check-input ms-0 me-3" type="checkbox" id="template_is_active" wire:model.defer="form.is_active" style="cursor: pointer;">
            <label class="form-check-label fw-bold text-dark mb-0" for="template_is_active" style="cursor: pointer;">Aktifkan Template Approval</label>
            <span class="d-block small text-muted ms-5">Template yang aktif dapat dipilih oleh pengguna saat membuat pengajuan baru.</span>
        </div>
        @error('form.is_active') <span class="text-danger small">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 mt-5">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 border-bottom pb-3">
            <div>
                <h4 class="fw-bold mb-1 fs-5 text-dark"><i class="fas fa-list-ol text-primary me-2"></i>Daftar Step Approval</h4>
                <p class="text-muted small mb-0">Langkah persetujuan akan diproses berurutan dari nomor 1 hingga selesai.</p>
            </div>
            <button type="button" class="btn btn-outline-primary rounded-pill px-4 fw-medium" wire:click="addStep">
                <i class="fas fa-plus me-2"></i> Tambah Step Baru
            </button>
        </div>

        <div class="d-flex flex-column gap-3">
            @foreach ($steps as $index => $step)
                <div class="card border rounded-4 p-4 shadow-sm bg-light bg-opacity-50" wire:key="approval-template-step-{{ $index }}">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.9rem;">
                                {{ $index + 1 }}
                            </span>
                            <h6 class="fw-bold text-dark mb-0 fs-6">{{ $step['name'] ?: 'Step approval #' . ($index + 1) }}</h6>
                        </div>
                        @if (count($steps) > 1)
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" wire:click="removeStep({{ $index }})">
                                <i class="fas fa-trash me-1"></i> Hapus
                            </button>
                        @endif
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-4">
                            <label class="form-label small fw-bold text-dark required">Nama Step</label>
                            <input type="text" class="form-control rounded-3 @error('steps.'.$index.'.name') is-invalid @enderror" wire:model.defer="steps.{{ $index }}.name" placeholder="Contoh: Approval Atasan / Kaprodi">
                            @error('steps.'.$index.'.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label small fw-bold text-dark required">Tipe Approver</label>
                            <select class="form-select rounded-3 @error('steps.'.$index.'.approver_type') is-invalid @enderror" wire:model.live="steps.{{ $index }}.approver_type">
                                <option value="permission">Permission</option>
                                <option value="role">Role</option>
                                <option value="position">Jabatan</option>
                                <option value="work_unit">Unit Kerja</option>
                            </select>
                            @error('steps.'.$index.'.approver_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        @if ($step['approver_type'] === 'permission')
                            <div class="col-lg-4">
                                <label class="form-label small fw-bold text-dark required">Target Permission</label>
                                <select class="form-select rounded-3 @error('steps.'.$index.'.approver_permission') is-invalid @enderror" wire:model.defer="steps.{{ $index }}.approver_permission">
                                    <option value="">-- Pilih Permission --</option>
                                    @foreach ($this->permissions as $permission)
                                        <option value="{{ $permission->name }}">{{ $permission->name }}</option>
                                    @endforeach
                                </select>
                                @error('steps.'.$index.'.approver_permission') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endif

                        @if ($step['approver_type'] === 'role')
                            <div class="col-lg-4">
                                <label class="form-label small fw-bold text-dark required">Target Role</label>
                                <select class="form-select rounded-3 @error('steps.'.$index.'.approver_role') is-invalid @enderror" wire:model.defer="steps.{{ $index }}.approver_role">
                                    <option value="">-- Pilih Role --</option>
                                    @foreach ($this->roles as $role)
                                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                @error('steps.'.$index.'.approver_role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endif

                        @if ($step['approver_type'] === 'position')
                            <div class="col-lg-4">
                                <label class="form-label small fw-bold text-dark required">Target Jabatan</label>
                                <select class="form-select rounded-3 @error('steps.'.$index.'.organizational_position_id') is-invalid @enderror" wire:model.defer="steps.{{ $index }}.organizational_position_id">
                                    <option value="">-- Pilih Jabatan --</option>
                                    @foreach ($this->positions as $position)
                                        <option value="{{ $position->id }}">{{ $position->name }} ({{ $position->code }})</option>
                                    @endforeach
                                </select>
                                @error('steps.'.$index.'.organizational_position_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endif

                        @if ($step['approver_type'] === 'work_unit')
                            <div class="col-lg-4">
                                <label class="form-label small fw-bold text-dark required">Target Unit Kerja</label>
                                <select class="form-select rounded-3 @error('steps.'.$index.'.work_unit_id') is-invalid @enderror" wire:model.defer="steps.{{ $index }}.work_unit_id">
                                    <option value="">-- Pilih Unit Kerja --</option>
                                    @foreach ($this->workUnits as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->name }}{{ $unit->code ? ' ('.$unit->code.')' : '' }}</option>
                                    @endforeach
                                </select>
                                @error('steps.'.$index.'.work_unit_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endif

                        <div class="col-lg-3">
                            <label class="form-label small fw-bold text-dark">SLA (Jam)</label>
                            <input type="number" min="1" class="form-control rounded-3 @error('steps.'.$index.'.sla_hours') is-invalid @enderror" wire:model.defer="steps.{{ $index }}.sla_hours" placeholder="Opsional (Contoh: 24)">
                            @error('steps.'.$index.'.sla_hours') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-lg-4 d-flex align-items-center gap-4 pt-2">
                            <label class="form-check form-switch mb-0" style="cursor: pointer;">
                                <input class="form-check-input" type="checkbox" wire:model.defer="steps.{{ $index }}.is_required" style="cursor: pointer;">
                                <span class="form-check-label fw-medium text-dark">Wajib</span>
                            </label>
                            <label class="form-check form-switch mb-0" style="cursor: pointer;">
                                <input class="form-check-input" type="checkbox" wire:model.defer="steps.{{ $index }}.can_reject" style="cursor: pointer;">
                                <span class="form-check-label fw-medium text-dark">Bisa Reject</span>
                            </label>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold text-dark">Catatan / Instruksi untuk Approver</label>
                            <textarea class="form-control rounded-3" rows="2" wire:model.defer="steps.{{ $index }}.description" placeholder="Tuliskan petunjuk pemeriksaan untuk verifikator di langkah ini..."></textarea>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
