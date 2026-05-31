<div class="row row-cards" x-data="{ attachmentUploading: false, attachmentProgress: 0 }">
    <div class="col-12">
        <div class="border rounded p-3 bg-body">
            <label class="form-label required">Owner</label>
            @if ($this->selectedUser)
                <div class="d-flex align-items-center gap-3 border rounded p-3 bg-primary-lt">
                    <span class="avatar bg-primary text-primary-fg">{{ strtoupper(substr($this->selectedUser->name, 0, 1)) }}</span>
                    <div class="flex-fill">
                        <div class="fw-bold">{{ $this->selectedUser->name }}</div>
                        <div class="text-secondary">{{ $this->selectedUser->email }}</div>
                    </div>
                    @if (! ($isEdit ?? false))
                        <button type="button" class="btn btn-outline-secondary" wire:click="clearUser">Ganti</button>
                    @endif
                </div>
            @else
                <input type="search" class="form-control mb-2" wire:model.live.debounce.350ms="userSearch" placeholder="Cari nama, email, username, kode">
                <div class="list-group list-group-flush border rounded">
                    @forelse ($this->searchableUsers as $candidate)
                        <button type="button" class="list-group-item list-group-item-action" wire:click="selectUser({{ $candidate->id }})">
                            <div class="fw-semibold">{{ $candidate->name }}</div>
                            <div class="text-secondary">{{ $candidate->email }}</div>
                        </button>
                    @empty
                        <div class="list-group-item text-secondary">User tidak ditemukan.</div>
                    @endforelse
                </div>
            @endif
            @error('selectedUserId') <span class="text-danger">{{ $message }}</span> @enderror
        </div>
    </div>

    <div class="form-group col-lg-4">
        <label class="form-label required">Tipe</label>
        <select class="form-select" wire:model.defer="form.type">
            <option value="research">Penelitian</option>
            <option value="community_service">Pengabdian Masyarakat</option>
            <option value="publication">Publikasi / Luaran</option>
        </select>
        @error('form.type') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-8">
        <label class="form-label required">Judul</label>
        <input type="text" class="form-control" wire:model.defer="form.title">
        @error('form.title') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-4">
        <label class="form-label">Skema</label>
        <input type="text" class="form-control" wire:model.defer="form.scheme" placeholder="Internal, hibah, mandiri">
        @error('form.scheme') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-4">
        <label class="form-label">Mulai</label>
        <input type="date" class="form-control" wire:model.defer="form.starts_at">
        @error('form.starts_at') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-4">
        <label class="form-label">Selesai</label>
        <input type="date" class="form-control" wire:model.defer="form.ends_at">
        @error('form.ends_at') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6">
        <label class="form-label">Sumber Dana</label>
        <input type="text" class="form-control" wire:model.defer="form.funding_source">
        @error('form.funding_source') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6">
        <label class="form-label">Nominal Dana</label>
        <input type="number" min="0" step="0.01" class="form-control" wire:model.defer="form.funding_amount">
        @error('form.funding_amount') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-12">
        <label class="form-label">Abstrak / Deskripsi</label>
        <textarea class="form-control" rows="4" wire:model.defer="form.abstract"></textarea>
        @error('form.abstract') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6">
        <label class="form-label">Status</label>
        <select class="form-select" wire:model.defer="form.status">
            <option value="draft">Draft</option>
            <option value="approved">Approved</option>
            <option value="active">Active</option>
            <option value="completed">Completed</option>
            <option value="archived">Archived</option>
        </select>
        @error('form.status') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6"
        x-on:livewire-upload-start="attachmentUploading = true; attachmentProgress = 1"
        x-on:livewire-upload-finish="attachmentUploading = false; attachmentProgress = 100"
        x-on:livewire-upload-cancel="attachmentUploading = false; attachmentProgress = 0"
        x-on:livewire-upload-error="attachmentUploading = false; attachmentProgress = 0"
        x-on:livewire-upload-progress="attachmentProgress = $event.detail.progress">
        <label class="form-label">Lampiran Awal</label>
        <input type="file" class="form-control" wire:model="attachmentFile" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,.xlsx">
        <div class="form-hint">Proposal/evidence awal. Maksimal 8MB.</div>
        <div class="mt-2" x-show="attachmentUploading || attachmentProgress === 100" x-transition>
            <div class="progress progress-sm">
                <div class="progress-bar" x-bind:style="`width: ${attachmentProgress}%`"></div>
            </div>
            <div class="small text-secondary mt-1" x-text="attachmentProgress === 100 ? 'File siap disimpan.' : `Mengunggah ${attachmentProgress}%`"></div>
        </div>
        @error('attachmentFile') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-12">
        <label class="form-label">Catatan Admin</label>
        <textarea class="form-control" rows="2" wire:model.defer="form.admin_notes"></textarea>
        @error('form.admin_notes') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-12 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save,attachmentFile" x-bind:disabled="attachmentUploading">
            <i class="fas fa-save me-2"></i> Simpan
        </button>
        <button type="button" class="btn btn-secondary" wire:click="cancel">
            <i class="fas fa-times me-2"></i> Batal
        </button>
    </div>
</div>
