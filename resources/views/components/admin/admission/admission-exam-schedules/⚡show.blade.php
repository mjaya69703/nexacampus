<?php

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionExamParticipant;
use App\Models\Admission\AdmissionExamSchedule;
use App\Support\ActivePermission;
use App\Support\Admission\AdmissionSelectionService;
use Livewire\Component;

new class extends Component
{
    public AdmissionExamSchedule $schedule;

    public string $applicationId = '';

    public array $attendanceForms = [];

    public array $scoreForms = [];

    public function mount($id): void
    {
        $this->loadSchedule($id);
    }

    public function assignParticipant(): void
    {
        abort_unless(ActivePermission::check('admission-exam-schedule.update'), 403);

        $validated = $this->validate([
            'applicationId' => 'required|exists:admission_applications,id',
        ]);

        $exists = $this->schedule->participants()
            ->where('admission_application_id', $validated['applicationId'])
            ->exists();

        if ($exists) {
            session()->flash('error', 'Applicant sudah terdaftar pada jadwal ini.');
            return;
        }

        if ($this->schedule->quota > 0 && $this->schedule->participants()->count() >= $this->schedule->quota) {
            session()->flash('error', 'Quota jadwal seleksi sudah penuh.');
            return;
        }

        $this->schedule->participants()->create([
            'admission_application_id' => $validated['applicationId'],
            'created_by' => auth()->id(),
        ]);

        $this->syncRegisteredCount();
        $this->applicationId = '';
        session()->flash('success', 'Applicant berhasil ditambahkan ke jadwal seleksi.');
        $this->loadSchedule($this->schedule->id);
    }

    public function assignAllFromPeriod(): void
    {
        abort_unless(ActivePermission::check('admission-exam-schedule.update'), 403);

        $assignedIds = $this->schedule->participants()
            ->pluck('admission_application_id');

        $remainingSlots = $this->schedule->quota > 0
            ? max(0, $this->schedule->quota - $this->schedule->participants()->count())
            : null;

        if ($remainingSlots === 0) {
            session()->flash('error', 'Quota jadwal seleksi sudah penuh.');
            return;
        }

        $applications = AdmissionApplication::query()
            ->where('admission_period_id', $this->schedule->admission_period_id)
            ->whereIn('status', ['submitted', 'under_review', 'waitlisted'])
            ->whereNotIn('id', $assignedIds)
            ->orderBy('submitted_at')
            ->orderBy('full_name')
            ->when($remainingSlots !== null, fn ($query) => $query->limit($remainingSlots))
            ->get(['id']);

        if ($applications->isEmpty()) {
            session()->flash('error', 'Tidak ada applicant baru dari periode ini yang bisa ditambahkan.');
            return;
        }

        $now = now();
        $rows = $applications->map(fn (AdmissionApplication $application) => [
            'admission_exam_schedule_id' => $this->schedule->id,
            'admission_application_id' => $application->id,
            'attendance_status' => 'registered',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        AdmissionExamParticipant::insert($rows);
        $this->syncRegisteredCount();

        session()->flash('success', $applications->count().' applicant berhasil ditambahkan dari periode yang sama.');
        $this->loadSchedule($this->schedule->id);
    }

    public function updateAttendance(int $participantId): void
    {
        abort_unless(ActivePermission::check('admission-exam-schedule.update'), 403);

        $participant = $this->schedule->participants()->whereKey($participantId)->firstOrFail();
        $form = $this->attendanceForms[$participantId] ?? [];

        validator($form, [
            'attendance_status' => 'required|in:registered,present,absent',
            'notes' => 'nullable|string',
        ])->validate();

        $participant->update([
            'attendance_status' => $form['attendance_status'],
            'notes' => $form['notes'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Attendance berhasil diperbarui.');
        $this->loadSchedule($this->schedule->id);
    }

    public function saveScore(int $participantId, AdmissionSelectionService $selectionService): void
    {
        abort_unless(ActivePermission::check('admission-exam-schedule.update'), 403);

        $participant = $this->schedule->participants()->with('application')->whereKey($participantId)->firstOrFail();
        $form = $this->scoreForms[$participantId] ?? [];

        validator($form, [
            'score_type' => 'required|string|max:100',
            'score' => 'required|numeric|min:0|max:100',
            'weight' => 'required|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ])->validate();

        $participant->application->scores()->updateOrCreate(
            [
                'admission_exam_schedule_id' => $this->schedule->id,
                'score_type' => $form['score_type'],
            ],
            [
                'score' => $form['score'],
                'weight' => $form['weight'],
                'notes' => $form['notes'] ?? null,
                'scored_by' => auth()->id(),
            ],
        );

        $selectionService->recalculateFinalScore($participant->application);

        session()->flash('success', 'Score berhasil disimpan dan final score dihitung ulang.');
        $this->loadSchedule($this->schedule->id);
    }

    public function removeParticipant(int $participantId): void
    {
        abort_unless(ActivePermission::check('admission-exam-schedule.update'), 403);

        $this->schedule->participants()->whereKey($participantId)->delete();
        $this->syncRegisteredCount();
        session()->flash('success', 'Participant berhasil dihapus.');
        $this->loadSchedule($this->schedule->id);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Exam Schedule Detail',
        ]);
    }

    public function eligibleApplications()
    {
        $assignedIds = $this->schedule->participants->pluck('admission_application_id');

        return AdmissionApplication::query()
            ->where('admission_period_id', $this->schedule->admission_period_id)
            ->whereIn('status', ['submitted', 'under_review', 'waitlisted'])
            ->whereNotIn('id', $assignedIds)
            ->orderBy('full_name')
            ->get(['id', 'application_number', 'full_name']);
    }

    private function loadSchedule($id): void
    {
        $this->schedule = AdmissionExamSchedule::with([
            'period',
            'participants.application.studyProgram',
            'participants.application.scores',
        ])->findOrFail($id);

        $this->attendanceForms = $this->schedule->participants
            ->mapWithKeys(fn (AdmissionExamParticipant $participant) => [
                $participant->id => [
                    'attendance_status' => $participant->attendance_status,
                    'notes' => $participant->notes,
                ],
            ])
            ->toArray();

        $this->scoreForms = $this->schedule->participants
            ->mapWithKeys(function (AdmissionExamParticipant $participant) {
                $score = $participant->application->scores
                    ->where('admission_exam_schedule_id', $this->schedule->id)
                    ->first();

                return [
                    $participant->id => [
                        'score_type' => $score?->score_type ?? $this->schedule->exam_type,
                        'score' => $score?->score,
                        'weight' => $score?->weight ?? 100,
                        'notes' => $score?->notes,
                    ],
                ];
            })
            ->toArray();
    }

    private function syncRegisteredCount(): void
    {
        $this->schedule->update([
            'registered_count' => $this->schedule->participants()->count(),
            'updated_by' => auth()->id(),
        ]);
    }
};
?>

<div>
    <x-alert />

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">{{ $schedule->title }}</h3>
                <small class="text-muted">{{ $schedule->period?->name }} - {{ str($schedule->exam_type)->replace('_', ' ')->title() }}</small>
            </div>
            <div class="d-flex gap-2">
                @activecan('admission-exam-schedule.update')
                    <a href="{{ route('admin.admission.admission-exam-schedules.edit', ['id' => $schedule->id]) }}" class="btn btn-warning">
                        <i class="fas fa-edit me-1"></i> Edit
                    </a>
                @endactivecan
                <a href="{{ route('admin.admission.admission-exam-schedules.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><div class="p-3 bg-light rounded"><small class="text-muted">Date</small><div class="fw-bold">{{ $schedule->exam_date?->format('d F Y') }}</div></div></div>
                <div class="col-md-3"><div class="p-3 bg-light rounded"><small class="text-muted">Time</small><div class="fw-bold">{{ $schedule->exam_time?->format('H:i') }}</div></div></div>
                <div class="col-md-3"><div class="p-3 bg-light rounded"><small class="text-muted">Venue</small><div class="fw-bold">{{ $schedule->venue ?? 'Online / TBA' }}</div></div></div>
                <div class="col-md-3"><div class="p-3 bg-light rounded"><small class="text-muted">Participants</small><div class="fw-bold">{{ $schedule->participants->count() }} / {{ $schedule->quota ?: '∞' }}</div></div></div>
                @if($schedule->meeting_link)
                    <div class="col-12"><a href="{{ $schedule->meeting_link }}" target="_blank" class="btn btn-outline-primary"><i class="fas fa-video me-1"></i> Open Meeting Link</a></div>
                @endif
            </div>
        </div>
    </div>

    @activecan('admission-exam-schedule.update')
        <div class="card mb-4">
            <div class="card-header"><h4 class="card-title mb-0">Assign Participant</h4></div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-9">
                        <select class="form-select" wire:model="applicationId">
                            <option value="">Choose applicant from this period</option>
                            @foreach($this->eligibleApplications() as $application)
                                <option value="{{ $application->id }}">{{ $application->application_number }} - {{ $application->full_name }}</option>
                            @endforeach
                        </select>
                        @error('applicationId') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary w-100" wire:click="assignParticipant">
                            <i class="fas fa-user-plus me-1"></i> Add
                        </button>
                        <button class="btn btn-outline-primary" wire:click="assignAllFromPeriod" wire:loading.attr="disabled" wire:target="assignAllFromPeriod">
                            <i class="fas fa-users me-1"></i>
                            <span wire:loading.remove wire:target="assignAllFromPeriod">Add All From Same Period</span>
                            <span wire:loading wire:target="assignAllFromPeriod">Adding applicants...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endactivecan

    <div class="card">
        <div class="card-header"><h4 class="card-title mb-0">Participants & Scores</h4></div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Program</th>
                        <th>Attendance</th>
                        <th>Score</th>
                        <th>Final</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedule->participants as $participant)
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $participant->application?->full_name }}</div>
                                <small class="text-muted">{{ $participant->application?->application_number }}</small>
                            </td>
                            <td>{{ $participant->application?->studyProgram?->name ?? '-' }}</td>
                            <td style="min-width:220px;">
                                <select class="form-select form-select-sm mb-2" wire:model.defer="attendanceForms.{{ $participant->id }}.attendance_status">
                                    <option value="registered">Registered</option>
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                </select>
                                <input type="text" class="form-control form-control-sm" placeholder="Attendance notes" wire:model.defer="attendanceForms.{{ $participant->id }}.notes">
                            </td>
                            <td style="min-width:280px;">
                                <div class="row g-1">
                                    <div class="col-5"><input class="form-control form-control-sm" wire:model.defer="scoreForms.{{ $participant->id }}.score_type" placeholder="type"></div>
                                    <div class="col-3"><input type="number" step="0.01" class="form-control form-control-sm" wire:model.defer="scoreForms.{{ $participant->id }}.score" placeholder="score"></div>
                                    <div class="col-4"><input type="number" step="0.01" class="form-control form-control-sm" wire:model.defer="scoreForms.{{ $participant->id }}.weight" placeholder="weight"></div>
                                    <div class="col-12"><input class="form-control form-control-sm" wire:model.defer="scoreForms.{{ $participant->id }}.notes" placeholder="score notes"></div>
                                </div>
                            </td>
                            <td class="fw-bold">{{ $participant->application?->final_score ?? '-' }}</td>
                            <td class="text-end">
                                @activecan('admission-exam-schedule.update')
                                    <button class="btn btn-sm btn-outline-primary" wire:click="updateAttendance({{ $participant->id }})">Attendance</button>
                                    <button class="btn btn-sm btn-success" wire:click="saveScore({{ $participant->id }})">Score</button>
                                    <button class="btn btn-sm btn-danger" wire:click="removeParticipant({{ $participant->id }})">Remove</button>
                                @endactivecan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada participant.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
