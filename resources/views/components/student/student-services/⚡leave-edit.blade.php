<?php

use App\Models\Academic\AcademicYear;
use App\Models\StudentService\StudentLeaveApplication;
use App\Support\StudentService\StudentLeaveApplicationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public StudentLeaveApplication $application;

    public array $form = [];

    public ?TemporaryUploadedFile $attachment = null;

    public $academicYears;

    public string $submitLabel = 'Kirim Ulang';

    public function mount($id): void
    {
        $studentProfile = auth()->user()?->studentProfile;
        abort_unless($studentProfile, 404);

        $this->application = StudentLeaveApplication::where('student_profile_id', $studentProfile->id)->findOrFail($id);
        abort_unless($this->application->status === 'revision_requested', 403);

        $this->academicYears = AcademicYear::query()->orderByDesc('start_date')->get();
        $this->form = [
            'academic_year_id' => $this->application->academic_year_id,
            'semester' => $this->application->semester,
            'duration_semesters' => $this->application->duration_semesters,
            'reason_category' => $this->application->reason_category,
            'reason' => $this->application->reason,
            'student_notes' => $this->application->student_notes,
        ];
    }

    public function save(StudentLeaveApplicationService $service): void
    {
        $validated = $this->validate($this->rules())['form'];

        try {
            $application = $service->resubmit($this->application, $validated, $this->attachment);
            session()->flash('success', 'Perbaikan pengajuan cuti berhasil dikirim ulang.');
            $this->redirectRoute('student.student-services.leaves.show', ['id' => $application->id]);
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Student Services',
            'pages' => 'Edit Leave Application',
        ]);
    }

    public function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    private function rules(): array
    {
        return [
            'form.academic_year_id' => ['required', Rule::exists('academic_years', 'id')],
            'form.semester' => ['required', 'integer', 'min:1', 'max:14'],
            'form.duration_semesters' => ['required', 'integer', 'min:1', 'max:2'],
            'form.reason_category' => ['required', Rule::in(['personal', 'medical', 'financial', 'family', 'work', 'other'])],
            'form.reason' => ['required', 'string', 'max:3000'],
            'form.student_notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
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
                        <h1 class="h2 mb-2" style="font-weight: 800;">Perbaiki Pengajuan Cuti</h1>
                        <div style="opacity: 0.9;">Lengkapi revisi sesuai catatan admin lalu kirim ulang.</div>
                    </div>
                </div>
                <a href="{{ route('student.student-services.leaves.show', ['id' => $application->id]) }}" class="action-btn" style="background: rgba(255,255,255,0.94); color: #4f46e5;">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    @if ($application->admin_notes)
        <div class="alert alert-warning">
            <strong>Catatan Admin:</strong> {{ $application->admin_notes }}
        </div>
    @endif

    <div class="card service-card">
        <div class="card-header" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <div>
                <h3 class="card-title mb-0">{{ $application->application_number }}</h3>
                <small class="text-muted d-block mt-1">Perbaiki data pengajuan cuti akademik.</small>
            </div>
        </div>
        <div class="card-body p-4">
            <form wire:submit.prevent="save">
                @include('components.student.student-services.leave-form', ['application' => $application])
            </form>
        </div>
    </div>
</div>
