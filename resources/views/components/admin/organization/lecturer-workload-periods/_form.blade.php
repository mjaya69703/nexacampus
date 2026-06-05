<form wire:submit.prevent="save" class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Nama</label>
        <input type="text" class="form-control" wire:model.defer="form.name">
        @error('form.name') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Kode</label>
        <input type="text" class="form-control" wire:model.defer="form.code">
        @error('form.code') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Status</label>
        <select class="form-select" wire:model.defer="form.status">
            <option value="draft">Draf</option>
            <option value="open">Dibuka</option>
            <option value="review">Review</option>
            <option value="closed">Ditutup</option>
        </select>
        @error('form.status') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Tahun Akademik</label>
        <select class="form-select" wire:model.defer="form.academic_year_id">
            <option value="">Tidak dibatasi</option>
            @foreach ($academicYears as $year)
                <option value="{{ $year->id }}">{{ $year->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Periode Akademik</label>
        <select class="form-select" wire:model.defer="form.academic_period_id">
            <option value="">Tidak dibatasi</option>
            @foreach ($academicPeriods as $period)
                <option value="{{ $period->id }}">{{ $period->name }} - {{ $period->type }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Tanggal Mulai</label>
        <input type="date" class="form-control" wire:model.defer="form.starts_at">
    </div>
    <div class="col-md-3">
        <label class="form-label">Tanggal Selesai</label>
        <input type="date" class="form-control" wire:model.defer="form.ends_at">
    </div>
    <div class="col-md-3">
        <label class="form-label">Minimum SKS</label>
        <input type="number" step="0.01" class="form-control" wire:model.defer="form.minimum_sks">
    </div>
    <div class="col-md-3">
        <label class="form-label">Maksimum SKS</label>
        <input type="number" step="0.01" class="form-control" wire:model.defer="form.maximum_sks">
    </div>
    <div class="col-12">
        <label class="form-label">Catatan</label>
        <textarea class="form-control" rows="3" wire:model.defer="form.notes"></textarea>
    </div>
    <div class="col-12 d-flex justify-content-end gap-2">
        <a href="{{ route('admin.organization.lecturer-workload-periods.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Kembali</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button>
    </div>
</form>
