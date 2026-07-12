<div class="row g-4" x-data="{ attachmentUploading: false, attachmentProgress: 0 }">
    <div class="col-12">
        <div class="p-3 bg-light rounded-3 border">
            <label class="form-label fw-bold text-dark required">Pegawai / Dosen Penanggung Jawab (Owner)</label>
            @if ($this->selectedUser)
                <div class="d-flex align-items-center justify-content-between border rounded-3 p-3 bg-white shadow-sm mt-2">
                    <div class="d-flex align-items-center gap-3">
                        <span class="badge bg-primary rounded-circle p-3 fs-6">{{ strtoupper(substr($this->selectedUser->name, 0, 1)) }}</span>
                        <div>
                            <span class="fw-bold text-dark d-block fs-6">{{ $this->selectedUser->name }}</span>
                            <span class="text-secondary small">{{ $this->selectedUser->email }}</span>
                        </div>
                    </div>
                    @if (! ($isEdit ?? false))
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-3 btn-sm fw-medium" wire:click="clearUser"><i class="fas fa-sync me-1"></i> Ganti Owner</button>
                    @endif
                </div>
            @else
                <input type="search" class="form-control rounded-3 mt-2 @error('selectedUserId') is-invalid @enderror" wire:model.live.debounce.350ms="userSearch" placeholder="Cari nama dosen, email, username, atau kode NIDN/NIM...">
                @if (filled($userSearch))
                    <div class="list-group mt-2 border rounded-3 shadow-sm overflow-hidden">
                        @forelse ($this->searchableUsers as $candidate)
                            <button type="button" class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center" wire:click="selectUser({{ $candidate->id }})">
                                <div>
                                    <strong class="text-dark d-block">{{ $candidate->name }}</strong>
                                    <span class="text-secondary small">{{ $candidate->email }}</span>
                                </div>
                                <i class="fas fa-check-circle text-primary"></i>
                            </button>
                        @empty
                            <div class="list-group-item text-muted py-3 text-center small">Dosen atau pegawai tidak ditemukan.</div>
                        @endforelse
                    </div>
                @endif
            @endif
            @error('selectedUserId') <div class="invalid-feedback d-block mt-1">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark required">Jenis Kegiatan Tri Dharma</label>
        <select class="form-select rounded-3 @error('form.type') is-invalid @enderror" wire:model.defer="form.type">
            <option value="research">Penelitian (Research & Studi)</option>
            <option value="community_service">Pengabdian Kepada Masyarakat (Abdimas / KKN)</option>
            <option value="publication">Publikasi Ilmiah / Luaran / HKI</option>
        </select>
        @error('form.type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-8">
        <label class="form-label fw-bold text-dark required">Judul Kegiatan / Publikasi</label>
        <input type="text" class="form-control rounded-3 @error('form.title') is-invalid @enderror" wire:model.defer="form.title" placeholder="Tuliskan judul lengkap kegiatan penelitian, pengabdian, atau luaran...">
        @error('form.title') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark">Skema Hibah / Pendanaan</label>
        <input type="text" class="form-control rounded-3 @error('form.scheme') is-invalid @enderror" wire:model.defer="form.scheme" placeholder="Contoh: Hibah Internal Kampus / PDP Kemendikbud / Mandiri">
        @error('form.scheme') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark">Tanggal Mulai Kegiatan</label>
        <input type="date" class="form-control rounded-3 @error('form.starts_at') is-invalid @enderror" wire:model.defer="form.starts_at">
        @error('form.starts_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark">Tanggal Selesai Kegiatan</label>
        <input type="date" class="form-control rounded-3 @error('form.ends_at') is-invalid @enderror" wire:model.defer="form.ends_at">
        @error('form.ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark">Lembaga / Sumber Dana</label>
        <input type="text" class="form-control rounded-3 @error('form.funding_source') is-invalid @enderror" wire:model.defer="form.funding_source" placeholder="Contoh: DRTPM Kemendikbud / LPPM / Swasta / Mandiri">
        @error('form.funding_source') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark">Nominal Dana Disetujui / Dialokasikan</label>
        <div class="input-group">
            <span class="input-group-text bg-light text-secondary rounded-start-3">Rp</span>
            <input type="number" min="0" step="0.01" class="form-control rounded-end-3 @error('form.funding_amount') is-invalid @enderror" wire:model.defer="form.funding_amount" placeholder="0">
        </div>
        @error('form.funding_amount') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-bold text-dark">Abstrak / Ringkasan Deskripsi Kegiatan</label>
        <textarea class="form-control rounded-3 @error('form.abstract') is-invalid @enderror" rows="4" wire:model.defer="form.abstract" placeholder="Tuliskan latar belakang singkat, tujuan, metodologi, dan target capaian dari kegiatan tri dharma ini..."></textarea>
        @error('form.abstract') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark required">Status Siklus Kegiatan</label>
        <select class="form-select rounded-3 @error('form.status') is-invalid @enderror" wire:model.defer="form.status">
            <option value="draft">Draft (Persiapan)</option>
            <option value="in_approval">Menunggu Approval (Proses Verifikasi)</option>
            <option value="approved">Disetujui (Approved)</option>
            <option value="active">Sedang Berjalan (Active / Pelaksanaan)</option>
            <option value="completed">Selesai (Completed / Laporan Akhir Diterima)</option>
            <option value="rejected">Ditolak / Revisi</option>
            <option value="archived">Diarsipkan (Archived)</option>
        </select>
        @error('form.status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6"
        x-on:livewire-upload-start="attachmentUploading = true; attachmentProgress = 1"
        x-on:livewire-upload-finish="attachmentUploading = false; attachmentProgress = 100"
        x-on:livewire-upload-cancel="attachmentUploading = false; attachmentProgress = 0"
        x-on:livewire-upload-error="attachmentUploading = false; attachmentProgress = 0"
        x-on:livewire-upload-progress="attachmentProgress = $event.detail.progress">
        <label class="form-label fw-bold text-dark">Lampiran Dokumen Proposal / Evidence Awal</label>
        <input type="file" class="form-control rounded-3 @error('attachmentFile') is-invalid @enderror" wire:model="attachmentFile" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,.xlsx">
        <div class="form-text small">Format didukung: PDF, Word, Excel, Gambar. Maksimal 8MB.</div>
        <div class="mt-2" x-show="attachmentUploading || attachmentProgress === 100" x-transition>
            <div class="progress progress-sm rounded-pill mb-1">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" x-bind:style="`width: ${attachmentProgress}%`"></div>
            </div>
            <div class="small text-secondary" x-text="attachmentProgress === 100 ? 'File siap disimpan bersama data kegiatan.' : `Mengunggah berkas (${attachmentProgress}%)...`"></div>
        </div>
        @error('attachmentFile') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-bold text-dark">Catatan Khusus Admin & Asesor (Internal)</label>
        <textarea class="form-control rounded-3 @error('form.admin_notes') is-invalid @enderror" rows="2" wire:model.defer="form.admin_notes" placeholder="Catatan internal yang hanya dapat dilihat oleh admin/operator pengelola tri dharma..."></textarea>
        @error('form.admin_notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 border-top pt-4 mt-4 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" wire:click="cancel">
            <i class="fas fa-times me-2"></i> Batal
        </a>
        <button type="button" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium" wire:click="save" wire:loading.attr="disabled" wire:target="save,attachmentFile" x-bind:disabled="attachmentUploading">
            <i class="fas fa-save me-2"></i> Simpan Rekor Tri Dharma
        </button>
    </div>
</div>
