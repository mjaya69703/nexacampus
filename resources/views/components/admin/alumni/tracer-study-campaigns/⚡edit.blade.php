<?php

use App\Models\Academic\AcademicYear;
use App\Models\Alumni\TracerStudyCampaign;
use Livewire\Component;

new class extends Component
{
    public int $campaignId;
    public array $form = [];
    public array $questions = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.alumni.tracer-study.index');
    }

    public function mount($id): void
    {
        $campaign = TracerStudyCampaign::findOrFail($id);
        $this->campaignId = (int) $id;

        $this->form = [
            'title' => $campaign->title,
            'description' => $campaign->description ?? '',
            'academic_year_id' => $campaign->academic_year_id ?? '',
            'start_date' => $campaign->start_date?->format('Y-m-d') ?? '',
            'end_date' => $campaign->end_date?->format('Y-m-d') ?? '',
            'status' => $campaign->status,
        ];

        $this->questions = collect($campaign->questions ?? [])->map(function ($q) {
            return [
                'id' => $q['id'],
                'type' => $q['type'],
                'text' => $q['text'],
                'options' => is_array($q['options'] ?? null) ? implode("\n", $q['options']) : '',
            ];
        })->toArray();
    }

    public function addQuestion(): void
    {
        $this->questions[] = [
            'id' => 'q_' . uniqid(),
            'type' => 'text',
            'text' => '',
            'options' => '',
        ];
    }

    public function removeQuestion(int $index): void
    {
        unset($this->questions[$index]);
        $this->questions = array_values($this->questions);
    }

    public function updateTracerStudyCampaign(): void
    {
        $validated = $this->validate([
            'form.title' => 'required|string|max:255',
            'form.description' => 'nullable|string',
            'form.academic_year_id' => 'nullable|exists:academic_years,id',
            'form.start_date' => 'required|date',
            'form.end_date' => 'required|date|after_or_equal:form.start_date',
            'form.status' => 'required|in:draft,active,closed',
            'questions.*.type' => 'required|in:text,textarea,radio,select',
            'questions.*.text' => 'required|string',
            'questions.*.options' => 'nullable|string',
        ]);

        $campaign = TracerStudyCampaign::findOrFail($this->campaignId);

        $questionObjects = collect($validated['questions'])->map(function ($q) {
            return [
                'id' => $q['id'],
                'type' => $q['type'],
                'text' => $q['text'],
                'options' => $q['options'] ? array_map('trim', explode("\n", $q['options'])) : [],
            ];
        })->toArray();

        $campaign->update([
            'title' => $validated['form']['title'],
            'description' => $validated['form']['description'] ?: null,
            'academic_year_id' => $validated['form']['academic_year_id'] ?: null,
            'start_date' => $validated['form']['start_date'],
            'end_date' => $validated['form']['end_date'],
            'status' => $validated['form']['status'],
            'questions' => $questionObjects,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Kampanye tracer study berhasil diperbarui.');
        $this->redirectRoute('admin.alumni.tracer-study.index');
    }

    public function render()
    {
        $academicYears = AcademicYear::orderByDesc('start_date')->get();

        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni',
            'pages' => 'Edit Kampanye Tracer Study',
            'academicYears' => $academicYears,
        ]);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header"><h3 class="card-title">Edit Kampanye: {{ $form['title'] ?? '' }}</h3></div>
        <div class="card-body row">
            <div class="form-group col-lg-8 col-md-6 col-sm-12 mt-2">
                <label for="title">Judul <span class="text-danger">*</span></label>
                <input type="text" id="title" class="form-control" wire:model.defer="form.title">
                @error('form.title') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="academic_year_id">Tahun Akademik</label>
                <select id="academic_year_id" class="form-control" wire:model.defer="form.academic_year_id">
                    <option value="">-- Pilih --</option>
                    @foreach ($academicYears as $ay)
                        <option value="{{ $ay->id }}">{{ $ay->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="start_date">Tanggal Mulai <span class="text-danger">*</span></label>
                <input type="date" id="start_date" class="form-control" wire:model.defer="form.start_date">
                @error('form.start_date') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="end_date">Tanggal Selesai <span class="text-danger">*</span></label>
                <input type="date" id="end_date" class="form-control" wire:model.defer="form.end_date">
                @error('form.end_date') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="status">Status</label>
                <select id="status" class="form-control" wire:model.defer="form.status">
                    <option value="draft">Draft</option>
                    <option value="active">Aktif</option>
                    <option value="closed">Ditutup</option>
                </select>
            </div>
            <div class="form-group col-12 mt-2">
                <label for="description">Deskripsi</label>
                <textarea id="description" class="form-control" rows="3" wire:model.defer="form.description"></textarea>
            </div>

            <div class="col-12 mt-4">
                <h5>Pertanyaan Survey</h5>
                <button class="btn btn-sm btn-ghost-primary mb-3" wire:click="addQuestion">
                    <i class="fa fa-plus me-1"></i> Tambah Pertanyaan
                </button>

                @foreach ($questions as $index => $q)
                    <div class="card mb-3">
                        <div class="card-body row">
                            <div class="col-12 d-flex justify-content-between mb-2">
                                <strong>Pertanyaan {{ $index + 1 }}</strong>
                                <button class="btn btn-sm btn-danger" wire:click="removeQuestion({{ $index }})">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                            <div class="form-group col-lg-4 mt-2">
                                <label>Tipe</label>
                                <select class="form-control" wire:model.defer="questions.{{ $index }}.type">
                                    <option value="text">Text</option>
                                    <option value="textarea">Textarea</option>
                                    <option value="radio">Radio</option>
                                    <option value="select">Select</option>
                                </select>
                            </div>
                            <div class="form-group col-lg-8 mt-2">
                                <label>Pertanyaan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" wire:model.defer="questions.{{ $index }}.text">
                            </div>
                            @if (in_array($q['type'], ['radio', 'select']))
                                <div class="form-group col-12 mt-2">
                                    <label>Opsi (satu per baris)</label>
                                    <textarea class="form-control" rows="3" wire:model.defer="questions.{{ $index }}.options"></textarea>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
                <button class="btn btn-primary" wire:click="updateTracerStudyCampaign">
                    <i class="fas fa-save me-2"></i> Simpan Perubahan
                </button>
                <button class="btn btn-secondary" wire:click="cancel">
                    <i class="fas fa-times me-2"></i> Batal
                </button>
            </div>
        </div>
    </div>
</div>
