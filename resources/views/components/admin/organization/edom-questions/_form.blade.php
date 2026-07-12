<div class="row g-4">
    <div class="col-md-4">
        <label class="form-label fw-bold text-dark required">Kategori Penilaian</label>
        <select class="form-select rounded-3 @error('form.category') is-invalid @enderror" wire:model.defer="form.category">
            <option value="teaching">Pengajaran (Teaching)</option>
            <option value="material">Materi Perkuliahan (Material)</option>
            <option value="evaluation">Evaluasi & Tugas (Evaluation)</option>
            <option value="general">Umum (General)</option>
        </select>
        @error('form.category') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark required">Tipe Jawaban</label>
        <select class="form-select rounded-3 @error('form.answer_type') is-invalid @enderror" wire:model.defer="form.answer_type">
            <option value="scale">Skala Penilaian (1-5)</option>
            <option value="text">Komentar Teks Bebas</option>
        </select>
        @error('form.answer_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark required">Nomor Urut Tampil</label>
        <input type="number" min="0" class="form-control rounded-3 @error('form.sort_order') is-invalid @enderror" wire:model.defer="form.sort_order" placeholder="Contoh: 1">
        @error('form.sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-bold text-dark required">Teks Pertanyaan Evaluasi</label>
        <textarea rows="3" class="form-control rounded-3 @error('form.question_text') is-invalid @enderror" wire:model.defer="form.question_text" placeholder="Tuliskan butir pertanyaan yang akan ditanyakan kepada mahasiswa..."></textarea>
        @error('form.question_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 d-flex align-items-center gap-4 pt-2">
        <label class="form-check form-switch mb-0" style="cursor: pointer;">
            <input class="form-check-input ms-0 me-2" type="checkbox" wire:model.defer="form.is_required" style="cursor: pointer;">
            <span class="form-check-label fw-bold text-dark">Wajib Diisi Mahasiswa</span>
        </label>
        <label class="form-check form-switch mb-0" style="cursor: pointer;">
            <input class="form-check-input ms-0 me-2" type="checkbox" wire:model.defer="form.is_active" style="cursor: pointer;">
            <span class="form-check-label fw-bold text-dark">Status Aktif</span>
        </label>
    </div>
</div>
