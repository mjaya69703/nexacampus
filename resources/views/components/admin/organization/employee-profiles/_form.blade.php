<div class="row row-cards">
    <div class="col-12">
        <div class="border rounded p-3 bg-body">
                <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                    <div>
                        <label class="form-label required mb-1">User</label>
                        <div class="form-hint">Pilih akun aktif yang akan dibuatkan profil pegawai.</div>
                    </div>
                    @if ($this->selectedUser && method_exists($this, 'clearUser'))
                        <button type="button" class="btn btn-outline-secondary" wire:click="clearUser">
                            <i class="fas fa-arrows-rotate me-2"></i> Ganti User
                        </button>
                    @endif
                </div>

                @if ($this->selectedUser)
                    @php
                        $selectedUser = $this->selectedUser;
                        $selectedMeta = collect([$selectedUser->email, $selectedUser->username, $selectedUser->code, $selectedUser->identity_number ?? null])
                            ->filter()
                            ->implode(' - ');
                    @endphp
                    <div class="border rounded p-3 bg-primary-lt">
                        <div class="d-flex align-items-center gap-3">
                            <span class="avatar avatar-lg bg-primary text-primary-fg">{{ strtoupper(substr($selectedUser->name ?: $selectedUser->email, 0, 1)) }}</span>
                            <div class="flex-fill min-width-0">
                                <div class="fw-bold text-truncate">{{ $selectedUser->name }}</div>
                                <div class="text-secondary text-truncate">{{ $selectedMeta }}</div>
                            </div>
                            <span class="badge bg-primary text-primary-fg flex-shrink-0">Terpilih</span>
                        </div>
                    </div>
                @else
                    <div class="input-icon mb-3">
                        <span class="input-icon-addon">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="search" class="form-control" wire:model.live.debounce.350ms="userSearch" placeholder="Cari nama, email, username, kode, atau nomor identitas">
                    </div>

                    <div class="list-group list-group-flush border rounded">
                        @forelse ($this->searchableUsers as $candidate)
                            @php
                                $candidateMeta = collect([$candidate->email, $candidate->username, $candidate->code, $candidate->identity_number ?? null])
                                    ->filter()
                                    ->implode(' - ');
                            @endphp
                            <button type="button" class="list-group-item list-group-item-action" wire:click="selectUser({{ $candidate->id }})">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="avatar bg-primary-lt text-primary">{{ strtoupper(substr($candidate->name ?: $candidate->email, 0, 1)) }}</span>
                                    <span class="flex-fill text-start min-width-0">
                                        <span class="fw-semibold d-block text-truncate">{{ $candidate->name }}</span>
                                        <span class="text-secondary d-block text-truncate">{{ $candidateMeta }}</span>
                                    </span>
                                    <span class="text-secondary flex-shrink-0">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                </div>
                            </button>
                        @empty
                            <div class="empty my-3">
                                <div class="empty-icon">
                                    <i class="fas fa-user-slash"></i>
                                </div>
                                <p class="empty-title">User tidak ditemukan</p>
                                <p class="empty-subtitle text-secondary">
                                    Tidak ada user aktif yang cocok atau semua user sudah punya profil pegawai.
                                </p>
                            </div>
                        @endforelse
                    </div>
                @endif

                @error('selectedUserId') <div class="text-danger mt-2">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="form-group col-lg-6">
        <label class="form-label">Nomor Pegawai</label>
        <input type="text" class="form-control" wire:model.defer="form.employee_number" placeholder="Contoh: EMP-0001">
        @error('form.employee_number') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6">
        <label class="form-label required">Tipe Kepegawaian</label>
        <select class="form-select" wire:model.defer="form.employment_type">
            <option value="staff">Staff</option>
            <option value="admin_staff">Admin Staff</option>
            <option value="tendik">Tendik</option>
            <option value="lecturer">Dosen</option>
            <option value="contract">Kontrak</option>
            <option value="guest">Tamu</option>
        </select>
        @error('form.employment_type') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6">
        <label class="form-label required">Status Kepegawaian</label>
        <select class="form-select" wire:model.defer="form.employment_status">
            <option value="active">Aktif</option>
            <option value="inactive">Nonaktif</option>
            <option value="suspended">Ditangguhkan</option>
            <option value="resigned">Berhenti</option>
        </select>
        @error('form.employment_status') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6">
        <label class="form-label">Unit Kerja Utama</label>
        <select class="form-select" wire:model.defer="form.primary_work_unit_id">
            <option value="">Belum ditentukan</option>
            @foreach ($this->workUnits as $unit)
                <option value="{{ $unit->id }}">{{ $unit->name }}{{ $unit->code ? ' - '.$unit->code : '' }}</option>
            @endforeach
        </select>
        @error('form.primary_work_unit_id') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6">
        <label class="form-label">Tanggal Bergabung</label>
        <input type="date" class="form-control" wire:model.defer="form.join_date">
        @error('form.join_date') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6">
        <label class="form-label">Tanggal Selesai</label>
        <input type="date" class="form-control" wire:model.defer="form.end_date">
        @error('form.end_date') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-12">
        <label class="form-label">Catatan</label>
        <textarea class="form-control" rows="3" wire:model.defer="form.notes"></textarea>
        @error('form.notes') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="employee_is_active" wire:model.defer="form.is_active">
            <label class="form-check-label" for="employee_is_active">Aktif</label>
        </div>
        @error('form.is_active') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-12 d-flex align-items-center justify-content-end gap-2">
        <button type="button" class="btn btn-primary" wire:click="save">
            <i class="fas fa-save me-2"></i> Simpan
        </button>
        <button type="button" class="btn btn-secondary" wire:click="cancel">
            <i class="fas fa-times me-2"></i> Batal
        </button>
    </div>
</div>
