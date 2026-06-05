<div class="row g-3 form-shell">
    <div class="col-md-6">
        <label class="form-label required">Batch Yudisium</label>
        <select wire:model="form.graduation_batch_id" class="form-select">
            <option value="">Pilih batch yudisium yang sedang dibuka</option>
            @foreach ($graduationBatches as $batch)
                <option value="{{ $batch->id }}">
                    {{ $batch->AcademicPeriod?->name }} - {{ $batch->yudisium_date->translatedFormat('d F Y') }}
                </option>
            @endforeach
        </select>
        @if ($graduationBatches->isEmpty())
            <small class="text-danger">Belum ada batch yudisium yang sedang dibuka.</small>
        @elseif ($graduationBatches->count() === 1)
            <small class="text-muted">Batch yang sedang dibuka sudah dipilih otomatis.</small>
        @else
            <small class="text-muted">Pilih batch yudisium yang sesuai dengan gelombang pengajuan kamu.</small>
        @endif
        @error('form.graduation_batch_id') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Judul Skripsi / Tugas Akhir</label>
        <input type="text" wire:model="form.thesis_title" class="form-control">
        @error('form.thesis_title') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
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
        <div
            x-data="{ uploading: false, progress: 0 }"
            x-on:livewire-upload-start="uploading = true; progress = 0"
            x-on:livewire-upload-finish="uploading = false; progress = 100"
            x-on:livewire-upload-cancel="uploading = false; progress = 0"
            x-on:livewire-upload-error="uploading = false; progress = 0"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
        >
            <input type="file" wire:model="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
            <div x-show="uploading" x-cloak class="mt-2">
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-primary" role="progressbar" x-bind:style="'width: '+progress+'%'"></div>
                </div>
                <div class="small text-muted mt-1">Uploading <span x-text="progress"></span>%</div>
            </div>
        </div>
        <small class="text-muted">PDF/JPG/PNG, maksimal 5MB.</small>
        @error('attachment') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    @if (($documentRequirements ?? collect())->isNotEmpty())
        <div class="col-12">
            <div class="detail-tile">
                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-3">
                    <div>
                        <div class="fw-bold">Dokumen Yudisium</div>
                        <div class="small text-muted">Upload dokumen sesuai requirement yang diatur admin. Dokumen yang ditolak bisa diganti saat pengajuan diminta perbaikan.</div>
                    </div>
                    <span class="badge bg-blue-lt text-blue">{{ $documentRequirements->where('is_required', true)->count() }} wajib</span>
                </div>
                @php($existingDocuments = isset($application) ? $application->documents->keyBy('graduation_document_requirement_id') : collect())
                <div class="row g-3">
                    @foreach ($documentRequirements as $requirement)
                        @php($document = $existingDocuments->get($requirement->id))
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100 {{ $document?->verification_status === 'verified' ? 'border-success bg-green-lt' : ($document?->verification_status === 'rejected' ? 'border-danger bg-red-lt' : 'border-light') }}">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                    <div>
                                        <div class="fw-bold">{{ $requirement->label }}</div>
                                        <div class="small text-muted">{{ $requirement->description ?: str($requirement->document_type)->replace('_', ' ')->title() }}</div>
                                    </div>
                                    <span class="badge {{ $requirement->is_required ? 'bg-danger-lt text-danger' : 'bg-secondary-lt text-secondary' }}">
                                        {{ $requirement->is_required ? 'Wajib' : 'Opsional' }}
                                    </span>
                                </div>
                                @if ($document)
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                        <a href="{{ route('student.student-services.graduation-documents.preview', ['document' => $document->id]) }}" target="_blank" class="badge bg-blue-lt text-blue text-decoration-none">
                                            <i class="fas fa-eye me-1"></i> {{ $document->file_name }}
                                        </a>
                                        <span class="badge {{ $document->verification_status === 'verified' ? 'bg-green-lt text-green' : ($document->verification_status === 'rejected' ? 'bg-red-lt text-red' : 'bg-yellow-lt text-yellow') }}">
                                            {{ str($document->verification_status)->title() }}
                                        </span>
                                    </div>
                                    @if ($document->verification_notes)
                                        <div class="small text-danger mb-2">{{ $document->verification_notes }}</div>
                                    @endif
                                @endif
                                <div
                                    x-data="{ uploading: false, progress: 0 }"
                                    x-on:livewire-upload-start="uploading = true; progress = 0"
                                    x-on:livewire-upload-finish="uploading = false; progress = 100"
                                    x-on:livewire-upload-cancel="uploading = false; progress = 0"
                                    x-on:livewire-upload-error="uploading = false; progress = 0"
                                    x-on:livewire-upload-progress="progress = $event.detail.progress"
                                >
                                    <input type="file" wire:model="documentUploads.{{ $requirement->id }}" class="form-control" accept=".{{ implode(',.', $requirement->allowedExtensionsList()) }}">
                                    <div x-show="uploading" x-cloak class="mt-2">
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-primary" role="progressbar" x-bind:style="'width: '+progress+'%'"></div>
                                        </div>
                                        <div class="small text-muted mt-1">Uploading <span x-text="progress"></span>%</div>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    {{ strtoupper(implode('/', $requirement->allowedExtensionsList())) }}, maksimal {{ number_format($requirement->max_size_kb ?: 5120) }} KB.
                                </small>
                                @error('documentUploads.'.$requirement->id) <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
    <div class="col-12">
        <label class="form-label">Catatan / Keterangan</label>
        <textarea wire:model="form.reason" rows="4" class="form-control" placeholder="Tambahkan keterangan bila perlu."></textarea>
        @error('form.reason') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label class="form-label">Catatan Tambahan</label>
        <textarea wire:model="form.student_notes" rows="3" class="form-control"></textarea>
        @error('form.student_notes') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-12 d-flex gap-2">
        <button type="submit" class="action-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;" wire:loading.attr="disabled" wire:target="attachment,documentUploads,save" @disabled(! $canSubmit)>
            <span wire:loading.remove wire:target="save,attachment,documentUploads">
                <i class="fas fa-paper-plane me-1"></i> {{ $submitLabel }}
            </span>
            <span wire:loading wire:target="attachment,documentUploads">
                <i class="fas fa-spinner fa-spin me-1"></i> Tunggu upload selesai
            </span>
            <span wire:loading wire:target="save">
                <i class="fas fa-spinner fa-spin me-1"></i> Mengirim
            </span>
        </button>
        <a href="{{ route('student.student-services.graduations') }}" class="action-btn" style="background: #e5e7eb; color: #374151;">Cancel</a>
    </div>
</div>
