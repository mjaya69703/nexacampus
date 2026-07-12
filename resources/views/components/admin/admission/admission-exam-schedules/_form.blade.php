<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-bold text-dark fs-7">Periode PMB <span class="text-danger">*</span></label>
        <select class="form-select rounded-3 border-secondary border-opacity-25" wire:model="form.admission_period_id">
            <option value="">-- Pilih Periode --</option>
            @foreach($periods as $period)
                <option value="{{ $period['id'] }}">{{ $period['label'] }}</option>
            @endforeach
        </select>
        @error('form.admission_period_id') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold text-dark fs-7">Jenis Seleksi / Ujian <span class="text-danger">*</span></label>
        <select class="form-select rounded-3 border-secondary border-opacity-25" wire:model="form.exam_type">
            <option value="written_test">Tes Tertulis (Written Test)</option>
            <option value="interview">Wawancara (Interview)</option>
            <option value="practical">Ujian Praktik (Practical)</option>
            <option value="portfolio">Portofolio (Portfolio)</option>
            <option value="other">Lainnya (Other)</option>
        </select>
        @error('form.exam_type') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold text-dark fs-7">Kuota Peserta <span class="text-danger">*</span></label>
        <input type="number" min="0" class="form-control rounded-3 border-secondary border-opacity-25" wire:model="form.quota" placeholder="Kapasitas">
        @error('form.quota') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-12">
        <label class="form-label fw-bold text-dark fs-7">Nama Sesi / Judul Ujian <span class="text-danger">*</span></label>
        <input type="text" class="form-control rounded-3 border-secondary border-opacity-25" wire:model="form.title" placeholder="Contoh: Sesi Wawancara Gelombang 1 - Ruang 101">
        @error('form.title') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold text-dark fs-7">Tanggal Ujian <span class="text-danger">*</span></label>
        <input type="date" class="form-control rounded-3 border-secondary border-opacity-25" wire:model="form.exam_date">
        @error('form.exam_date') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold text-dark fs-7">Waktu Mulai <span class="text-danger">*</span></label>
        <input type="time" class="form-control rounded-3 border-secondary border-opacity-25" wire:model="form.exam_time">
        @error('form.exam_time') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark fs-7">Lokasi / Ruangan Ujian</label>
        <input type="text" class="form-control rounded-3 border-secondary border-opacity-25" wire:model="form.venue" placeholder="Contoh: Gedung A Lt. 2 / Auditorium / Daring">
        @error('form.venue') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-8">
        <label class="form-label fw-bold text-dark fs-7">Tautan Daring (Zoom / GMeet)</label>
        <input type="url" class="form-control rounded-3 border-secondary border-opacity-25" wire:model="form.meeting_link" placeholder="https://zoom.us/j/123456789">
        @error('form.meeting_link') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-4 d-flex align-items-center pt-3">
        <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" id="is_active_schedule" wire:model="form.is_active">
            <label class="form-check-label fw-bold text-dark" for="is_active_schedule">Status Sesi Aktif</label>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label fw-bold text-dark fs-7">Catatan / Keterangan Tambahan</label>
        <textarea rows="3" class="form-control rounded-3 border-secondary border-opacity-25" wire:model="form.notes" placeholder="Informasi tata tertib perlengkapan atau ketentuan ujian bagi calon mahasiswa..."></textarea>
        @error('form.notes') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 d-flex justify-content-end gap-3 mt-4 pt-3 border-top border-light">
        <a href="{{ route('admin.admission.admission-exam-schedules.index') }}" class="btn btn-light rounded-pill px-4 py-2 fw-bold text-secondary border shadow-sm">
            <i class="fas fa-times me-1"></i> Batal
        </a>
        <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow-sm d-flex align-items-center gap-2">
            <i class="fas fa-save"></i> Simpan Jadwal Ujian
        </button>
    </div>
</div>
