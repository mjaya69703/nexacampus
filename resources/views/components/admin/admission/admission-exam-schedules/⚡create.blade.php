<?php

use App\Models\Admission\AdmissionExamSchedule;
use App\Models\Admission\AdmissionPeriod;
use Livewire\Component;

new class extends Component
{
    public array $periods = [];

    public array $form = [
        'admission_period_id' => '',
        'exam_type' => 'written_test',
        'title' => '',
        'exam_date' => '',
        'exam_time' => '',
        'venue' => '',
        'meeting_link' => '',
        'quota' => 0,
        'notes' => '',
        'is_active' => true,
    ];

    public function mount(): void
    {
        $this->periods = AdmissionPeriod::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'code'])
            ->map(fn (AdmissionPeriod $period) => [
                'id' => $period->id,
                'label' => $period->name.' ('.$period->code.')',
            ])
            ->toArray();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.admission_period_id' => 'required|exists:admission_periods,id',
            'form.exam_type' => 'required|in:written_test,interview,practical,portfolio,other',
            'form.title' => 'required|string|max:255',
            'form.exam_date' => 'required|date',
            'form.exam_time' => 'required|date_format:H:i',
            'form.venue' => 'nullable|string|max:255',
            'form.meeting_link' => 'nullable|url|max:255',
            'form.quota' => 'required|integer|min:0',
            'form.notes' => 'nullable|string',
            'form.is_active' => 'boolean',
        ])['form'];

        AdmissionExamSchedule::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Jadwal seleksi berhasil dibuat.');
        $this->redirectRoute('admin.admission.admission-exam-schedules.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Create Exam Schedule',
        ]);
    }
};
?>

<div class="row">
    <div class="col-12">
        <x-alert />

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Create Exam Schedule</h3>
                <a href="{{ route('admin.admission.admission-exam-schedules.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    @include('components.admin.admission.admission-exam-schedules._form')
                </form>
            </div>
        </div>
    </div>
</div>
