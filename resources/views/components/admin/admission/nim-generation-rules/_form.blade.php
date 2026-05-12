<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Rule Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" wire:model.live="form.name">
        @error('form.name') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Sequence Scope <span class="text-danger">*</span></label>
        <select class="form-select" wire:model.live="form.sequence_scope">
            <option value="study_program_year">Study Program + Year</option>
            <option value="global">Global</option>
            <option value="year">Year</option>
            <option value="period">Admission Period</option>
            <option value="faculty">Faculty</option>
            <option value="study_program">Study Program</option>
            <option value="class_type">Class Type</option>
        </select>
        @error('form.sequence_scope') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-12 mb-3">
        <label class="form-label">Pattern <span class="text-danger">*</span></label>
        <input type="text" class="form-control" wire:model.live.debounce.300ms="form.pattern" placeholder="{yy}{program_code}{sequence}">
        <small class="text-muted">Tokens: {year}, {yy}, {period_code}, {faculty_code}, {program_code}, {class_type}, {sequence}</small>
        @error('form.pattern') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Padding</label>
        <input type="number" min="1" max="10" class="form-control" wire:model.live="form.sequence_padding">
        @error('form.sequence_padding') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Start Number</label>
        <input type="number" min="1" class="form-control" wire:model.live="form.sequence_start">
        @error('form.sequence_start') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3 mb-3 d-flex align-items-center">
        <label class="form-check mb-0">
            <input class="form-check-input" type="checkbox" wire:model="form.is_active">
            <span class="form-check-label">Set as active rule</span>
        </label>
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Preview</label>
        <div class="form-control bg-light fw-bold">{{ $preview }}</div>
    </div>

    <div class="col-12 mb-3">
        <label class="form-label">Description</label>
        <textarea rows="3" class="form-control" wire:model="form.description"></textarea>
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save
        </button>
        <a href="{{ route('admin.admission.nim-generation-rules.index') }}" class="btn btn-secondary">Cancel</a>
    </div>
</div>
