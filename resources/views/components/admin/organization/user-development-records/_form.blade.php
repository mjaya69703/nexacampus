<div class="row g-4" x-data="{ attachmentUploading: false, attachmentProgress: 0 }">
    <div class="col-12">
        <div class="p-3 bg-light rounded-3 border">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-2">
                <div>
                    <label class="form-label fw-bold text-dark required mb-1">Pegawai / Pemilik Riwayat Sertifikasi</label>
                    <div class="text-muted small">Pilih dosen atau tenaga kependidikan pemilik sertifikat/pelatihan ini.</div>
                </div>
                @if (! ($isEdit ?? false) && $this->selectedUser && method_exists($this, 'clearUser'))
                    <button type="button" class="btn btn-outline-secondary rounded-pill btn-sm px-3 fw-medium" wire:click="clearUser">
                        <i class="fas fa-sync me-1"></i> Ganti User
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
                <div class="border rounded-3 p-3 bg-white shadow-sm mt-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge bg-primary rounded-circle p-3 fs-6">{{ strtoupper(substr($selectedUser->name ?: $selectedUser->email, 0, 1)) }}</span>
                            <div>
                                <span class="fw-bold text-dark d-block fs-6">{{ $selectedUser->name }}</span>
                                <span class="text-secondary small">{{ $selectedMeta }}</span>
                            </div>
                        </div>
                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill">Terpilih</span>
                    </div>
                </div>
            @else
                <input type="search" class="form-control rounded-3 mt-2 @error('selectedUserId') is-invalid @enderror" wire:model.live.debounce.350ms="userSearch" placeholder="Cari nama pegawai, email, username, atau NIDN/NIM...">

                @if (filled($userSearch))
                    <div class="list-group mt-2 border rounded-3 shadow-sm overflow-hidden">
                        @forelse ($this->searchableUsers as $candidate)
                            @php
                                $candidateMeta = collect([$candidate->email, $candidate->username, $candidate->code, $candidate->identity_number ?? null])
                                    ->filter()
                                    ->implode(' - ');
                            @endphp
                            <button type="button" class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center" wire:click="selectUser({{ $candidate->id }})">
                                <div>
                                    <strong class="text-dark d-block">{{ $candidate->name }}</strong>
                                    <span class="text-secondary small">{{ $candidateMeta }}</span>
                                </div>
                                <i class="fas fa-check-circle text-primary"></i>
                            </button>
                        @empty
                            <div class="list-group-item text-muted text-center py-3 small">Pegawai tidak ditemukan.</div>
                        @endforelse
                    </div>
                @endif
            @endif

            @error('selectedUserId') <div class="invalid-feedback d-block mt-1">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark required">Jenis Pengembangan / Kegiatan</label>
        <select class="form-select rounded-3 @error('form.type') is-invalid @enderror" wire:model.defer="form.type">
            <option value="certification">Sertifikasi Profesi & Kompetensi</option>
            <option value="training">Pelatihan & Kursus (Training)</option>
            <option value="workshop">Workshop & Lokakarya</option>
            <option value="seminar">Seminar & Konferensi</option>
            <option value="award">Penghargaan / Prestasi (Award)</option>
            <option value="license">Lisensi & Izin Praktik Kerja</option>
        </select>
        @error('form.type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark required">Judul Kegiatan / Nama Sertifikat</label>
        <input type="text" class="form-control rounded-3 @error('form.title') is-invalid @enderror" wire:model.defer="form.title" placeholder="Contoh: AWS Certified Solutions Architect / Pelatihan Pekerti">
        @error('form.title') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark">Lembaga Penyelenggara / Penerbit</label>
        <input type="text" class="form-control rounded-3 @error('form.organizer') is-invalid @enderror" wire:model.defer="form.organizer" placeholder="Contoh: BNSP / Kemendikbud / Amazon Web Services">
        @error('form.organizer') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark">Nomor Kredensial / Sertifikat</label>
        <input type="text" class="form-control rounded-3 @error('form.credential_number') is-invalid @enderror" wire:model.defer="form.credential_number" placeholder="Tuliskan nomor seri, ID kredensial, atau nomor SK...">
        @error('form.credential_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark required">Tanggal Pelaksanaan / Terbit</label>
        <input type="date" class="form-control rounded-3 @error('form.start_date') is-invalid @enderror" wire:model.defer="form.start_date">
        @error('form.start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark">Tanggal Selesai Kegiatan</label>
        <input type="date" class="form-control rounded-3 @error('form.end_date') is-invalid @enderror" wire:model.defer="form.end_date">
        @error('form.end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark">Berlaku Sampai (Kedaluwarsa)</label>
        <input type="date" class="form-control rounded-3 @error('form.expires_at') is-invalid @enderror" wire:model.defer="form.expires_at">
        @error('form.expires_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text small">Kosongkan jika sertifikat berlaku seumur hidup.</div>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark">Biaya Kegiatan (Jika Ada / Diklaim)</label>
        <div class="input-group">
            <span class="input-group-text bg-light text-secondary rounded-start-3">Rp</span>
            <input type="number" min="0" step="0.01" class="form-control rounded-end-3 @error('form.cost') is-invalid @enderror" wire:model.defer="form.cost" placeholder="0">
        </div>
        @error('form.cost') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6"
        x-on:livewire-upload-start="attachmentUploading = true; attachmentProgress = 1"
        x-on:livewire-upload-finish="attachmentUploading = false; attachmentProgress = 100"
        x-on:livewire-upload-cancel="attachmentUploading = false; attachmentProgress = 0"
        x-on:livewire-upload-error="attachmentUploading = false; attachmentProgress = 0"
        x-on:livewire-upload-progress="attachmentProgress = $event.detail.progress">
        <label class="form-label fw-bold text-dark">Unggah Berkas Sertifikat / Piagam</label>
        <input type="file" class="form-control rounded-3 @error('attachmentFile') is-invalid @enderror" wire:model="attachmentFile" accept=".pdf,.jpg,.jpeg,.png,.webp">
        <div class="form-text small">Format supported: PDF, JPG, PNG. Maksimal 5MB. Disimpan privat.</div>
        <div class="mt-2" x-show="attachmentUploading || attachmentProgress === 100" x-transition>
            <div class="progress progress-sm rounded-pill mb-1">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" x-bind:style="`width: ${attachmentProgress}%`"></div>
            </div>
            <div class="small text-secondary" x-text="attachmentProgress === 100 ? 'File siap disimpan.' : `Mengunggah (${attachmentProgress}%)...`"></div>
        </div>
        @error('attachmentFile') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    @if (($isEdit ?? false) && $this->record->attachments->isNotEmpty())
        <div class="col-12">
            <label class="form-label fw-bold text-dark">Lampiran Berkas Saat Ini</label>
            <div class="list-group list-group-flush border rounded-3 overflow-hidden shadow-sm">
                @foreach ($this->record->attachments as $attachment)
                    <a class="list-group-item list-group-item-action py-3 px-3 d-flex align-items-center justify-content-between" href="{{ route('admin.organization.user-development-records.attachments.preview', $attachment) }}" target="_blank">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-file-alt text-primary fs-5"></i>
                            <span class="fw-medium text-dark">{{ $attachment->file_name }}</span>
                        </div>
                        <span class="badge bg-light text-secondary rounded-pill px-3 py-1">{{ number_format($attachment->file_size / 1024, 1) }} KB</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="col-12">
        <label class="form-label fw-bold text-dark">Keterangan & Uraian Kompetensi (Opsional)</label>
        <textarea class="form-control rounded-3 @error('form.description') is-invalid @enderror" rows="3" wire:model.defer="form.description" placeholder="Tuliskan keterangan tambahan, modul yang dipelajari, atau rincian pencapaian..."></textarea>
        @error('form.description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 d-flex flex-column justify-content-end pb-1">
        <div class="p-3 bg-light rounded-3 border">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input ms-0 me-3" type="checkbox" id="development_is_verified" wire:model.defer="form.is_verified" style="cursor: pointer;">
                <label class="form-check-label fw-bold text-dark mb-0" for="development_is_verified" style="cursor: pointer;">Dokumen Sudah Diverifikasi Keabsahannya</label>
            </div>
        </div>
        @error('form.is_verified') <span class="text-danger small d-block mt-1">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark">Catatan Verifikasi Asesor / SDM</label>
        <textarea class="form-control rounded-3 @error('form.verification_notes') is-invalid @enderror" rows="2" wire:model.defer="form.verification_notes" placeholder="Catatan hasil verifikasi atau alasan berkas perlu perbaikan..."></textarea>
        @error('form.verification_notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 border-top pt-4 mt-4 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" wire:click="cancel">
            <i class="fas fa-times me-2"></i> Batal
        </a>
        <button type="button" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium" wire:click="save" wire:loading.attr="disabled" wire:target="save,attachmentFile" x-bind:disabled="attachmentUploading">
            <span wire:loading.remove wire:target="save">
                <i class="fas fa-save me-2"></i> Simpan Sertifikasi
            </span>
            <span wire:loading wire:target="save">
                <i class="fas fa-spinner fa-spin me-2"></i> Menyimpan...
            </span>
        </button>
    </div>
</div>
