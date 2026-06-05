<div class="row row-cards">
    <div class="col-lg-6">
        <label class="form-label required">Nama Lokasi</label>
        <input type="text" class="form-control" wire:model.defer="form.name" placeholder="Kampus Utama">
        @error('form.name') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-lg-3">
        <label class="form-label required">Kode</label>
        <input type="text" class="form-control" wire:model.defer="form.code" placeholder="MAIN_CAMPUS">
        @error('form.code') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-lg-3">
        <label class="form-label required">Radius Meter</label>
        <input type="number" min="10" class="form-control" wire:model.defer="form.radius_meters">
        @error('form.radius_meters') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-lg-6">
        <label class="form-label required">Latitude</label>
        <input type="number" step="0.0000001" class="form-control" wire:model.defer="form.latitude">
        @error('form.latitude') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-lg-6">
        <label class="form-label required">Longitude</label>
        <input type="number" step="0.0000001" class="form-control" wire:model.defer="form.longitude">
        @error('form.longitude') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-12">
        <label class="form-label">Alamat</label>
        <textarea class="form-control" rows="3" wire:model.defer="form.address"></textarea>
    </div>
    <div class="col-12">
        <label class="form-check form-switch">
            <input class="form-check-input" type="checkbox" wire:model.defer="form.is_active">
            <span class="form-check-label">Aktif</span>
        </label>
    </div>
    <div class="col-12 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-primary" wire:click="save"><i class="fas fa-save me-2"></i>Simpan</button>
        <button type="button" class="btn btn-secondary" wire:click="cancel">Batal</button>
    </div>
</div>
