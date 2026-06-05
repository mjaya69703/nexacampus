<div class="row row-cards" x-data="{ attachmentUploading: false, attachmentProgress: 0 }">
    <div class="col-12">
        <div class="border rounded p-3 bg-body">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <label class="form-label required mb-1">User</label>
                    <div class="form-hint">Pilih pemilik sertifikasi atau riwayat pelatihan.</div>
                </div>
                @if (! ($isEdit ?? false) && $this->selectedUser && method_exists($this, 'clearUser'))
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
                            <p class="empty-subtitle text-secondary">Tidak ada user aktif yang cocok dengan pencarian.</p>
                        </div>
                    @endforelse
                </div>
            @endif

            @error('selectedUserId') <div class="text-danger mt-2">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="form-group col-lg-6">
        <label class="form-label required">Tipe</label>
        <select class="form-select" wire:model.defer="form.type">
            <option value="certification">Sertifikasi</option>
            <option value="training">Pelatihan</option>
            <option value="workshop">Workshop</option>
            <option value="seminar">Seminar</option>
            <option value="award">Penghargaan</option>
            <option value="license">Lisensi / Izin Praktik</option>
        </select>
        @error('form.type') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6">
        <label class="form-label required">Judul</label>
        <input type="text" class="form-control" wire:model.defer="form.title" placeholder="Contoh: AWS Certified Developer">
        @error('form.title') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6">
        <label class="form-label">Penyelenggara</label>
        <input type="text" class="form-control" wire:model.defer="form.organizer" placeholder="Contoh: Amazon Web Services">
        @error('form.organizer') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6">
        <label class="form-label">Nomor Kredensial</label>
        <input type="text" class="form-control" wire:model.defer="form.credential_number" placeholder="Nomor sertifikat, lisensi, atau kredensial">
        @error('form.credential_number') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-4">
        <label class="form-label required">Tanggal Mulai / Terbit</label>
        <input type="date" class="form-control" wire:model.defer="form.start_date">
        @error('form.start_date') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-4">
        <label class="form-label">Tanggal Selesai</label>
        <input type="date" class="form-control" wire:model.defer="form.end_date">
        @error('form.end_date') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-4">
        <label class="form-label">Berlaku Sampai</label>
        <input type="date" class="form-control" wire:model.defer="form.expires_at">
        @error('form.expires_at') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6">
        <label class="form-label">Biaya</label>
        <input type="number" min="0" step="0.01" class="form-control" wire:model.defer="form.cost" placeholder="0">
        @error('form.cost') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6"
        x-on:livewire-upload-start="attachmentUploading = true; attachmentProgress = 1"
        x-on:livewire-upload-finish="attachmentUploading = false; attachmentProgress = 100"
        x-on:livewire-upload-cancel="attachmentUploading = false; attachmentProgress = 0"
        x-on:livewire-upload-error="attachmentUploading = false; attachmentProgress = 0"
        x-on:livewire-upload-progress="attachmentProgress = $event.detail.progress">
        <label class="form-label">Dokumen Pendukung</label>
        <input type="file" class="form-control" wire:model="attachmentFile" accept=".pdf,.jpg,.jpeg,.png,.webp">
        <div class="form-hint">PDF, JPG, PNG, atau WEBP. Maksimal 5MB. Disimpan sebagai dokumen privat.</div>
        <div class="mt-2" x-show="attachmentUploading || attachmentProgress === 100" x-transition>
            <div class="progress progress-sm">
                <div class="progress-bar" role="progressbar" x-bind:style="`width: ${attachmentProgress}%`"></div>
            </div>
            <div class="small text-secondary mt-1" x-text="attachmentProgress === 100 ? 'File siap disimpan.' : `Mengunggah ${attachmentProgress}%`"></div>
        </div>
        <div wire:loading wire:target="attachmentFile" class="text-secondary mt-1">
            <i class="fas fa-spinner fa-spin me-1"></i> Mengunggah file...
        </div>
        @error('attachmentFile') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    @if (($isEdit ?? false) && $this->record->attachments->isNotEmpty())
        <div class="form-group col-12">
            <label class="form-label">Lampiran Tersimpan</label>
            <div class="list-group list-group-flush border rounded">
                @foreach ($this->record->attachments as $attachment)
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-3" href="{{ route('admin.organization.user-development-records.attachments.preview', $attachment) }}" target="_blank">
                        <i class="fas fa-paperclip text-secondary"></i>
                        <span class="flex-fill text-truncate">{{ $attachment->file_name }}</span>
                        <span class="text-secondary">{{ number_format($attachment->file_size / 1024, 1) }} KB</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="form-group col-12">
        <label class="form-label">Deskripsi</label>
        <textarea class="form-control" rows="3" wire:model.defer="form.description" placeholder="Catatan kegiatan, kompetensi, atau informasi tambahan"></textarea>
        @error('form.description') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="development_is_verified" wire:model.defer="form.is_verified">
            <label class="form-check-label" for="development_is_verified">Terverifikasi</label>
        </div>
        @error('form.is_verified') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-12">
        <label class="form-label">Catatan Verifikasi</label>
        <textarea class="form-control" rows="2" wire:model.defer="form.verification_notes" placeholder="Catatan pemeriksaan dokumen atau alasan belum diverifikasi"></textarea>
        @error('form.verification_notes') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-12 d-flex align-items-center justify-content-end gap-2">
        <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save,attachmentFile" x-bind:disabled="attachmentUploading">
            <span wire:loading.remove wire:target="save">
                <i class="fas fa-save me-2"></i> Simpan
            </span>
            <span wire:loading wire:target="save">
                <i class="fas fa-spinner fa-spin me-2"></i> Menyimpan
            </span>
        </button>
        <button type="button" class="btn btn-secondary" wire:click="cancel">
            <i class="fas fa-times me-2"></i> Batal
        </button>
    </div>
</div>
