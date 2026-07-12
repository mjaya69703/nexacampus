<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Periode Akademik <span class="text-danger">*</span></label>
        <select wire:model="form.academic_period_id" class="form-select rounded-3">
            <option value="">Pilih periode pendaftaran yudisium</option>
            @foreach ($academicPeriods as $period)
                <option value="{{ $period->id }}">{{ $period->name }} - {{ $period->academicYear?->name ?? '-' }}</option>
            @endforeach
        </select>
        <small class="text-muted">Periode akademik hanya dipakai sebagai window pendaftaran.</small>
        @error('form.academic_period_id') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Scope Program Studi</label>
        <select wire:model="form.study_program_id" class="form-select rounded-3">
            <option value="">Semua Program Studi (Berlaku Umum / Lintas Prodi)</option>
            @foreach ($studyPrograms as $program)
                <option value="{{ $program->id }}">{{ $program->name }}</option>
            @endforeach
        </select>
        <small class="text-muted">Kosongkan untuk batch yudisium serentak universitas.</small>
        @error('form.study_program_id') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-5">
        <label class="form-label fw-semibold">Nama Gelombang (Batch) <span class="text-danger">*</span></label>
        <input type="text" wire:model="form.name" class="form-control rounded-3" placeholder="Contoh: Yudisium Mei 2026 Gelombang 1">
        @error('form.name') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">Kode Batch <span class="text-danger">*</span></label>
        <input type="text" wire:model="form.code" class="form-control rounded-3" placeholder="Contoh: YUD-2026-05">
        @error('form.code') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Status Batch <span class="text-danger">*</span></label>
        <select wire:model="form.status" class="form-select rounded-3">
            <option value="draft">Draft (Konsep)</option>
            <option value="open">Open (Dibuka untuk Mahasiswa)</option>
            <option value="review">Review (Masa Evaluasi Berkas)</option>
            <option value="finalized">Finalized (Selesai)</option>
            <option value="cancelled">Cancelled (Dibatalkan)</option>
        </select>
        @error('form.status') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Tanggal Yudisium Resmi</label>
        <input type="date" wire:model="form.yudisium_date" class="form-control rounded-3">
        @error('form.yudisium_date') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Nomor SK Yudisium</label>
        <input type="text" wire:model="form.sk_number" class="form-control rounded-3" placeholder="Contoh: SK/YUD/2026/05">
        @error('form.sk_number') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Tanggal SK</label>
        <input type="date" wire:model="form.sk_date" class="form-control rounded-3">
        @error('form.sk_date') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold">Catatan & Keterangan</label>
        <textarea wire:model="form.notes" rows="3" class="form-control rounded-3" placeholder="Informasi atau ketentuan khusus terkait pelaksanaan yudisium pada batch ini..."></textarea>
        @error('form.notes') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <div class="border rounded-4 p-3 bg-light bg-opacity-50">
            <div class="d-flex align-items-center gap-2 text-info mb-1">
                <i class="fa fa-info-circle"></i>
                <span class="fw-semibold">Informasi Alur Yudisium</span>
            </div>
            <div class="text-muted small">Mahasiswa hanya bisa memilih batch berstatus <strong>Open</strong> yang periode akademiknya sedang aktif. Fitur <em>Bulk Finalize</em> akan mengambil tanggal yudisium resmi dari batch ini untuk memperbarui status kelulusan mahasiswa secara massal.</div>
        </div>
    </div>
    <div class="col-12 mt-4 d-flex justify-content-end gap-2">
        <a href="{{ route('admin.student-services.graduation-batches.index') }}" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fa fa-times me-2"></i> Batal
        </a>
        <button type="button" wire:click="save" class="btn btn-primary rounded-pill px-4">
            <i class="fa fa-save me-2"></i> Simpan Batch
        </button>
    </div>
</div>
