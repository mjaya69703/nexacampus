<form wire:submit.prevent="save" class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Kategori</label>
        <select class="form-select" wire:model.defer="form.category">
            <option value="teaching">Mengajar</option>
            <option value="structural">Jabatan</option>
            <option value="tridharma">Tridharma</option>
        </select>
        @error('form.category') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Kode Sumber</label>
        <input type="text" class="form-control" wire:model.defer="form.source_code" placeholder="DEKAN / RESEARCH">
        @error('form.source_code') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Nama</label>
        <input type="text" class="form-control" wire:model.defer="form.name">
        @error('form.name') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">SKS</label>
        <input type="number" step="0.01" class="form-control" wire:model.defer="form.sks_value">
        @error('form.sks_value') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Maksimum SKS</label>
        <input type="number" step="0.01" class="form-control" wire:model.defer="form.maximum_sks">
        @error('form.maximum_sks') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-3 d-flex align-items-end">
        <label class="form-check mb-2">
            <input class="form-check-input" type="checkbox" wire:model.defer="form.is_active">
            <span class="form-check-label">Aktif</span>
        </label>
    </div>
    <div class="col-12">
        <label class="form-label">Deskripsi</label>
        <textarea class="form-control" rows="3" wire:model.defer="form.description"></textarea>
    </div>
    <div class="col-12 d-flex justify-content-end gap-2">
        <a href="{{ route('admin.organization.lecturer-workload-rules.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Kembali</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button>
    </div>
</form>
