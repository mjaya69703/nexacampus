<?php

use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\StudentService\StudentTransferRequest;
use App\Support\StudentService\StudentTransferRequestService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public StudentTransferRequest $request;

    public array $form = [];

    public ?TemporaryUploadedFile $attachment = null;

    public $studyPrograms;

    public $faculties;

    public array $classTypeOptions = [
        'regular' => 'Regular',
        'evening' => 'Evening',
        'weekend' => 'Weekend',
    ];

    public string $submitLabel = 'Kirim Ulang';

    public function mount($id): void
    {
        $studentProfile = auth()->user()?->studentProfile;
        abort_unless($studentProfile, 404);

        $this->request = StudentTransferRequest::where('student_profile_id', $studentProfile->id)->findOrFail($id);
        abort_unless($this->request->status === 'revision_requested', 403);

        $this->faculties = $this->facultiesForStudent($studentProfile);
        $targetFacultyId = $this->request->transfer_type === 'faculty'
            ? $this->request->toStudyProgram?->faculty_id
            : null;
        $this->studyPrograms = $this->studyProgramsForType($studentProfile, $this->request->transfer_type, $targetFacultyId);

        $this->form = [
            'to_study_program_id' => $this->request->to_study_program_id,
            'target_faculty_id' => $targetFacultyId,
            'to_class_type' => $this->request->to_class_type,
            'recommended_semester' => $this->request->recommended_semester,
            'transfer_type' => $this->request->transfer_type,
            'reason' => $this->request->reason,
            'student_notes' => $this->request->student_notes,
        ];
    }

    public function updatedFormTransferType(): void
    {
        $studentProfile = auth()->user()?->studentProfile;
        $this->form['target_faculty_id'] = '';
        $this->studyPrograms = $this->studyProgramsForType($studentProfile, $this->form['transfer_type'], $this->form['target_faculty_id']);
        $this->form['to_study_program_id'] = $this->form['transfer_type'] === 'class_type'
            ? ($studentProfile?->study_program_id ?? '')
            : '';
        $this->form['to_class_type'] = '';
    }

    public function updatedFormTargetFacultyId(): void
    {
        $studentProfile = auth()->user()?->studentProfile;
        $this->studyPrograms = $this->studyProgramsForType($studentProfile, $this->form['transfer_type'], $this->form['target_faculty_id']);
        $this->form['to_study_program_id'] = '';
    }

    public function save(StudentTransferRequestService $service): void
    {
        $validated = $this->validate($this->rules())['form'];

        try {
            $request = $service->resubmit($this->request, $validated, $this->attachment);
            session()->flash('success', 'Perbaikan pengajuan transfer berhasil dikirim ulang.');
            $this->redirectRoute('student.student-services.transfers.show', ['id' => $request->id]);
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Student Services',
            'pages' => 'Edit Transfer Request',
        ]);
    }

    public function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    private function rules(): array
    {
        $studentProfile = auth()->user()?->studentProfile;
        $currentProgramId = $studentProfile?->study_program_id;
        $toClassTypeRules = ['nullable', Rule::in(array_keys($this->classTypeOptions))];

        if (($this->form['transfer_type'] ?? null) === 'class_type') {
            $toClassTypeRules = ['required', Rule::in(array_keys($this->classTypeOptions))];

            if ($studentProfile?->class_type) {
                $toClassTypeRules[] = Rule::notIn([$studentProfile->class_type]);
            }
        }

        return [
            'form.target_faculty_id' => ($this->form['transfer_type'] ?? null) === 'faculty'
                ? ['required', Rule::exists('faculties', 'id'), Rule::notIn([$studentProfile?->studyProgram?->faculty_id])]
                : ['nullable', Rule::exists('faculties', 'id')],
            'form.to_study_program_id' => ($this->form['transfer_type'] ?? null) === 'class_type'
                ? ['required', Rule::exists('study_programs', 'id'), Rule::in([$currentProgramId])]
                : ['required', Rule::exists('study_programs', 'id'), Rule::notIn([$currentProgramId])],
            'form.to_class_type' => $toClassTypeRules,
            'form.recommended_semester' => ['nullable', 'integer', 'min:1', 'max:14'],
            'form.transfer_type' => ['required', Rule::in(['study_program', 'faculty', 'class_type', 'other'])],
            'form.reason' => ['required', 'string', 'max:3000'],
            'form.student_notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    private function facultiesForStudent($studentProfile): Collection
    {
        return Faculty::query()
            ->when($studentProfile?->studyProgram?->faculty_id, fn ($query, $facultyId) => $query->whereKeyNot($facultyId))
            ->orderBy('name')
            ->get();
    }

    private function studyProgramsForType($studentProfile, string $transferType, $targetFacultyId = null): Collection
    {
        $query = StudyProgram::query()->with('faculty')->orderBy('name');

        if (! $studentProfile?->studyProgram) {
            return $query->get();
        }

        return match ($transferType) {
            'study_program' => $query
                ->where('faculty_id', $studentProfile->studyProgram->faculty_id)
                ->whereKeyNot($studentProfile->study_program_id)
                ->get(),
            'faculty' => $query
                ->where('faculty_id', $targetFacultyId ?: 0)
                ->get(),
            'class_type' => $query
                ->whereKey($studentProfile->study_program_id)
                ->get(),
            default => $query
                ->whereKeyNot($studentProfile->study_program_id)
                ->get(),
        };
    }
};
?>

@include('components.student.student-services.service-styles')

<div>
    <x-alert />

    <div class="card service-card hero-gradient mb-4">
        <div class="card-body p-4 p-lg-5" style="position: relative;">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                        <i class="fas fa-pen-to-square"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Student Services</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">Perbaiki Pengajuan Transfer</h1>
                        <div style="opacity: 0.9;">Lengkapi revisi sesuai catatan admin lalu kirim ulang.</div>
                    </div>
                </div>
                <a href="{{ route('student.student-services.transfers.show', ['id' => $request->id]) }}" class="action-btn" style="background: rgba(255,255,255,0.94); color: #4f46e5;">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    @if ($request->admin_notes)
        <div class="alert alert-warning">
            <strong>Catatan Admin:</strong> {{ $request->admin_notes }}
        </div>
    @endif

    <div class="card service-card">
        <div class="card-header" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <div>
                <h3 class="card-title mb-0">{{ $request->request_number }}</h3>
                <small class="text-muted d-block mt-1">Perbaiki data pengajuan transfer.</small>
            </div>
        </div>
        <div class="card-body p-4">
            <form wire:submit.prevent="save">
                @include('components.student.student-services.transfer-form', ['request' => $request])
            </form>
        </div>
    </div>
</div>
