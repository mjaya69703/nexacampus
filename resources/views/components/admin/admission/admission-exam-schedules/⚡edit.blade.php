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

        session()->flash('success', 'Jadwal seleksi & ujian berhasil diperbarui.');
        $this->redirectRoute('admin.admission.admission-exam-schedules.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Edit Jadwal Ujian',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.admission.header
        title="Edit Jadwal Ujian & Seleksi"
        description="Perbarui informasi jadwal, kuota peserta, tautan pertemuan, atau catatan khusus pelaksanaan tes seleksi."
        icon="calendar-check"
    >
        <a href="{{ route('admin.admission.admission-exam-schedules.index') }}" class="btn btn-light rounded-pill px-4 py-2 text-dark fw-bold shadow-sm d-inline-flex align-items-center gap-2 border-0">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar
        </a>
    </x-admin.admission.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-4">
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="fas fa-edit text-primary"></i> Perbarui Formulir Jadwal Ujian
            </h4>
            <p class="text-muted fs-7 mb-0">Sesuaikan data ruangan, tanggal pelaksanaan, dan status aktif sesi ujian ini.</p>
        </div>
        <div class="card-body p-4">
            <form wire:submit.prevent="save">
                @include('components.admin.admission.admission-exam-schedules._form')
            </form>
        </div>
    </div>
</div>
