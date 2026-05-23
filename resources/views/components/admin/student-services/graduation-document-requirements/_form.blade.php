<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Scope Program Studi</label>
        <select wire:model="form.study_program_id" class="form-select">
            <option value="">Global</option>
            @foreach ($studyPrograms as $program)
                <option value="{{ $program->id }}">{{ $program->name }}</option>
            @endforeach
        </select>
        <small class="text-muted">Kosongkan untuk berlaku semua prodi.</small>
        @error('form.study_program_id') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label required">Document Type</label>
        <input type="text" wire:model="form.document_type" class="form-control" placeholder="ktp, pas_foto, bebas_pustaka">
        @error('form.document_type') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label required">Label</label>
        <input type="text" wire:model="form.label" class="form-control" placeholder="Scan KTP">
        @error('form.label') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Allowed Extensions</label>
        <input type="text" wire:model="form.allowed_extensions" class="form-control" placeholder="pdf,jpg,jpeg,png">
        @error('form.allowed_extensions') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Max Size KB</label>
        <input type="number" min="1" wire:model="form.max_size_kb" class="form-control">
        @error('form.max_size_kb') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Sort Order</label>
        <input type="number" min="0" wire:model="form.sort_order" class="form-control">
        @error('form.sort_order') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label class="form-label">Description</label>
        <textarea wire:model="form.description" rows="3" class="form-control"></textarea>
        @error('form.description') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-check form-switch">
            <input type="checkbox" class="form-check-input" wire:model="form.is_required">
            <span class="form-check-label">Required</span>
        </label>
    </div>
    <div class="col-md-6">
        <label class="form-check form-switch">
            <input type="checkbox" class="form-check-input" wire:model="form.is_active">
            <span class="form-check-label">Active</span>
        </label>
    </div>
    <div class="col-12 d-flex gap-2">
        <button wire:click="save" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save Requirement
        </button>
        <a href="{{ route('admin.student-services.graduation-document-requirements.index') }}" class="btn btn-secondary">Cancel</a>
    </div>
</div>
