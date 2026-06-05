<div class="row g-3">
    <div class="col-md-5">
        <label class="form-label required">Name</label>
        <input type="text" wire:model="form.name" class="form-control" placeholder="Surat Keterangan Aktif Kuliah">
        @error('form.name') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label required">Code</label>
        <input type="text" wire:model="form.code" class="form-control" placeholder="ACTIVE_STUDENT">
        @error('form.code') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label required">Fulfillment Mode</label>
        <select wire:model.live="form.fulfillment_mode" class="form-select">
            <option value="auto_generate">Auto Generate</option>
            <option value="manual_upload">Manual Upload</option>
            <option value="hybrid">Hybrid</option>
        </select>
        @error('form.fulfillment_mode') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Template Key</label>
        <input type="text" wire:model="form.template_key" class="form-control" placeholder="active_student">
        <small class="text-muted">Dipakai untuk auto-generated PDF.</small>
        @error('form.template_key') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Required Fields</label>
        <input type="text" wire:model="form.required_fields" class="form-control" placeholder="recipient, purpose, company_name">
        <small class="text-muted">Pisahkan dengan koma.</small>
        @error('form.required_fields') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label required">Status</label>
        <select wire:model="form.is_active" class="form-select">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
        @error('form.is_active') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-check form-switch mt-4">
            <input class="form-check-input" type="checkbox" wire:model.live="form.requires_financial_clearance">
            <span class="form-check-label">Requires financial clearance</span>
        </label>
    </div>
    <div class="col-md-4">
        <label class="form-label">Clearance Hold Type</label>
        <select wire:model="form.clearance_hold_type" class="form-select" @disabled(! $form['requires_financial_clearance'])>
            <option value="">None</option>
            <option value="registration">Registration</option>
            <option value="study_plan">Study Plan</option>
            <option value="exam_card">Exam Card</option>
            <option value="transcript">Transcript</option>
            <option value="graduation">Graduation</option>
        </select>
        @error('form.clearance_hold_type') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-check form-switch mt-4">
            <input class="form-check-input" type="checkbox" wire:model="form.requires_attachment">
            <span class="form-check-label">Requires student attachment</span>
        </label>
    </div>

    <div class="col-md-6">
        <label class="form-label">Allowed Extensions</label>
        <input type="text" wire:model="form.allowed_extensions" class="form-control" placeholder="pdf,jpg,jpeg,png">
        @error('form.allowed_extensions') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Max File Size (KB)</label>
        <input type="number" min="1" wire:model="form.max_file_size_kb" class="form-control" placeholder="2048">
        @error('form.max_file_size_kb') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Signer Name</label>
        <input type="text" wire:model="form.signer_name" class="form-control">
        @error('form.signer_name') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Signer Position</label>
        <input type="text" wire:model="form.signer_position" class="form-control">
        @error('form.signer_position') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label">Description</label>
        <textarea wire:model="form.description" rows="3" class="form-control"></textarea>
        @error('form.description') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <div class="alert alert-info mb-0">
            <i class="fas fa-info-circle me-2"></i>
            Auto generate memakai template PDF bawaan sistem. Manual upload dipakai untuk surat custom yang final filenya disiapkan admin.
        </div>
    </div>

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save Letter Type
        </button>
        <a href="{{ route('admin.student-services.letter-types.index') }}" class="btn btn-secondary">Cancel</a>
    </div>
</div>
