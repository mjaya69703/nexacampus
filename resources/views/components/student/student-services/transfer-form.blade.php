<div class="row g-3 form-shell">
    <div class="col-md-4">
        <label class="form-label required">Jenis Transfer</label>
        <select wire:model.live="form.transfer_type" class="form-select">
            <option value="study_program">Pindah Program Studi</option>
            <option value="faculty">Pindah Fakultas</option>
            <option value="class_type">Pindah Kelas</option>
            <option value="other">Lainnya</option>
        </select>
        <small class="text-muted">
            Pindah prodi dibatasi fakultas yang sama; pindah fakultas wajib lintas fakultas.
        </small>
        @error('form.transfer_type') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    @if (($form['transfer_type'] ?? null) === 'faculty')
        <div class="col-md-4">
            <label class="form-label required">Fakultas Tujuan</label>
            <select wire:model.live="form.target_faculty_id" class="form-select">
                <option value="">Pilih fakultas tujuan</option>
                @foreach ($faculties as $faculty)
                    <option value="{{ $faculty->id }}">{{ $faculty->name }}</option>
                @endforeach
            </select>
            <small class="text-muted">Setelah fakultas dipilih, prodi tujuan akan difilter otomatis.</small>
            @error('form.target_faculty_id') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
    @endif
    <div class="{{ ($form['transfer_type'] ?? null) === 'faculty' ? 'col-md-4' : 'col-md-6' }}">
        <label class="form-label required">
            {{ ($form['transfer_type'] ?? null) === 'class_type' ? 'Program Studi Saat Ini' : 'Program Studi Tujuan' }}
        </label>
        <select wire:model="form.to_study_program_id" class="form-select" @disabled(($form['transfer_type'] ?? null) === 'class_type' || (($form['transfer_type'] ?? null) === 'faculty' && blank($form['target_faculty_id'] ?? null)))>
            <option value="">{{ ($form['transfer_type'] ?? null) === 'faculty' ? 'Pilih prodi di fakultas tujuan' : 'Pilih program studi tujuan' }}</option>
            @foreach ($studyPrograms as $program)
                <option value="{{ $program->id }}">{{ $program->name }} - {{ $program->faculty?->name ?? '-' }}</option>
            @endforeach
        </select>
        @if (($form['transfer_type'] ?? null) === 'class_type')
            <small class="text-muted">Pindah kelas tidak mengubah fakultas atau program studi.</small>
        @elseif (($form['transfer_type'] ?? null) === 'study_program')
            <small class="text-muted">Yang ditampilkan hanya prodi dalam fakultas yang sama.</small>
        @elseif (($form['transfer_type'] ?? null) === 'faculty')
            <small class="text-muted">Yang ditampilkan hanya prodi dari fakultas berbeda.</small>
        @endif
        @error('form.to_study_program_id') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    @if (($form['transfer_type'] ?? null) === 'class_type')
        <div class="col-md-2">
            <label class="form-label required">Kelas Tujuan</label>
            <select wire:model="form.to_class_type" class="form-select">
                <option value="">Pilih kelas</option>
                @foreach ($classTypeOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('form.to_class_type') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
    @endif
    <div class="col-md-3">
        <label class="form-label">Semester Rekomendasi</label>
        <input type="number" min="1" max="14" wire:model="form.recommended_semester" class="form-control">
        <small class="text-muted">Opsional. Admin bisa ubah saat evaluasi.</small>
        @error('form.recommended_semester') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label class="form-label">Lampiran Pendukung</label>
        @isset($request)
            @if ($request->attachment_path)
                <div class="mb-2">
                    <a href="{{ $this->fileUrl($request->attachment_path) }}" target="_blank" class="badge bg-blue-lt text-blue text-decoration-none">
                        <i class="fas fa-paperclip me-1"></i> Lampiran saat ini
                    </a>
                </div>
            @endif
        @endisset
        <input type="file" wire:model="attachment" class="form-control">
        <small class="text-muted">PDF/JPG/PNG, maksimal 5MB.</small>
        @error('attachment') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label class="form-label required">Alasan Pindah</label>
        <textarea wire:model="form.reason" rows="4" class="form-control" placeholder="Jelaskan alasan pindah program atau kelas."></textarea>
        @error('form.reason') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label class="form-label">Catatan Tambahan</label>
        <textarea wire:model="form.student_notes" rows="3" class="form-control"></textarea>
        @error('form.student_notes') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-12 d-flex gap-2">
        <button type="submit" class="action-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <i class="fas fa-paper-plane me-1"></i> {{ $submitLabel }}
        </button>
        <a href="{{ route('student.student-services.transfers') }}" class="action-btn" style="background: #e5e7eb; color: #374151;">Cancel</a>
    </div>
</div>
