<?php

use App\Models\Academic\AcademicYear;
use App\Support\StudentService\StudentLeaveApplicationService;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public array $form = [
        'academic_year_id' => '',
        'semester' => 1,
        'duration_semesters' => 1,
        'reason_category' => 'personal',
        'reason' => '',
        'student_notes' => '',
    ];

    public ?TemporaryUploadedFile $attachment = null;

    public $academicYears;

    public string $submitLabel = 'Submit Pengajuan';

    public function mount(): void
    {
        $this->academicYears = AcademicYear::query()->orderByDesc('start_date')->get();
        $this->form['semester'] = auth()->user()?->studentProfile?->current_semester ?: 1;
    }

    public function save(StudentLeaveApplicationService $service): void
    {
        $studentProfile = auth()->user()?->studentProfile;
        abort_unless($studentProfile, 422, 'Student profile belum tersedia.');

        $validated = $this->validate($this->rules())['form'];

        try {
            $application = $service->create($studentProfile, $validated, $this->attachment);
            session()->flash('success', 'Pengajuan cuti berhasil dikirim.');
            $this->redirectRoute('student.student-services.leaves.show', ['id' => $application->id]);
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Student Services',
            'pages' => 'Create Leave Application',
        ]);
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
                        <i class="fas fa-calendar-minus"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Student Services</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">Ajukan Cuti Akademik</h1>
                        <div style="opacity: 0.9;">Pengajuan ini akan direview oleh bagian akademik sebelum status cuti diaktifkan.</div>
                    </div>
                </div>
                <a href="{{ route('student.student-services.leaves') }}" class="action-btn" style="background: rgba(255,255,255,0.94); color: #4f46e5;">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <div class="card service-card">
        <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <div>
                <h3 class="card-title mb-0">Form Pengajuan Cuti</h3>
                <small class="text-muted d-block mt-1">Isi semester, durasi, alasan, dan lampiran pendukung jika ada.</small>
            </div>
        </div>
        <div class="card-body p-4">
            <form wire:submit.prevent="save">
                @include('components.student.student-services.leave-form')
            </form>
        </div>
    </div>
</div>
