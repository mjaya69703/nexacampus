<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Schedule Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" wire:model="form.name" placeholder="Tuition invoice 2026 ganjil">
        @error('form.name') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Invoice Kind <span class="text-danger">*</span></label>
        <select class="form-control" wire:model.live="form.invoice_kind">
            <option value="tuition">Tuition Template</option>
            <option value="custom">Custom Invoice</option>
        </select>
        @error('form.invoice_kind') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Target Mode <span class="text-danger">*</span></label>
        <select class="form-control" wire:model.live="form.generation_mode">
            <option value="active_students">Approved Active Students</option>
            <option value="selected_students">Selected Students</option>
            <option value="single">Single Student</option>
        </select>
        @error('form.generation_mode') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    @if(($form['generation_mode'] ?? 'active_students') === 'single')
        <div class="col-12">
            <label class="form-label">Student <span class="text-danger">*</span></label>
            <input type="search" class="form-control mb-2" wire:model.live.debounce.400ms="studentSearch" placeholder="Cari NIM, nama, atau email">
            <div class="border rounded p-2" style="max-height: 220px; overflow:auto;">
                @foreach($this->studentOptions() as $student)
                    <label class="form-check py-1">
                        <input class="form-check-input" type="radio" wire:model="form.student_profile_id" value="{{ $student['id'] }}">
                        <span class="form-check-label">{{ $student['label'] }}</span>
                    </label>
                @endforeach
            </div>
            @error('form.student_profile_id') <span class="text-danger">{{ $message }}</span> @enderror
        </div>
    @elseif(($form['generation_mode'] ?? 'active_students') === 'selected_students')
        <div class="col-12">
            <label class="form-label">Selected Students <span class="text-danger">*</span></label>
            <input type="search" class="form-control mb-2" wire:model.live.debounce.400ms="studentSearch" placeholder="Cari NIM, nama, atau email">
            <div class="border rounded p-2" style="max-height: 260px; overflow:auto;">
                @foreach($this->studentOptions() as $student)
                    <label class="form-check py-1">
                        <input class="form-check-input" type="checkbox" wire:model="selectedStudentProfileIds" value="{{ $student['id'] }}">
                        <span class="form-check-label">{{ $student['label'] }}</span>
                    </label>
                @endforeach
            </div>
            <small class="text-muted">{{ count($selectedStudentProfileIds) }} student dipilih.</small>
            @error('selectedStudentProfileIds') <span class="text-danger d-block">{{ $message }}</span> @enderror
        </div>
    @else
        <div class="col-12">
            <div class="alert alert-info mb-0">
                Bulk schedule akan menargetkan student profile aktif dengan registrasi approved aktif sesuai academic year dan semester.
            </div>
        </div>
    @endif

    <div class="col-md-4">
        <label class="form-label">Academic Year @if(($form['invoice_kind'] ?? 'tuition') === 'tuition') <span class="text-danger">*</span> @endif</label>
        <select class="form-control" wire:model="form.academic_year_id">
            <option value="">Select academic year</option>
            @foreach($academicYears as $year)
                <option value="{{ $year['id'] }}">{{ $year['label'] }}</option>
            @endforeach
        </select>
        @error('form.academic_year_id') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">Semester @if(($form['invoice_kind'] ?? 'tuition') === 'tuition') <span class="text-danger">*</span> @endif</label>
        <input type="number" min="1" max="14" class="form-control" wire:model="form.semester">
        @error('form.semester') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Publish At <span class="text-danger">*</span></label>
        <input type="datetime-local" class="form-control" wire:model="form.publish_at">
        @error('form.publish_at') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Due Date <span class="text-danger">*</span></label>
        <input type="date" class="form-control" wire:model="form.due_date">
        @error('form.due_date') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    @if(($form['invoice_kind'] ?? 'tuition') === 'custom')
        <div class="col-md-4">
            <label class="form-label">Invoice Type <span class="text-danger">*</span></label>
            <select class="form-control" wire:model="form.invoice_type">
                @foreach(config('financial.invoice_types', []) as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('form.invoice_type') <span class="text-danger">{{ $message }}</span> @enderror
        </div>
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label mb-0">Invoice Items</label>
                <button type="button" class="btn btn-outline-primary btn-sm" wire:click="addItem">
                    <i class="fas fa-plus me-1"></i> Add Item
                </button>
            </div>
            @foreach($items as $index => $item)
                <div class="row g-2 mb-2">
                    <div class="col-md-3">
                        <select class="form-control" wire:model="items.{{ $index }}.item_type">
                            <option value="fee">Fee</option>
                            <option value="discount">Discount</option>
                            <option value="adjustment">Adjustment</option>
                            <option value="penalty">Penalty</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" class="form-control" wire:model="items.{{ $index }}.description" placeholder="Description">
                    </div>
                    <div class="col-md-3">
                        <input type="number" step="1" min="1" class="form-control" wire:model="items.{{ $index }}.amount" placeholder="Amount">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-outline-danger w-100" wire:click="removeItem({{ $index }})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            @endforeach
            @error('items') <span class="text-danger">{{ $message }}</span> @enderror
        </div>
    @endif

    <div class="col-md-4">
        <label class="form-check mt-4">
            <input class="form-check-input" type="checkbox" wire:model="form.issue_immediately">
            <span class="form-check-label">Issue when schedule runs</span>
        </label>
        <small class="text-muted">Jika off, invoice dibuat draft dan belum terlihat mahasiswa.</small>
    </div>
    <div class="col-md-4">
        <label class="form-check mt-4">
            <input class="form-check-input" type="checkbox" wire:model="form.is_active">
            <span class="form-check-label">Active</span>
        </label>
    </div>
    <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea class="form-control" rows="3" wire:model="form.notes" placeholder="Optional notes for finance/admin"></textarea>
        @error('form.notes') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i> Save Schedule
    </button>
    <a href="{{ route('admin.financial.invoice-schedules.index') }}" class="btn btn-secondary">Cancel</a>
</div>
