<div class="row">
    <div class="form-group col-lg-6 mt-2">
        <label class="form-label required">Nama Kategori</label>
        <input type="text" class="form-control" wire:model.defer="form.name" placeholder="Contoh: Masalah KRS">
        @error('form.name') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="form-group col-lg-3 mt-2">
        <label class="form-label required">Kode</label>
        <input type="text" class="form-control" wire:model.defer="form.code" placeholder="AKD">
        @error('form.code') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="form-group col-lg-3 mt-2">
        <label class="form-label required">SLA (Jam)</label>
        <input type="number" min="1" class="form-control" wire:model.defer="form.default_sla_hours">
        @error('form.default_sla_hours') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="form-group col-lg-6 mt-2">
        <label class="form-label">Unit Default</label>
        <select class="form-select" wire:model.defer="form.default_work_unit_id">
            <option value="">Belum diarahkan</option>
            @foreach ($this->workUnits as $unit)
                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
            @endforeach
        </select>
        @error('form.default_work_unit_id') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="form-group col-lg-6 mt-2">
        <label class="form-label">Status</label>
        <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="form.is_active">
            <label class="form-check-label" for="is_active">Aktif</label>
        </div>
    </div>
    <div class="form-group col-12 mt-2">
        <label class="form-label">Deskripsi</label>
        <textarea class="form-control" rows="3" wire:model.defer="form.description"></textarea>
    </div>
    <div class="form-group col-12 mt-4 d-flex justify-content-end gap-2">
        <button class="btn btn-primary" wire:click="save"><i class="fas fa-save me-1"></i> Simpan</button>
        <button class="btn btn-secondary" wire:click="cancel">Batal</button>
    </div>
</div>
