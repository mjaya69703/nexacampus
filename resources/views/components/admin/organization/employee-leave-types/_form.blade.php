<div class="row row-cards">
    <div class="col-lg-6">
        <label class="form-label required">Nama</label>
        <input type="text" class="form-control" wire:model.defer="form.name" placeholder="Cuti Tahunan">
        @error('form.name') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-lg-3">
        <label class="form-label required">Kode</label>
        <input type="text" class="form-control" wire:model.defer="form.code" placeholder="ANNUAL_LEAVE">
        @error('form.code') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-lg-3">
        <label class="form-label">Jatah/Tahun</label>
        <input type="number" step="0.5" min="0" class="form-control" wire:model.defer="form.default_days_per_year">
        @error('form.default_days_per_year') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-lg-6">
        <label class="form-label">Template Approval</label>
        <select class="form-select" wire:model.defer="form.approval_template_id">
            <option value="">Tanpa template</option>
            @foreach ($this->templates as $template)
                <option value="{{ $template->id }}">{{ $template->name }} - {{ $template->code }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-lg-6 d-flex align-items-end gap-4">
        <label class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" wire:model.defer="form.requires_approval">
            <span class="form-check-label">Butuh approval</span>
        </label>
        <label class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" wire:model.defer="form.is_paid">
            <span class="form-check-label">Paid leave</span>
        </label>
        <label class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" wire:model.defer="form.is_active">
            <span class="form-check-label">Aktif</span>
        </label>
    </div>
    <div class="col-12">
        <label class="form-label">Deskripsi</label>
        <textarea class="form-control" rows="3" wire:model.defer="form.description"></textarea>
    </div>
    <div class="col-12 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-primary" wire:click="save"><i class="fas fa-save me-2"></i>Simpan</button>
        <button type="button" class="btn btn-secondary" wire:click="cancel">Batal</button>
    </div>
</div>
