<div class="row g-4">
    <div class="col-md-6">
        <label class="form-label fw-bold text-dark required">Nama Unit Kerja / Biro</label>
        <input type="text" class="form-control rounded-3 @error('form.name') is-invalid @enderror" wire:model.defer="form.name" placeholder="Contoh: Biro Administrasi Akademik dan Kemahasiswaan (BAAK)">
        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark required">Kode Identitas Unit (Singkatan)</label>
        <input type="text" class="form-control rounded-3 text-uppercase @error('form.code') is-invalid @enderror" wire:model.defer="form.code" placeholder="Contoh: BAAK / LPPM / UPT-TI">
        @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text small">Kode bersifat unik dan digunakan dalam kode tiket serta routing sistem.</div>
    </div>

    <div class="col-12">
        <label class="form-label fw-bold text-dark">Deskripsi & Tugas Pokok Unit Kerja</label>
        <textarea class="form-control rounded-3 @error('form.description') is-invalid @enderror" rows="3" wire:model.defer="form.description" placeholder="Tuliskan penjelasan singkat mengenai fungsi, tanggung jawab, atau cakupan layanan unit kerja ini..."></textarea>
        @error('form.description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <div class="p-3 bg-light rounded-3 border d-flex align-items-center justify-content-between">
            <div>
                <span class="fw-bold text-dark d-block">Status Keaktifan Unit Kerja</span>
                <span class="text-muted small">Jika dinonaktifkan, unit tidak akan muncul dalam pilihan penugasan tiket dan penambahan staf baru.</span>
            </div>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input ms-0" type="checkbox" id="work_unit_is_active" wire:model.defer="form.is_active" style="cursor: pointer; width: 3em; height: 1.5em;">
            </div>
        </div>
        @error('form.is_active') <span class="text-danger small d-block mt-1">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 pt-3">
        <div class="border rounded-4 p-4 bg-light shadow-sm">
            <h5 class="fw-bold text-dark mb-1"><i class="fas fa-users-cog text-primary me-2"></i>Daftarkan Anggota Pegawai ke Unit Kerja</h5>
            <p class="text-muted small mb-3">Cari dan pilih satu atau beberapa pegawai sekaligus untuk ditugaskan ke dalam struktur unit kerja ini.</p>

            <div class="row g-2 align-items-end mb-3">
                <div class="col-lg-5">
                    <label class="form-label small fw-bold text-dark">Cari Dosen / Pegawai</label>
                    <input type="search" class="form-control rounded-3 form-control-sm" wire:model.live.debounce.350ms="memberSearch" placeholder="Cari nama, email, username, atau NIK/NIDN...">
                </div>
                <div class="col-lg-3">
                    <label class="form-label small fw-bold text-dark">Posisi Penugasan Default</label>
                    <select class="form-select rounded-3 form-select-sm" wire:model.defer="bulkPosition">
                        <option value="member">Anggota Unit (Member)</option>
                        <option value="coordinator">Koordinator Bidang</option>
                        <option value="head">Kepala Unit (Head / Pimpinan)</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" id="bulk_is_active" wire:model.defer="bulkIsActive" style="cursor: pointer;">
                        <label class="form-check-label small fw-bold text-dark" for="bulk_is_active" style="cursor: pointer;">Aktif</label>
                    </div>
                </div>
                <div class="col-lg-2">
                    <button type="button" class="btn btn-sm btn-primary rounded-pill w-100 fw-medium shadow-sm" wire:click="addSelectedMembers">
                        <i class="fas fa-user-plus me-1"></i> Tambahkan
                    </button>
                </div>
            </div>

            @if (filled($memberSearch))
                <div class="list-group border rounded-3 overflow-hidden shadow-sm bg-white max-h-48 mb-3">
                    @forelse ($this->searchableUsers as $user)
                        <label class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2 px-3" style="cursor: pointer;">
                            <input class="form-check-input flex-shrink-0 my-0" type="checkbox" value="{{ $user->id }}" wire:model.live="selectedUserIds">
                            <span class="flex-fill min-width-0">
                                <strong class="text-dark d-block text-truncate small">{{ $user->name }}</strong>
                                <span class="text-muted d-block text-truncate" style="font-size: 0.75rem;">{{ $user->email }}{{ $user->username ? ' &bull; '.$user->username : '' }}</span>
                            </span>
                            <span class="badge bg-light text-dark border rounded-pill small">{{ $user->role ?: 'Staff' }}</span>
                        </label>
                    @empty
                        <div class="list-group-item text-muted text-center py-3 small">
                            Tidak ada pegawai yang cocok atau semua hasil pencarian sudah masuk ke unit ini.
                        </div>
                    @endforelse
                </div>
            @endif

            <h6 class="fw-bold text-dark mt-4 mb-2 fs-6"><i class="fas fa-list-check text-secondary me-2"></i>Daftar Anggota Unit Kerja Saat Ini ({{ count($members) }} Pegawai)</h6>
            <div class="border rounded-3 bg-white shadow-sm overflow-hidden">
                @forelse ($members as $index => $member)
                    @php
                        $memberUser = \App\Models\User::find($member['user_id'] ?? null);
                    @endphp
                    <div class="p-3 {{ $loop->last ? '' : 'border-bottom' }} d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3 min-width-0 flex-fill">
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-circle p-3 fs-6">{{ strtoupper(substr($memberUser?->name ?: 'U', 0, 1)) }}</span>
                            <div class="min-width-0">
                                @if ($memberUser)
                                    <strong class="text-dark d-block text-truncate">{{ $memberUser->name }}</strong>
                                    <span class="text-secondary small d-block text-truncate">{{ $memberUser->email }} &bull; <span class="badge bg-light text-dark border px-2 py-0">{{ $memberUser->role ?: 'User' }}</span></span>
                                @else
                                    <span class="text-danger small">User ID #{{ $member['user_id'] ?? '-' }} tidak ditemukan</span>
                                @endif
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <input type="hidden" wire:model.defer="members.{{ $index }}.user_id">
                            <select class="form-select rounded-3 form-select-sm" wire:model.defer="members.{{ $index }}.position" style="width: 160px;">
                                <option value="member">Anggota Unit</option>
                                <option value="coordinator">Koordinator Bidang</option>
                                <option value="head">Kepala Unit (Head)</option>
                            </select>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="member_active_{{ $index }}" wire:model.defer="members.{{ $index }}.is_active" style="cursor: pointer;">
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-circle p-1" style="width: 32px; height: 32px;" wire:click="removeMember({{ $index }})" title="Hapus anggota dari unit">
                                <i class="fas fa-trash small"></i>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="p-4 text-center text-muted small">
                        Belum ada anggota yang ditugaskan di unit kerja ini. Unit tetap dapat disimpan dan keanggotaan dapat diatur kapan saja.
                    </div>
                @endforelse
            </div>
            @error('members') <span class="text-danger small d-block mt-2">{{ $message }}</span> @enderror
        </div>
    </div>

    <div class="col-12 border-top pt-4 mt-4 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" wire:click="cancel">
            <i class="fas fa-times me-2"></i> Batal
        </a>
        <button type="button" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium" wire:click="save">
            <i class="fas fa-save me-2"></i> Simpan Unit Kerja
        </button>
    </div>
</div>
