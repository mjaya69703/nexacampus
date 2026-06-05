<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label required">Academic Period</label>
        <select wire:model="form.academic_period_id" class="form-select">
            <option value="">Pilih periode pendaftaran yudisium</option>
            @foreach ($academicPeriods as $period)
                <option value="{{ $period->id }}">{{ $period->name }} - {{ $period->academicYear?->name ?? '-' }}</option>
            @endforeach
        </select>
        <small class="text-muted">Academic Period hanya dipakai sebagai window pendaftaran.</small>
        @error('form.academic_period_id') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Scope Program Studi</label>
        <select wire:model="form.study_program_id" class="form-select">
            <option value="">Semua Program Studi</option>
            @foreach ($studyPrograms as $program)
                <option value="{{ $program->id }}">{{ $program->name }}</option>
            @endforeach
        </select>
        <small class="text-muted">Kosongkan untuk batch lintas prodi.</small>
        @error('form.study_program_id') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-5">
        <label class="form-label required">Nama Batch</label>
        <input type="text" wire:model="form.name" class="form-control" placeholder="Yudisium Mei 2026">
        @error('form.name') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label required">Kode</label>
        <input type="text" wire:model="form.code" class="form-control" placeholder="YUD-2026-05">
        @error('form.code') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label required">Status</label>
        <select wire:model="form.status" class="form-select">
            <option value="draft">Draft</option>
            <option value="open">Open</option>
            <option value="review">Review</option>
            <option value="finalized">Finalized</option>
            <option value="cancelled">Cancelled</option>
        </select>
        @error('form.status') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Tanggal Yudisium Resmi</label>
        <input type="date" wire:model="form.yudisium_date" class="form-control">
        @error('form.yudisium_date') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Nomor SK</label>
        <input type="text" wire:model="form.sk_number" class="form-control" placeholder="Opsional">
        @error('form.sk_number') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Tanggal SK</label>
        <input type="date" wire:model="form.sk_date" class="form-control">
        @error('form.sk_date') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea wire:model="form.notes" rows="3" class="form-control"></textarea>
        @error('form.notes') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <div class="alert alert-info mb-0">
            Mahasiswa hanya bisa memilih batch berstatus <strong>Open</strong> yang academic period-nya sedang aktif. Bulk finalize mengambil tanggal dari batch ini.
        </div>
    </div>
    <div class="col-12 d-flex gap-2">
        <button type="button" wire:click="save" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Simpan Batch
        </button>
        <a href="{{ route('admin.student-services.graduation-batches.index') }}" class="btn btn-secondary">Batal</a>
    </div>
</div>
