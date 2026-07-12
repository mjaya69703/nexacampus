<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-bold text-dark fs-7">Gelombang / Periode PMB <span class="text-danger">*</span></label>
        <select class="form-select rounded-3 border-secondary border-opacity-25" wire:model="form.admission_period_id">
            <option value="">-- Pilih Periode --</option>
            @foreach($periods as $period)
                <option value="{{ $period['id'] }}">{{ $period['label'] }}</option>
            @endforeach
        </select>
        @error('form.admission_period_id') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark fs-7">Fakultas (Opsional)</label>
        <select class="form-select rounded-3 border-secondary border-opacity-25" wire:model.live="form.faculty_id">
            <option value="">-- Semua Fakultas / Umum --</option>
            @foreach($faculties as $faculty)
                <option value="{{ $faculty['id'] }}">{{ $faculty['label'] }}</option>
            @endforeach
        </select>
        @error('form.faculty_id') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark fs-7">Program Studi (Opsional)</label>
        <select class="form-select rounded-3 border-secondary border-opacity-25" wire:model="form.study_program_id">
            <option value="">-- Semua Program Studi / Keseluruhan --</option>
            @foreach($studyPrograms as $program)
                <option value="{{ $program['id'] }}">{{ $program['label'] }}</option>
            @endforeach
        </select>
        @error('form.study_program_id') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold text-dark fs-7">Tipe Kelas</label>
        <select class="form-select rounded-3 border-secondary border-opacity-25" wire:model="form.class_type">
            <option value="">-- Semua Kelas --</option>
            <option value="regular">Reguler Pagi (Regular)</option>
            <option value="evening">Kelas Malam (Evening)</option>
            <option value="weekend">Kelas Akhir Pekan (Weekend)</option>
        </select>
        @error('form.class_type') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold text-dark fs-7">Daya Tampung / Kuota <span class="text-danger">*</span></label>
        <input type="number" min="1" class="form-control rounded-3 border-secondary border-opacity-25" wire:model="form.quota" placeholder="Jumlah Kursi">
        @error('form.quota') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 d-flex justify-content-end gap-3 mt-4 pt-3 border-top border-light">
        <a href="{{ route('admin.admission.admission-quotas.index') }}" class="btn btn-light rounded-pill px-4 py-2 fw-bold text-secondary border shadow-sm">
            <i class="fas fa-times me-1"></i> Batal
        </a>
        <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow-sm d-flex align-items-center gap-2">
            <i class="fas fa-save"></i> Simpan Pengaturan Kuota
        </button>
    </div>
</div>
