<div class="row g-4">
    <div class="col-12">
        <label class="form-label fw-bold text-dark required">Akun Pengguna (User)</label>
        <div class="border rounded-4 p-3 bg-light">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <span class="small text-muted d-block">Pilih akun aktif yang akan didaftarkan sebagai pegawai kampus/organisasi.</span>
                </div>
                @if ($this->selectedUser && method_exists($this, 'clearUser'))
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-medium" wire:click="clearUser">
                        <i class="fas fa-sync-alt me-1"></i> Ganti User
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
                <div class="border border-primary rounded-4 p-3 bg-primary bg-opacity-10 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-5 shadow-sm" style="width: 48px; height: 48px;">
                            {{ strtoupper(substr($selectedUser->name ?: $selectedUser->email, 0, 1)) }}
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0 fs-6">{{ $selectedUser->name }}</h6>
                            <span class="small text-muted">{{ $selectedMeta }}</span>
                        </div>
                    </div>
                    <span class="badge bg-primary text-white rounded-pill px-3 py-1">Terpilih <i class="fas fa-check ms-1"></i></span>
                </div>
            @else
                <div class="input-group mb-3">
                    <span class="input-group-text bg-white text-secondary rounded-start-3"><i class="fas fa-search"></i></span>
                    <input type="search" class="form-control rounded-end-3" wire:model.live.debounce.350ms="userSearch" placeholder="Cari nama, email, username, kode, atau NIK/nomor identitas...">
                </div>

                <div class="list-group list-group-flush border rounded-3 overflow-hidden shadow-sm bg-white">
                    @forelse ($this->searchableUsers as $candidate)
                        @php
                            $candidateMeta = collect([$candidate->email, $candidate->username, $candidate->code, $candidate->identity_number ?? null])
                                ->filter()
                                ->implode(' - ');
                        @endphp
                        <button type="button" class="list-group-item list-group-item-action py-3 px-4 d-flex justify-content-between align-items-center" wire:click="selectUser({{ $candidate->id }})">
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-light text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold fs-6 border" style="width: 40px; height: 40px;">
                                    {{ strtoupper(substr($candidate->name ?: $candidate->email, 0, 1)) }}
                                </span>
                                <span class="text-start min-width-0">
                                    <span class="fw-bold text-dark d-block text-truncate">{{ $candidate->name }}</span>
                                    <span class="small text-muted d-block text-truncate">{{ $candidateMeta }}</span>
                                </span>
                            </div>
                            <span class="badge bg-light text-primary rounded-pill px-3 py-1">Pilih <i class="fas fa-chevron-right ms-1"></i></span>
                        </button>
                    @empty
                        <div class="list-group-item text-muted text-center py-4 small">
                            <i class="fas fa-user-slash fs-4 d-block mb-1 text-secondary"></i> Tidak ada user aktif yang cocok atau semua user sudah memiliki profil pegawai.
                        </div>
                    @endforelse
                </div>
            @endif

            @error('selectedUserId') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="col-lg-6">
        <label class="form-label fw-bold text-dark">Nomor Pegawai (NIP / NIDN / NIPY)</label>
        <input type="text" class="form-control rounded-3 @error('form.employee_number') is-invalid @enderror" wire:model.defer="form.employee_number" placeholder="Contoh: EMP-2026-0001">
        @error('form.employee_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-lg-6">
        <label class="form-label fw-bold text-dark required">Tipe Ikatan Kepegawaian</label>
        <select class="form-select rounded-3 @error('form.employment_type') is-invalid @enderror" wire:model.defer="form.employment_type">
            <option value="staff">Staff Umum</option>
            <option value="admin_staff">Staff Administrasi (Admin Staff)</option>
            <option value="tendik">Tenaga Kependidikan (Tendik)</option>
            <option value="lecturer">Dosen (Tenaga Pengajar)</option>
            <option value="contract">Pegawai Kontrak / Honorer</option>
            <option value="guest">Dosen Tamu / Profesional</option>
        </select>
        @error('form.employment_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-lg-6">
        <label class="form-label fw-bold text-dark required">Status Kepegawaian</label>
        <select class="form-select rounded-3 @error('form.employment_status') is-invalid @enderror" wire:model.defer="form.employment_status">
            <option value="active">Aktif</option>
            <option value="inactive">Nonaktif / Cuti Panjang</option>
            <option value="suspended">Ditangguhkan / Skorsing</option>
            <option value="resigned">Berhenti / Resign / Pensiun</option>
        </select>
        @error('form.employment_status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-lg-6">
        <label class="form-label fw-bold text-dark">Unit Kerja Utama (Primary Work Unit)</label>
        <select class="form-select rounded-3 @error('form.primary_work_unit_id') is-invalid @enderror" wire:model.defer="form.primary_work_unit_id">
            <option value="">-- Belum Ditentukan / Tanpa Unit Utama --</option>
            @foreach ($this->workUnits as $unit)
                <option value="{{ $unit->id }}">{{ $unit->name }}{{ $unit->code ? ' ('.$unit->code.')' : '' }}</option>
            @endforeach
        </select>
        @error('form.primary_work_unit_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-lg-6">
        <label class="form-label fw-bold text-dark">Tanggal Mulai Bergabung (TMT / Join Date)</label>
        <input type="date" class="form-control rounded-3 @error('form.join_date') is-invalid @enderror" wire:model.defer="form.join_date">
        @error('form.join_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-lg-6">
        <label class="form-label fw-bold text-dark">Tanggal Selesai / Habis Kontrak (Opsional)</label>
        <input type="date" class="form-control rounded-3 @error('form.end_date') is-invalid @enderror" wire:model.defer="form.end_date">
        @error('form.end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-bold text-dark">Catatan Kepegawaian</label>
        <textarea class="form-control rounded-3 @error('form.notes') is-invalid @enderror" rows="3" wire:model.defer="form.notes" placeholder="Tuliskan catatan riwayat, nomor kontrak, atau keterangan pendukung profil pegawai ini..."></textarea>
        @error('form.notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <div class="p-3 bg-light rounded-3 border d-inline-block">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input ms-0 me-3" type="checkbox" id="employee_is_active" wire:model.defer="form.is_active" style="cursor: pointer;">
                <label class="form-check-label fw-bold text-dark mb-0" for="employee_is_active" style="cursor: pointer;">Status Profil Pegawai Aktif</label>
            </div>
        </div>
        @error('form.is_active') <span class="text-danger small d-block mt-1">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 border-top pt-4 mt-4 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" wire:click="cancel">
            <i class="fas fa-times me-2"></i> Batal
        </button>
        <button type="button" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium" wire:click="save">
            <i class="fas fa-save me-2"></i> Simpan Profil Pegawai
        </button>
    </div>
</div>
