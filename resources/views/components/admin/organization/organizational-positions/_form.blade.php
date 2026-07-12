<div class="row g-4">
    <div class="col-md-6">
        <label class="form-label fw-bold text-dark required">Nama Jabatan</label>
        <input type="text" class="form-control rounded-3 @error('form.name') is-invalid @enderror" wire:model.defer="form.name" placeholder="Contoh: Ketua Program Studi / Dekan Fakultas">
        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark required">Kode Jabatan</label>
        <input type="text" class="form-control rounded-3 @error('form.code') is-invalid @enderror" wire:model.defer="form.code" placeholder="Contoh: KAPRODI / DEKAN">
        @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text small">Kode unik (uppercase) untuk identifikasi sistem.</div>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark required">Kategori Jabatan</label>
        <select class="form-select rounded-3 @error('form.category') is-invalid @enderror" wire:model.defer="form.category">
            <option value="executive">Pimpinan Eksekutif Kampus (Rektorat / Pimpinan Utama)</option>
            <option value="faculty">Pimpinan Fakultas (Dekanat)</option>
            <option value="study_program">Pimpinan Program Studi (Kaprodi / Sekprodi)</option>
            <option value="work_unit">Kepala / Pimpinan Unit Kerja & Lembaga</option>
            <option value="staff">Staff Struktural / Administrasi</option>
            <option value="academic">Fungsional Akademik (Dosen / Senat)</option>
        </select>
        @error('form.category') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark required">Cakupan Kewenangan (Scope Data)</label>
        <select class="form-select rounded-3 @error('form.scope_type') is-invalid @enderror" wire:model.defer="form.scope_type">
            <option value="none">Tanpa Scope (Wewenang Umum / Global / Seluruh Kampus)</option>
            <option value="faculty">Scope Fakultas (Memimpin / Mengelola Fakultas Tertentu)</option>
            <option value="study_program">Scope Program Studi (Memimpin / Mengelola Prodi Tertentu)</option>
            <option value="work_unit">Scope Unit Kerja (Memimpin / Mengelola Unit Kerja Tertentu)</option>
        </select>
        @error('form.scope_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text small"><i class="fas fa-info-circle text-info me-1"></i> Menentukan entitas apa yang harus dipilih saat menugaskan pegawai ke jabatan ini.</div>
    </div>

    <div class="col-12">
        <label class="form-label fw-bold text-dark">Deskripsi & Uraian Tugas (Opsional)</label>
        <textarea class="form-control rounded-3 @error('form.description') is-invalid @enderror" rows="3" wire:model.defer="form.description" placeholder="Tuliskan uraian singkat kewenangan, tanggung jawab pokok, atau landasan SK pembentukan jabatan ini..."></textarea>
        @error('form.description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <div class="p-3 bg-light rounded-3 border d-inline-block">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input ms-0 me-3" type="checkbox" id="position_is_active" wire:model.defer="form.is_active" style="cursor: pointer;">
                <label class="form-check-label fw-bold text-dark mb-0" for="position_is_active" style="cursor: pointer;">Status Jabatan Aktif</label>
            </div>
        </div>
        @error('form.is_active') <span class="text-danger small d-block mt-1">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 border-top pt-4 mt-4 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" wire:click="cancel">
            <i class="fas fa-times me-2"></i> Batal
        </a>
        <button type="button" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium" wire:click="save">
            <i class="fas fa-save me-2"></i> Simpan Master Jabatan
        </button>
    </div>
</div>
