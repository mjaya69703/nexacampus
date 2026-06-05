<?php

use App\Models\Academic\Assignment;
use App\Models\Academic\AssignmentFile;
use App\Models\Academic\AssignmentStatusHistory;
use App\Models\Academic\CourseOfferingLecturer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Assignment $assignment;
    public array $offeringInfo = [];
    public array $instructionFiles = [];
    public array $existingFiles = [];
    public string $formTitle = 'Edit Tugas';
    public string $backRoute = '';
    public string $editorIdentifier = 'lecturer-assignment-edit';
    public array $form = [
        'title' => '',
        'description' => '',
        'due_at' => '',
        'max_score' => 100,
        'allowed_file_types' => '',
        'max_file_size_mb' => 10,
        'allow_text_submission' => true,
        'allow_file_submission' => true,
        'allow_resubmission' => true,
        'accept_late_submission' => true,
        'is_published' => true,
    ];

    public function mount(int $id): void
    {
        $this->assignment = Assignment::query()
            ->with(['courseOffering.course', 'courseOffering.academicYear', 'courseOffering.studyProgram', 'files'])
            ->findOrFail($id);

        $this->authorizeAssignment();
        $this->backRoute = route('lecturer.assignments.show', ['id' => $this->assignment->id]);

        $offering = $this->assignment->courseOffering;
        $this->offeringInfo = [
            'course' => trim(($offering?->course?->code ? $offering->course->code.' - ' : '').($offering?->course?->name ?? '-')),
            'label' => $offering?->label ?? '-',
            'academic_year' => $offering?->academicYear?->name ?? '-',
            'study_program' => $offering?->studyProgram?->name ?? '-',
        ];

        $this->form = [
            'title' => $this->assignment->title,
            'description' => $this->assignment->description ?? '',
            'due_at' => $this->assignment->due_at?->format('Y-m-d\TH:i') ?? '',
            'max_score' => (float) $this->assignment->max_score,
            'allowed_file_types' => implode(',', $this->assignment->allowedExtensions()),
            'max_file_size_mb' => max(1, (int) ceil($this->assignment->max_file_size_kb / 1024)),
            'allow_text_submission' => $this->assignment->allow_text_submission,
            'allow_file_submission' => $this->assignment->allow_file_submission,
            'allow_resubmission' => $this->assignment->allow_resubmission,
            'accept_late_submission' => $this->assignment->accept_late_submission,
            'is_published' => $this->assignment->is_published,
        ];

        $this->loadExistingFiles();
    }

    public function save(): void
    {
        $this->authorizeAssignment();

        $validated = $this->validate($this->rules());

        if (! $validated['form']['allow_text_submission'] && ! $validated['form']['allow_file_submission']) {
            $this->addError('form.allow_file_submission', 'Minimal izinkan salah satu metode submit.');

            return;
        }

        DB::transaction(function () use ($validated) {
            $wasPublished = $this->assignment->is_published;

            $this->assignment->update($this->assignmentPayload($validated));

            foreach ($this->instructionFiles as $file) {
                $path = $file->store('assignments/instructions', 'public');
                $this->assignment->files()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => strtolower($file->getClientOriginalExtension()),
                    'file_size' => $file->getSize(),
                ]);
            }

            AssignmentStatusHistory::query()->create([
                'assignment_id' => $this->assignment->id,
                'actor_id' => auth()->id(),
                'status' => $this->assignment->is_published ? ($wasPublished ? 'updated' : 'published') : 'draft',
                'note' => 'Assignment updated.',
            ]);
        });

        session()->flash('success', 'Tugas berhasil diperbarui.');
        $this->redirectRoute('lecturer.assignments.show', ['id' => $this->assignment->id], navigate: true);
    }

    private function assignmentPayload(array $validated): array
    {
        $maxFileSizeMb = (int) $validated['form']['max_file_size_mb'];
        $dueAt = $validated['form']['due_at'];

        $payload = [
            'title' => $validated['form']['title'],
            'description' => $validated['form']['description'] ?: null,
            'due_at' => $dueAt,
            'max_score' => $validated['form']['max_score'],
            'allowed_file_types' => $this->normalisedExtensions($validated['form']['allowed_file_types']),
            'max_file_size_kb' => $maxFileSizeMb * 1024,
            'allow_text_submission' => (bool) $validated['form']['allow_text_submission'],
            'allow_file_submission' => (bool) $validated['form']['allow_file_submission'],
            'allow_resubmission' => (bool) $validated['form']['allow_resubmission'],
            'accept_late_submission' => (bool) $validated['form']['accept_late_submission'],
            'is_published' => (bool) $validated['form']['is_published'],
            'published_at' => $validated['form']['is_published']
                ? ($this->assignment->published_at ?: now())
                : null,
        ];

        if (Schema::hasColumn('assignments', 'due_date')) {
            $payload['due_date'] = $dueAt;
        }

        if (Schema::hasColumn('assignments', 'max_file_size_mb')) {
            $payload['max_file_size_mb'] = $maxFileSizeMb;
        }

        if (Schema::hasColumn('assignments', 'max_files')) {
            $payload['max_files'] = $this->assignment->max_files ?: 5;
        }

        if (Schema::hasColumn('assignments', 'allow_late_submission')) {
            $payload['allow_late_submission'] = (bool) $validated['form']['accept_late_submission'];
        }

        if (Schema::hasColumn('assignments', 'late_penalty_percentage')) {
            $payload['late_penalty_percentage'] = $this->assignment->late_penalty_percentage ?: 0;
        }

        return $payload;
    }

    public function removeInstructionFile(int $fileId): void
    {
        $this->authorizeAssignment();

        $file = AssignmentFile::query()
            ->where('assignment_id', $this->assignment->id)
            ->findOrFail($fileId);

        Storage::disk('public')->delete($file->file_path);
        $file->delete();
        $this->loadExistingFiles();
    }

    private function loadExistingFiles(): void
    {
        $this->existingFiles = $this->assignment->files()
            ->latest('id')
            ->get()
            ->map(fn (AssignmentFile $file) => [
                'id' => $file->id,
                'name' => $file->file_name,
            ])
            ->all();
    }

    private function rules(): array
    {
        return [
            'form.title' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string'],
            'form.due_at' => ['required', 'date'],
            'form.max_score' => ['required', 'numeric', 'min:1', 'max:9999'],
            'form.allowed_file_types' => ['nullable', 'string', 'max:255'],
            'form.max_file_size_mb' => ['required', 'integer', 'min:1', 'max:100'],
            'form.allow_text_submission' => ['boolean'],
            'form.allow_file_submission' => ['boolean'],
            'form.allow_resubmission' => ['boolean'],
            'form.accept_late_submission' => ['boolean'],
            'form.is_published' => ['boolean'],
            'instructionFiles.*' => ['nullable', 'file', 'max:20480'],
        ];
    }

    private function normalisedExtensions(?string $value): array
    {
        return collect(explode(',', (string) $value))
            ->map(fn ($item) => strtolower(trim($item, " .\t\n\r\0\x0B")))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function authorizeAssignment(): void
    {
        $lecturerProfileId = auth()->user()?->lecturerProfile?->id;

        abort_unless($lecturerProfileId && CourseOfferingLecturer::query()
            ->where('course_offering_id', $this->assignment->course_offering_id)
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->where('is_active', true)
            ->exists(), 403);
    }

    public function render(): View
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Edit Tugas',
        ]);
    }
};
?>

<div>
    <x-alert />

    <form wire:submit.prevent="save">
        @include('components.lecturer.assignments.assignment-form')

        <div class="d-flex justify-content-end gap-2 flex-wrap mt-4">
            <a href="{{ route('lecturer.assignments.show', ['id' => $assignment->id]) }}" class="btn btn-lg" style="background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;border-radius:12px;font-weight:800;">Batal</a>
            <button type="submit" class="btn btn-lg" wire:loading.attr="disabled" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;border:0;border-radius:12px;font-weight:900;">
                <span wire:loading.remove><i class="fas fa-save me-1"></i>Simpan Perubahan</span>
                <span wire:loading><i class="fas fa-spinner fa-spin me-1"></i>Menyimpan...</span>
            </button>
        </div>
    </form>
</div>
