<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Kode</label>
        <input type="text" class="form-control" wire:model.defer="form.code" placeholder="DEFAULT">
        @error('form.code') <small class="text-danger">{{ $message }}</small> @enderror
    </div>
    <div class="col-md-8">
        <label class="form-label">Nama Rubrik</label>
        <input type="text" class="form-control" wire:model.defer="form.name" placeholder="Rubrik Performa Standar">
        @error('form.name') <small class="text-danger">{{ $message }}</small> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Bobot EDOM</label>
        <input type="number" step="0.01" min="0" class="form-control" wire:model.defer="form.edom_weight">
    </div>
    <div class="col-md-3">
        <label class="form-label">Bobot Mengajar</label>
        <input type="number" step="0.01" min="0" class="form-control" wire:model.defer="form.teaching_weight">
    </div>
    <div class="col-md-3">
        <label class="form-label">Bobot Absensi</label>
        <input type="number" step="0.01" min="0" class="form-control" wire:model.defer="form.attendance_weight">
    </div>
    <div class="col-md-3">
        <label class="form-label">Bobot BKD</label>
        <input type="number" step="0.01" min="0" class="form-control" wire:model.defer="form.workload_weight">
    </div>
    <div class="col-md-4">
        <label class="form-label">Minimal Respon EDOM</label>
        <input type="number" min="1" class="form-control" wire:model.defer="form.minimum_responses">
    </div>
    <div class="col-md-4">
        <label class="form-label">Target BKD SKS</label>
        <input type="number" step="0.01" min="1" class="form-control" wire:model.defer="form.target_workload_sks">
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <label class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" wire:model.defer="form.is_active">
            <span class="form-check-label">Jadikan rubrik aktif</span>
        </label>
    </div>
    <div class="col-12">
        <label class="form-label">Catatan</label>
        <textarea class="form-control" rows="3" wire:model.defer="form.notes"></textarea>
    </div>
</div>
