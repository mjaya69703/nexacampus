<form wire:submit.prevent="save" class="row g-4">
    <div class="col-md-4">
        <label class="form-label fw-bold text-dark required">Kategori Aktivitas</label>
        <select class="form-select rounded-3 @error('form.category') is-invalid @enderror" wire:model.defer="form.category">
            <option value="teaching">Pengajaran (Pendidikan & Perkuliahan)</option>
            <option value="structural">Jabatan Struktural / Tugas Tambahan</option>
            <option value="tridharma">Tridharma (Penelitian / Pengabdian / Penunjang)</option>
        </select>
        @error('form.category') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark required">Kode Sumber (Source Code)</label>
        <input type="text" class="form-control rounded-3 @error('form.source_code') is-invalid @enderror" wire:model.defer="form.source_code" placeholder="Contoh: DEKAN / RESEARCH / KAPRODI">
        @error('form.source_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text small">Kode pengenal unik untuk pemetaan otomatis di sistem.</div>
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark required">Nama Aturan / Keterangan</label>
        <input type="text" class="form-control rounded-3 @error('form.name') is-invalid @enderror" wire:model.defer="form.name" placeholder="Contoh: Menjabat sebagai Dekan Fakultas">
        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark required">Nilai Konversi SKS</label>
        <div class="input-group">
            <input type="number" step="0.01" min="0" class="form-control rounded-start-3 @error('form.sks_value') is-invalid @enderror" wire:model.defer="form.sks_value" placeholder="1.00">
            <span class="input-group-text bg-light text-secondary rounded-end-3">SKS</span>
        </div>
        @error('form.sks_value') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text small">Poin SKS yang diakui per item / jabatan.</div>
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark">Batas Maksimum SKS (Opsional)</label>
        <div class="input-group">
            <input type="number" step="0.01" min="0" class="form-control rounded-start-3 @error('form.maximum_sks') is-invalid @enderror" wire:model.defer="form.maximum_sks" placeholder="Tidak dibatasi">
            <span class="input-group-text bg-light text-secondary rounded-end-3">SKS</span>
        </div>
        @error('form.maximum_sks') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text small">Batas atas akumulasi kuota per semester.</div>
    </div>

    <div class="col-md-4 d-flex flex-column justify-content-end pb-1">
        <div class="p-3 bg-light rounded-3 border">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input ms-0 me-3" type="checkbox" id="rule_is_active" wire:model.defer="form.is_active" style="cursor: pointer;">
                <label class="form-check-label fw-bold text-dark mb-0" for="rule_is_active" style="cursor: pointer;">Status Aturan Aktif Digunakan</label>
            </div>
        </div>
        @error('form.is_active') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-bold text-dark">Deskripsi & Landasan Regulasi (Opsional)</label>
        <textarea class="form-control rounded-3 @error('form.description') is-invalid @enderror" rows="3" wire:model.defer="form.description" placeholder="Tuliskan keterangan rinci, rujukan pasal/PO BKD, atau panduan verifikasi untuk asesor..."></textarea>
        @error('form.description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 border-top pt-4 mt-4 d-flex justify-content-end gap-2">
        <a href="{{ route('admin.organization.lecturer-workload-rules.index') }}" class="btn btn-light rounded-pill px-4 fw-medium">
            <i class="fas fa-times me-2"></i> Batal
        </a>
        <button type="submit" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium">
            <i class="fas fa-save me-2"></i> Simpan Aturan Konversi
        </button>
    </div>
</form>
