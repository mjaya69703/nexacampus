<div class="row">
    <div class="form-group col-lg-6 mt-2">
        <label class="form-label required">Nama Jabatan</label>
        <input type="text" class="form-control" wire:model.defer="form.name" placeholder="Contoh: Kaprodi">
        @error('form.name') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6 mt-2">
        <label class="form-label required">Kode</label>
        <input type="text" class="form-control" wire:model.defer="form.code" placeholder="Contoh: KAPRODI">
        @error('form.code') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6 mt-3">
        <label class="form-label required">Kategori</label>
        <select class="form-select" wire:model.defer="form.category">
            <option value="executive">Pimpinan Kampus</option>
            <option value="faculty">Fakultas</option>
            <option value="study_program">Program Studi</option>
            <option value="work_unit">Unit Kerja</option>
            <option value="staff">Staff</option>
            <option value="academic">Akademik</option>
        </select>
        @error('form.category') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6 mt-3">
        <label class="form-label required">Scope Data</label>
        <select class="form-select" wire:model.defer="form.scope_type">
            <option value="none">Tanpa Scope</option>
            <option value="faculty">Fakultas</option>
            <option value="study_program">Program Studi</option>
            <option value="work_unit">Unit Kerja</option>
        </select>
        @error('form.scope_type') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-12 mt-3">
        <label class="form-label">Deskripsi</label>
        <textarea class="form-control" rows="3" wire:model.defer="form.description"></textarea>
        @error('form.description') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-12 mt-3">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="position_is_active" wire:model.defer="form.is_active">
            <label class="form-check-label" for="position_is_active">Aktif</label>
        </div>
        @error('form.is_active') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
        <button type="button" class="btn btn-primary" wire:click="save">
            <i class="fas fa-save me-2"></i> Simpan
        </button>
        <button type="button" class="btn btn-secondary" wire:click="cancel">
            <i class="fas fa-times me-2"></i> Batal
        </button>
    </div>
</div>
