<div class="row g-4">
    <div class="col-lg-6">
        <label class="form-label fw-bold text-dark required">Nama Lokasi / Gedung</label>
        <input type="text" class="form-control rounded-3 @error('form.name') is-invalid @enderror" wire:model.defer="form.name" placeholder="Contoh: Kampus Utama Gedung Rektorat">
        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-lg-3">
        <label class="form-label fw-bold text-dark required">Kode Lokasi</label>
        <input type="text" class="form-control rounded-3 @error('form.code') is-invalid @enderror" wire:model.defer="form.code" placeholder="KAMPUS_UTAMA">
        @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-lg-3">
        <label class="form-label fw-bold text-dark required">Radius Validasi (Meter)</label>
        <input type="number" min="10" class="form-control rounded-3 @error('form.radius_meters') is-invalid @enderror" wire:model.defer="form.radius_meters" placeholder="100">
        @error('form.radius_meters') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text small">Jarak maksimal toleransi GPS dari koordinat pusat.</div>
    </div>

    <div class="col-lg-6">
        <label class="form-label fw-bold text-dark required">Latitude (Garis Lintang)</label>
        <div class="input-group">
            <span class="input-group-text bg-light text-secondary"><i class="fas fa-map-pin"></i></span>
            <input type="number" step="0.0000001" class="form-control rounded-end-3 @error('form.latitude') is-invalid @enderror" wire:model.defer="form.latitude" placeholder="Contoh: -6.200000">
            @error('form.latitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="col-lg-6">
        <label class="form-label fw-bold text-dark required">Longitude (Garis Bujur)</label>
        <div class="input-group">
            <span class="input-group-text bg-light text-secondary"><i class="fas fa-map-pin"></i></span>
            <input type="number" step="0.0000001" class="form-control rounded-end-3 @error('form.longitude') is-invalid @enderror" wire:model.defer="form.longitude" placeholder="Contoh: 106.816666">
            @error('form.longitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="col-12">
        <label class="form-label fw-bold text-dark">Alamat Lengkap</label>
        <textarea class="form-control rounded-3 @error('form.address') is-invalid @enderror" rows="3" wire:model.defer="form.address" placeholder="Tuliskan alamat jalan, kecamatan, dan kota lokasi absensi..."></textarea>
        @error('form.address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch p-3 bg-light rounded-3 border">
            <input class="form-check-input ms-0 me-3" type="checkbox" id="location_is_active" wire:model.defer="form.is_active" style="cursor: pointer;">
            <label class="form-check-label fw-bold text-dark mb-0" for="location_is_active" style="cursor: pointer;">Aktifkan Lokasi Absensi Ini</label>
            <span class="d-block small text-muted ms-5">Lokasi yang aktif akan muncul di aplikasi presensi pegawai dan dijadikan acuan validasi jarak geofence.</span>
        </div>
        @error('form.is_active') <span class="text-danger small">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 border-top pt-4 mt-4 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" wire:click="cancel">
            <i class="fas fa-times me-2"></i> Batal
        </button>
        <button type="button" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium" wire:click="save">
            <i class="fas fa-save me-2"></i> Simpan Lokasi
        </button>
    </div>
</div>
