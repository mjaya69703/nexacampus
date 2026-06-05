<?php

use App\Models\StudentService\ServiceLetterType;
use App\Support\StudentService\ServiceLetterRequestService;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public array $form = [
        'service_letter_type_id' => '',
        'purpose' => '',
        'student_notes' => '',
        'request_data' => [],
    ];

    public ?TemporaryUploadedFile $attachment = null;

    public $letterTypes;

    public ?ServiceLetterType $selectedType = null;

    public function mount(): void
    {
        $this->letterTypes = ServiceLetterType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function updatedFormServiceLetterTypeId(): void
    {
        $this->selectedType = $this->letterTypes->firstWhere('id', (int) $this->form['service_letter_type_id']);
        $this->form['request_data'] = [];

        foreach (($this->selectedType?->required_fields ?? []) as $field) {
            if ($field !== 'purpose') {
                $this->form['request_data'][$field] = '';
            }
        }
    }

    public function save(ServiceLetterRequestService $service): void
    {
        $studentProfile = auth()->user()?->studentProfile;
        abort_unless($studentProfile, 422, 'Student profile belum tersedia.');

        $this->selectedType = ServiceLetterType::findOrFail($this->form['service_letter_type_id']);

        $rules = [
            'form.service_letter_type_id' => ['required', Rule::exists('service_letter_types', 'id')],
            'form.purpose' => ['required', 'string', 'max:1000'],
            'form.student_notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => array_values(array_filter([
                $this->selectedType->requires_attachment ? 'required' : 'nullable',
                'file',
                $this->selectedType->allowed_extensions ? 'mimes:'.$this->selectedType->allowed_extensions : null,
                'max:'.($this->selectedType->max_file_size_kb ?: 2048),
            ])),
        ];

        foreach (($this->selectedType->required_fields ?? []) as $field) {
            if ($field !== 'purpose') {
                $rules['form.request_data.'.$field] = ['required', 'string', 'max:1000'];
            }
        }

        $validated = $this->validate($rules)['form'];

        try {
            $request = $service->create($this->selectedType, $studentProfile, $validated, $this->attachment);
            session()->flash('success', 'Pengajuan surat berhasil dikirim.');
            $this->redirectRoute('student.student-services.letters.show', ['id' => $request->id]);
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Student Services',
            'pages' => 'Create Letter Request',
        ]);
    }
};
?>

@push('styles')
    <style>
        .service-card {
            border: 0;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
            background: white;
        }
        .hero-gradient {
            border-radius: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            overflow: hidden;
            position: relative;
        }
        .hero-gradient::before {
            content: '';
            position: absolute;
            inset: -60% -30% auto auto;
            width: 420px;
            height: 420px;
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
        }
        .form-shell {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 16px;
            padding: 1.25rem;
        }
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            border-radius: 10px;
            padding: .65rem 1.05rem;
            border: 0;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card service-card hero-gradient mb-4">
        <div class="card-body p-4 p-lg-5" style="position: relative;">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                        <i class="fas fa-file-signature"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Student Services</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">Ajukan Surat</h1>
                        <div style="opacity: 0.9;">Lengkapi data surat dan pantau prosesnya dari halaman layanan.</div>
                    </div>
                </div>
                <a href="{{ route('student.student-services.letters') }}" class="action-btn" style="background: rgba(255,255,255,0.94); color: #4f46e5;">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <div class="card service-card">
        <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <div>
                <h3 class="card-title mb-0">Ajukan Surat</h3>
                <small class="text-muted d-block mt-1">Pilih jenis surat dan lengkapi informasi yang dibutuhkan.</small>
            </div>
        </div>
        <div class="card-body p-4">
            <form wire:submit.prevent="save">
                <div class="row g-3 form-shell">
                    <div class="col-md-12">
                        <label class="form-label required">Jenis Surat</label>
                        <select wire:model.live="form.service_letter_type_id" class="form-select">
                            <option value="">Pilih jenis surat</option>
                            @foreach ($letterTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                        @error('form.service_letter_type_id') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label required">Keperluan</label>
                        <textarea wire:model="form.purpose" class="form-control" rows="3" placeholder="Contoh: Keperluan beasiswa / magang / administrasi keluarga"></textarea>
                        @error('form.purpose') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    @if ($selectedType)
                        @foreach (($selectedType->required_fields ?? []) as $field)
                            @continue($field === 'purpose')
                            <div class="col-md-6">
                                <label class="form-label required">{{ str($field)->replace('_', ' ')->title() }}</label>
                                <input type="text" wire:model="form.request_data.{{ $field }}" class="form-control">
                                @error('form.request_data.'.$field) <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        @endforeach

                        @if ($selectedType->requires_financial_clearance)
                            <div class="col-12">
                                <div class="alert alert-warning mb-0">
                                    <i class="fas fa-lock me-2"></i>
                                    Jenis surat ini membutuhkan clearance {{ str($selectedType->clearance_hold_type)->replace('_', ' ') }}. Request akan dicek dengan status keuangan terbaru.
                                </div>
                            </div>
                        @endif
                    @endif

                    <div class="col-12">
                        <label class="form-label">Lampiran Pendukung</label>
                        <input type="file" wire:model="attachment" class="form-control">
                        <small class="text-muted">Wajib jika jenis surat mensyaratkan lampiran.</small>
                        @error('attachment') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Catatan Tambahan</label>
                        <textarea wire:model="form.student_notes" class="form-control" rows="3"></textarea>
                        @error('form.student_notes') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="action-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <i class="fas fa-paper-plane me-1"></i> Submit Request
                        </button>
                        <a href="{{ route('student.student-services.letters') }}" class="action-btn" style="background: #e5e7eb; color: #374151;">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
