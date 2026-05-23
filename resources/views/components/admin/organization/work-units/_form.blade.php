<div class="row">
    <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
        <label class="form-label required">Nama Unit</label>
        <input type="text" class="form-control" wire:model.defer="form.name" placeholder="Contoh: Bagian Akademik">
        @error('form.name') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
        <label class="form-label required">Kode</label>
        <input type="text" class="form-control" wire:model.defer="form.code" placeholder="Contoh: BAAK">
        @error('form.code') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-12 mt-2">
        <label class="form-label">Deskripsi</label>
        <textarea class="form-control" rows="3" wire:model.defer="form.description" placeholder="Catatan fungsi unit kerja"></textarea>
        @error('form.description') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-12 mt-2">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="form.is_active">
            <label class="form-check-label" for="is_active">Aktif</label>
        </div>
        @error('form.is_active') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 mt-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div>
                <h4 class="mb-0">Anggota Unit</h4>
                <small class="text-muted">Cari staff/admin berdasarkan nama, email, username, kode, atau nomor identitas. Bisa pilih satu atau banyak user sekaligus.</small>
            </div>
        </div>

        <div class="border rounded p-3 mb-3 bg-light">
            <div class="row g-2 align-items-end">
                <div class="col-lg-5">
                    <label class="form-label">Cari User</label>
                    <input type="search" class="form-control" wire:model.live.debounce.350ms="memberSearch" placeholder="Nama, email, username, kode, atau NIK">
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Posisi Default</label>
                    <select class="form-select" wire:model.defer="bulkPosition">
                        <option value="member">Anggota</option>
                        <option value="coordinator">Koordinator</option>
                        <option value="head">Kepala Unit</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="bulk_is_active" wire:model.defer="bulkIsActive">
                        <label class="form-check-label" for="bulk_is_active">Aktif</label>
                    </div>
                </div>
                <div class="col-lg-2 d-grid">
                    <button type="button" class="btn btn-outline-primary" wire:click="addSelectedMembers">
                        <i class="fas fa-user-plus me-1"></i> Tambahkan
                    </button>
                </div>
            </div>

            <div class="mt-3">
                @forelse ($this->searchableUsers as $user)
                    <label class="d-flex align-items-center gap-3 border rounded bg-white p-2 mb-2">
                        <input class="form-check-input" type="checkbox" value="{{ $user->id }}" wire:model.live="selectedUserIds">
                        <span class="flex-fill">
                            <span class="fw-semibold d-block">{{ $user->name }}</span>
                            <span class="small text-muted">{{ $user->email }}{{ $user->username ? ' - '.$user->username : '' }}{{ $user->code ? ' - '.$user->code : '' }}</span>
                        </span>
                        <span class="badge bg-light text-dark">{{ $user->role ?: 'User' }}</span>
                    </label>
                @empty
                    <div class="text-muted text-center py-3">
                        Tidak ada user yang cocok atau semua user hasil pencarian sudah ditambahkan.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="border rounded">
            @forelse ($members as $index => $member)
                @php
                    $memberUser = \App\Models\User::find($member['user_id'] ?? null);
                @endphp
                <div class="row g-2 align-items-end p-3 {{ $loop->last ? '' : 'border-bottom' }}">
                    <div class="col-lg-5">
                        <label class="form-label">User</label>
                        <div class="form-control bg-light">
                            @if ($memberUser)
                                <span class="fw-semibold">{{ $memberUser->name }}</span>
                                <span class="text-muted">- {{ $memberUser->email }}</span>
                                <span class="badge bg-light text-dark ms-1">{{ $memberUser->role ?: 'User' }}</span>
                            @else
                                User tidak ditemukan
                            @endif
                        </div>
                        <input type="hidden" wire:model.defer="members.{{ $index }}.user_id">
                        @error('members.'.$index.'.user_id') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">Posisi</label>
                        <select class="form-select" wire:model.defer="members.{{ $index }}.position">
                            <option value="member">Anggota</option>
                            <option value="coordinator">Koordinator</option>
                            <option value="head">Kepala Unit</option>
                        </select>
                        @error('members.'.$index.'.position') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-lg-2">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="member_active_{{ $index }}" wire:model.defer="members.{{ $index }}.is_active">
                            <label class="form-check-label" for="member_active_{{ $index }}">Aktif</label>
                        </div>
                    </div>
                    <div class="col-lg-2 text-lg-end">
                        <button type="button" class="btn btn-outline-danger" wire:click="removeMember({{ $index }})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-4 text-center text-muted">
                    Belum ada anggota. Unit tetap bisa disimpan dan anggota bisa ditambahkan nanti.
                </div>
            @endforelse
        </div>

        @error('members') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
        <button class="btn btn-primary" wire:click="save">
            <i class="fas fa-save me-2"></i> Simpan
        </button>
        <button class="btn btn-secondary" wire:click="cancel">
            <i class="fas fa-times me-2"></i> Batal
        </button>
    </div>
</div>
