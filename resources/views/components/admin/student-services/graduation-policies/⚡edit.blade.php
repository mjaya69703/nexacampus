<?php

use App\Models\Academic\StudyProgram;
use App\Models\StudentService\GraduationPolicy;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public GraduationPolicy $policy;

    public array $form = [];

    public $studyPrograms;

    public function mount($id): void
    {
        $this->policy = GraduationPolicy::findOrFail($id);
        $this->studyPrograms = StudyProgram::query()->orderBy('name')->get();
        $this->form = [
            'name' => $this->policy->name,
            'study_program_id' => $this->policy->study_program_id,
            'minimum_semester' => $this->policy->minimum_semester,
            'minimum_passed_credits' => $this->policy->minimum_passed_credits,
            'minimum_gpa' => $this->policy->minimum_gpa,
            'require_active_status' => (bool) $this->policy->require_active_status,
            'require_no_financial_hold' => (bool) $this->policy->require_no_financial_hold,
            'require_no_incomplete_grade' => (bool) $this->policy->require_no_incomplete_grade,
            'require_open_yudisium_period' => (bool) $this->policy->require_open_yudisium_period,
            'is_active' => (bool) $this->policy->is_active,
            'description' => $this->policy->description,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];
        $this->ensureUniqueActiveScope($validated['study_program_id'] ?: null, (bool) $validated['is_active']);

        $this->policy->update([
            ...$validated,
            'study_program_id' => $validated['study_program_id'] ?: null,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Graduation policy berhasil diperbarui.');
        $this->redirectRoute('admin.student-services.graduation-policies.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Edit Aturan Yudisium',
        ]);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.study_program_id' => ['nullable', Rule::exists('study_programs', 'id')],
            'form.minimum_semester' => ['required', 'integer', 'min:1', 'max:20'],
            'form.minimum_passed_credits' => ['required', 'integer', 'min:0', 'max:300'],
            'form.minimum_gpa' => ['required', 'numeric', 'min:0', 'max:4'],
            'form.require_active_status' => ['boolean'],
            'form.require_no_financial_hold' => ['boolean'],
            'form.require_no_incomplete_grade' => ['boolean'],
            'form.require_open_yudisium_period' => ['boolean'],
            'form.is_active' => ['boolean'],
            'form.description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function ensureUniqueActiveScope($studyProgramId, bool $isActive): void
    {
        if (! $isActive) {
            return;
        }

        $exists = GraduationPolicy::query()
            ->where('is_active', true)
            ->whereKeyNot($this->policy->id)
            ->when($studyProgramId, fn ($query) => $query->where('study_program_id', $studyProgramId), fn ($query) => $query->whereNull('study_program_id'))
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'form.study_program_id' => 'Sudah ada policy aktif untuk scope ini.',
            ]);
        }
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <x-admin.student-services.header
        title="Edit Aturan Yudisium: {{ $policy->name }}"
        description="Perbarui standar kelulusan minimal (SKS, IPK, Semester) atau prasyarat pendaftaran untuk aturan yudisium ini."
        icon="sliders"
    >
        <a href="{{ route('admin.student-services.graduation-policies.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.student-services.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-sliders fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Perubahan Aturan</h4>
                            <div class="text-muted small">Perbarui parameter dan prasyarat pendaftaran yudisium.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.student-services.graduation-policies._form')
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-circle-info"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Panduan Policy</h5>
                            <div class="text-muted small">Dampak evaluasi sistem.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Perubahan pada batas minimal SKS atau IPK akan langsung berlaku saat mahasiswa berikutnya mencoba mendaftar yudisium.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Jika Anda mengaktifkan aturan ini, pastikan aturan aktif lain pada scope yang sama telah dinonaktifkan.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Aturan</div>
                    <div class="fw-bold fs-5 text-dark mb-2">{{ $policy->is_active ? 'Aktif' : 'Nonaktif' }}</div>
                    <p class="text-muted small mb-0">Lingkup: {{ $policy->studyProgram?->name ?? 'Global Fallback (Semua Prodi)' }}.</p>
                </div>
            </div>
        </div>
    </div>
</div>
