<?php

use App\Models\Admission\AdmissionExamSchedule;
use App\Models\Admission\AdmissionPeriod;
use Livewire\Component;

new class extends Component
{
    public AdmissionExamSchedule $schedule;

    public array $periods = [];

    public array $form = [];

    public function mount($id): void
    {
        $this->schedule = AdmissionExamSchedule::findOrFail($id);
        $this->periods = AdmissionPeriod::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'code'])
            ->map(fn (AdmissionPeriod $period) => [
                'id' => $period->id,
                'label' => $period->name.' ('.$period->code.')',
            ])
            ->toArray();

        $this->form = [
            'admission_period_id' => $this->schedule->admission_period_id,
            'exam_type' => $this->schedule->exam_type,
            'title' => $this->schedule->title,
            'exam_date' => $this->schedule->exam_date?->format('Y-m-d'),
            'exam_time' => $this->schedule->exam_time?->format('H:i'),
            'venue' => $this->schedule->venue,
            'meeting_link' => $this->schedule->meeting_link,
            'quota' => $this->schedule->quota,
            'notes' => $this->schedule->notes,
            'is_active' => $this->schedule->is_active,
        ];
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

        $this->schedule->update([
            ...$validated,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Jadwal seleksi berhasil diperbarui.');
        $this->redirectRoute('admin.admission.admission-exam-schedules.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Edit Exam Schedule',
        ]);
    }
};
?>

<div class="row">
    <div class="col-12">
        <x-alert />

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Edit Exam Schedule</h3>
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
