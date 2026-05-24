@include('components.lecturer.assignments.assignment-styles')

<div class="card assignment-card assignment-hero mb-4">
    <div class="card-body p-4 p-lg-5" style="position:relative;">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div class="d-flex gap-3">
                <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.75rem;">
                    <i class="fas fa-clipboard-check"></i>
                </span>
                <div>
                    <div style="opacity:.86;font-weight:700;">Tugas Perkuliahan</div>
                    <h1 class="h2 mb-2" style="font-weight:900;">{{ $formTitle }}</h1>
                    <div style="opacity:.9;">{{ $offeringInfo['course'] ?? '-' }} / {{ $offeringInfo['label'] ?? '-' }}</div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="assignment-pill assignment-info-pill"><i class="fas fa-calendar"></i>{{ $offeringInfo['academic_year'] ?? '-' }}</span>
                        <span class="assignment-pill assignment-info-pill"><i class="fas fa-graduation-cap"></i>{{ $offeringInfo['study_program'] ?? '-' }}</span>
                    </div>
                </div>
            </div>
            <a href="{{ $backRoute }}" class="assignment-action" style="background:rgba(255,255,255,.95);color:#4f46e5;">
                <i class="fas fa-arrow-left"></i>Kembali
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card assignment-card">
            <div class="card-header py-3">
                <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-pen-to-square me-2 text-primary"></i>Konten Tugas</h3>
            </div>
            <div class="card-body p-4">
                <div class="mb-4">
                    <label class="form-label required">Judul Tugas</label>
                    <input type="text" class="form-control form-control-lg" wire:model="form.title" placeholder="Contoh: Analisis Studi Kasus Sistem Informasi">
                    @error('form.title') <small class="text-danger">{{ $message }}</small> @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label">Instruksi / Rubrik Singkat</label>
                    <livewire:jodit-text-editor wire:model.live="form.description" :identifier="$editorIdentifier" :height="330" />
                    <div class="form-hint mt-2">Tulis format jawaban, kriteria penilaian, dan catatan teknis yang perlu mahasiswa pahami.</div>
                    @error('form.description') <small class="text-danger">{{ $message }}</small> @enderror
                </div>

                <div class="assignment-dropzone">
                    <label class="form-label">Lampiran Instruksi</label>
                    <input type="file" class="form-control" wire:model="instructionFiles" multiple>
                    <div class="form-hint mt-2">Opsional. Bisa dipakai untuk soal, template, dataset, atau file pendukung.</div>
                    @error('instructionFiles.*') <small class="text-danger d-block mt-2">{{ $message }}</small> @enderror
                </div>

                @if (! empty($existingFiles))
                    <div class="mt-3 d-flex flex-wrap gap-2">
                        @foreach ($existingFiles as $file)
                            <span class="assignment-attachment">
                                <i class="fas fa-paperclip"></i>{{ $file['name'] }}
                                <button type="button" class="btn p-0 ms-1 text-danger" wire:click="removeInstructionFile({{ $file['id'] }})" title="Hapus lampiran">
                                    <i class="fas fa-times"></i>
                                </button>
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="assignment-shell">
            <div class="assignment-panel">
                <div class="fw-bold mb-3"><i class="fas fa-sliders text-primary me-2"></i>Pengaturan</div>

                <div class="mb-3">
                    <label class="form-label required">Deadline</label>
                    <input type="datetime-local" class="form-control" wire:model="form.due_at">
                    @error('form.due_at') <small class="text-danger">{{ $message }}</small> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label required">Maksimal Skor</label>
                    <input type="number" min="1" step="0.01" class="form-control" wire:model="form.max_score">
                    @error('form.max_score') <small class="text-danger">{{ $message }}</small> @enderror
                </div>

                <div>
                    <label class="form-label required">Maksimal File Mahasiswa (MB)</label>
                    <input type="number" min="1" max="100" class="form-control" wire:model="form.max_file_size_mb">
                    @error('form.max_file_size_mb') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
            </div>

            <div class="assignment-panel">
                <div class="fw-bold mb-2"><i class="fas fa-paperclip text-primary me-2"></i>Format Submission</div>
                <input type="text" class="form-control" wire:model="form.allowed_file_types" placeholder="pdf,docx,zip">
                <div class="form-hint mt-2">Pisahkan ekstensi dengan koma.</div>
                @error('form.allowed_file_types') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <div class="assignment-panel">
                <div class="fw-bold mb-3"><i class="fas fa-toggle-on text-primary me-2"></i>Aturan Mahasiswa</div>
                <label class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" wire:model="form.allow_text_submission">
                    <span class="form-check-label">Boleh jawaban teks</span>
                </label>
                <label class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" wire:model="form.allow_file_submission">
                    <span class="form-check-label">Boleh upload file</span>
                </label>
                <label class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" wire:model="form.allow_resubmission">
                    <span class="form-check-label">Boleh resubmit sebelum deadline</span>
                </label>
                <label class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" wire:model="form.accept_late_submission">
                    <span class="form-check-label">Terima submission telat</span>
                </label>
                @error('form.allow_file_submission') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <div class="assignment-panel" style="background:linear-gradient(135deg,#ecfdf5 0%,#f0fdf4 100%);border-color:#a7f3d0;">
                <label class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" wire:model="form.is_published">
                    <span class="form-check-label fw-bold text-success">Publikasikan ke mahasiswa</span>
                </label>
                <div class="form-hint mt-2" style="color:#047857;">Jika tidak dipublish, mahasiswa belum melihat tugas ini.</div>
            </div>
        </div>
    </div>
</div>
