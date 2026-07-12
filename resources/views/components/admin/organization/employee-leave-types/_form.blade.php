<div class="row g-4">
    <div class="col-lg-6">
        <label class="form-label fw-bold text-dark required">Nama Jenis Cuti</label>
        <input type="text" class="form-control rounded-3 @error('form.name') is-invalid @enderror" wire:model.defer="form.name" placeholder="Contoh: Cuti Tahunan">
        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-lg-3">
        <label class="form-label fw-bold text-dark required">Kode Klasifikasi</label>
        <input type="text" class="form-control rounded-3 @error('form.code') is-invalid @enderror" wire:model.defer="form.code" placeholder="ANNUAL_LEAVE">
        @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-lg-3">
        <label class="form-label fw-bold text-dark">Jatah Default (Hari/Tahun)</label>
        <input type="number" step="0.5" min="0" class="form-control rounded-3 @error('form.default_days_per_year') is-invalid @enderror" wire:model.defer="form.default_days_per_year" placeholder="12">
        @error('form.default_days_per_year') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text small">Jatah hari cuti yang otomatis dialokasikan ke pegawai.</div>
    </div>

    <div class="col-lg-6">
        <label class="form-label fw-bold text-dark">Template Alur Persetujuan (Approval Template)</label>
        <select class="form-select rounded-3 @error('form.approval_template_id') is-invalid @enderror" wire:model.defer="form.approval_template_id">
            <option value="">-- Tanpa Template (Persetujuan Standar Atasan) --</option>
            @foreach ($this->templates as $template)
                <option value="{{ $template->id }}">{{ $template->name }} ({{ $template->code }})</option>
            @endforeach
        </select>
        @error('form.approval_template_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-lg-6 d-flex flex-column justify-content-end gap-3 pb-1">
        <div class="d-flex flex-wrap gap-4 p-3 bg-light rounded-3 border">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input ms-0 me-2" type="checkbox" id="requires_approval" wire:model.defer="form.requires_approval" style="cursor: pointer;">
                <label class="form-check-label fw-bold text-dark mb-0" for="requires_approval" style="cursor: pointer;">Wajib Approval</label>
            </div>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input ms-0 me-2" type="checkbox" id="is_paid" wire:model.defer="form.is_paid" style="cursor: pointer;">
                <label class="form-check-label fw-bold text-dark mb-0" for="is_paid" style="cursor: pointer;">Digaji (Paid Leave)</label>
            </div>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input ms-0 me-2" type="checkbox" id="leave_is_active" wire:model.defer="form.is_active" style="cursor: pointer;">
                <label class="form-check-label fw-bold text-dark mb-0" for="leave_is_active" style="cursor: pointer;">Aktif</label>
            </div>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label fw-bold text-dark">Deskripsi & Syarat Pengajuan</label>
        <textarea class="form-control rounded-3 @error('form.description') is-invalid @enderror" rows="3" wire:model.defer="form.description" placeholder="Jelaskan ketentuan penggunaan jenis cuti ini atau dokumen yang wajib dilampirkan..."></textarea>
        @error('form.description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 border-top pt-4 mt-4 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" wire:click="cancel">
            <i class="fas fa-times me-2"></i> Batal
        </button>
        <button type="button" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium" wire:click="save">
            <i class="fas fa-save me-2"></i> Simpan Jenis Cuti
        </button>
    </div>
</div>
