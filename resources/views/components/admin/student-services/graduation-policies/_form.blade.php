<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label required">Policy Name</label>
        <input type="text" class="form-control" wire:model="form.name">
        @error('form.name') <span class="text-danger small">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Study Program Scope</label>
        <select class="form-select" wire:model="form.study_program_id">
            <option value="">Global fallback</option>
            @foreach ($studyPrograms as $program)
                <option value="{{ $program->id }}">{{ $program->name }}</option>
            @endforeach
        </select>
        <small class="text-muted">Policy program studi dipakai dulu; kalau tidak ada, sistem pakai global fallback.</small>
        @error('form.study_program_id') <span class="text-danger small">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label required">Minimum Semester</label>
        <input type="number" min="1" max="20" class="form-control" wire:model="form.minimum_semester">
        @error('form.minimum_semester') <span class="text-danger small">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label required">Minimum Passed SKS</label>
        <input type="number" min="0" max="300" class="form-control" wire:model="form.minimum_passed_credits">
        @error('form.minimum_passed_credits') <span class="text-danger small">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label required">Minimum GPA</label>
        <input type="number" min="0" max="4" step="0.01" class="form-control" wire:model="form.minimum_gpa">
        @error('form.minimum_gpa') <span class="text-danger small">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-check">
            <input type="checkbox" class="form-check-input" wire:model="form.require_active_status">
            <span class="form-check-label">Require active status</span>
        </label>
    </div>
    <div class="col-md-3">
        <label class="form-check">
            <input type="checkbox" class="form-check-input" wire:model="form.require_no_financial_hold">
            <span class="form-check-label">No graduation hold</span>
        </label>
    </div>
    <div class="col-md-3">
        <label class="form-check">
            <input type="checkbox" class="form-check-input" wire:model="form.require_no_incomplete_grade">
            <span class="form-check-label">No incomplete grade</span>
        </label>
    </div>
    <div class="col-md-3">
        <label class="form-check">
            <input type="checkbox" class="form-check-input" wire:model="form.require_open_yudisium_period">
            <span class="form-check-label">Require open period</span>
        </label>
    </div>
    <div class="col-md-3">
        <label class="form-check">
            <input type="checkbox" class="form-check-input" wire:model="form.is_active">
            <span class="form-check-label">Active</span>
        </label>
    </div>
    <div class="col-12">
        <label class="form-label">Description</label>
        <textarea class="form-control" rows="3" wire:model="form.description"></textarea>
        @error('form.description') <span class="text-danger small">{{ $message }}</span> @enderror
    </div>
    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save Policy
        </button>
        <a href="{{ route('admin.student-services.graduation-policies.index') }}" class="btn btn-secondary">Cancel</a>
    </div>
</div>
