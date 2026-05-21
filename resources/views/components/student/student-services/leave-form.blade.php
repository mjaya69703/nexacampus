<div class="row g-3 form-shell">
    <div class="col-md-6">
        <label class="form-label required">Academic Year</label>
        <select wire:model="form.academic_year_id" class="form-select">
            <option value="">Pilih tahun akademik</option>
            @foreach ($academicYears as $year)
                <option value="{{ $year->id }}">{{ $year->name }}</option>
            @endforeach
        </select>
        @error('form.academic_year_id') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label required">Semester</label>
        <input type="number" min="1" max="14" wire:model="form.semester" class="form-control">
        @error('form.semester') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label required">Durasi</label>
        <select wire:model="form.duration_semesters" class="form-select">
            <option value="1">1 semester</option>
            <option value="2">2 semester</option>
        </select>
        @error('form.duration_semesters') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label required">Kategori Alasan</label>
        <select wire:model="form.reason_category" class="form-select">
            <option value="personal">Personal</option>
            <option value="medical">Medical</option>
            <option value="financial">Financial</option>
            <option value="family">Family</option>
            <option value="work">Work</option>
            <option value="other">Other</option>
        </select>
        @error('form.reason_category') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Lampiran Pendukung</label>
        @isset($application)
            @if ($application->attachment_path)
                <div class="mb-2">
                    <a href="{{ $this->fileUrl($application->attachment_path) }}" target="_blank" class="badge bg-blue-lt text-blue text-decoration-none">
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
        <label class="form-label required">Alasan Cuti</label>
        <textarea wire:model="form.reason" rows="4" class="form-control" placeholder="Jelaskan alasan cuti akademik dengan jelas."></textarea>
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
        <a href="{{ route('student.student-services.leaves') }}" class="action-btn" style="background: #e5e7eb; color: #374151;">Cancel</a>
    </div>
</div>
