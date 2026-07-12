<div class="row g-3">
    <div class="col-lg-6">
        <label class="form-label fw-semibold">Nama Kategori <span class="text-danger">*</span></label>
        <input type="text" class="form-control rounded-3" wire:model.defer="form.name" placeholder="Contoh: Masalah KRS">
        @error('form.name') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
    </div>
    <div class="col-lg-3">
        <label class="form-label fw-semibold">Kode <span class="text-danger">*</span></label>
        <input type="text" class="form-control rounded-3" wire:model.defer="form.code" placeholder="Contoh: AKD">
        @error('form.code') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
    </div>
    <div class="col-lg-3">
        <label class="form-label fw-semibold">SLA (Jam) <span class="text-danger">*</span></label>
        <input type="number" min="1" class="form-control rounded-3" wire:model.defer="form.default_sla_hours" placeholder="48">
        @error('form.default_sla_hours') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
    </div>
    <div class="col-lg-6">
        <label class="form-label fw-semibold">Unit Kerja Penanggung Jawab</label>
        <select class="form-select rounded-3" wire:model.defer="form.default_work_unit_id">
            <option value="">Belum diarahkan / Default Sistem</option>
            @foreach ($this->workUnits as $unit)
                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
            @endforeach
        </select>
        @error('form.default_work_unit_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold">Deskripsi / Ruang Lingkup Kategori</label>
        <textarea class="form-control rounded-3" rows="3" wire:model.defer="form.description" placeholder="Jelaskan topik pengaduan yang termasuk dalam kategori ini..."></textarea>
        @error('form.description') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
    </div>
    <div class="col-12">
        <div class="border rounded-4 p-3 bg-light bg-opacity-50">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="form.is_active">
                <label class="form-check-label fw-semibold" for="is_active">Kategori aktif dan dapat dipilih mahasiswa</label>
            </div>
            <div class="text-muted small mt-2">Kategori yang nonaktif tidak akan muncul di form pembuatan tiket baru.</div>
        </div>
    </div>
    <div class="col-12 mt-4 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="cancel">
            <i class="fa fa-times me-2"></i> Batal
        </button>
        <button type="button" class="btn btn-primary rounded-pill px-4" wire:click="save">
            <i class="fa fa-save me-2"></i> Simpan Kategori
        </button>
    </div>
</div>
