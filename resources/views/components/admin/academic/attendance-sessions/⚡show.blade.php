<?php

use App\Models\Academic\AttendanceRecord;
use App\Models\Academic\AttendanceSession;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudyPlanDetail;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public AttendanceSession $session;
    public int $offeringId;
    public array $sessionForm = [];
    public array $lecturerOptions = [];
    public array $students = [];
    public array $recordForms = [];

    public function mount($offeringId, $id): void
    {
        $this->offeringId = (int) $offeringId;
        $this->session = AttendanceSession::query()
            ->with([
                'courseOffering.course',
                'courseOffering.studyProgram',
                'lecturerProfile.user',
                'records.studentProfile.user',
            ])
            ->where('course_offering_id', $this->offeringId)
            ->findOrFail($id);

        $this->sessionForm = [
            'meeting_no' => $this->session->meeting_no,
            'meeting_date' => $this->session->meeting_date?->format('Y-m-d'),
            'start_time' => $this->session->start_time?->format('H:i'),
            'end_time' => $this->session->end_time?->format('H:i'),
            'lecturer_profile_id' => $this->session->lecturer_profile_id,
            'status' => $this->session->status,
            'topic' => $this->session->topic,
            'notes' => $this->session->notes,
        ];

        $this->lecturerOptions = CourseOfferingLecturer::query()
            ->where('course_offering_id', $this->offeringId)
            ->with('lecturerProfile.user')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->lecturer_profile_id,
                'name' => $row->lecturerProfile?->user?->name ?? '-',
            ])
            ->unique('id')
            ->values()
            ->toArray();

        $this->loadStudentsAndRecords();
    }

    public function loadStudentsAndRecords(): void
    {
        $details = StudyPlanDetail::query()
            ->where('course_offering_id', $this->offeringId)
            ->where('status', 'Taken')
            ->whereHas('studyPlan', fn ($query) => $query->where('status', 'Approved'))
            ->with('studyPlan.studentProfile.user')
            ->get();

        $students = $details
            ->map(fn ($detail) => $detail->studyPlan?->studentProfile)
            ->filter()
            ->unique('id')
            ->values();

        $existingRecords = AttendanceRecord::query()
            ->where('attendance_session_id', $this->session->id)
            ->get()
            ->keyBy('student_profile_id');

        $this->students = $students
            ->map(fn ($studentProfile) => [
                'id' => $studentProfile->id,
                'name' => $studentProfile->user?->name ?? '-',
                'nim' => $studentProfile->nim,
            ])
            ->toArray();

        foreach ($this->students as $student) {
            $record = $existingRecords->get($student['id']);
            $this->recordForms[$student['id']] = [
                'status' => $record?->status ?? 'Absent',
                'notes' => $record?->notes,
            ];
        }
    }

    public function saveSession(): void
    {
        $validated = $this->validate([
            'sessionForm.meeting_no' => 'nullable|integer|min:1|max:64',
            'sessionForm.meeting_date' => 'required|date',
            'sessionForm.start_time' => 'nullable|date_format:H:i',
            'sessionForm.end_time' => 'nullable|date_format:H:i|after:sessionForm.start_time',
            'sessionForm.lecturer_profile_id' => 'nullable|integer|exists:lecturer_profiles,id',
            'sessionForm.status' => 'required|in:Draft,Opened,Closed,Cancelled',
            'sessionForm.topic' => 'nullable|string|max:255',
            'sessionForm.notes' => 'nullable|string',
        ]);

        $this->session->update([
            'meeting_no' => $validated['sessionForm']['meeting_no'] ?: null,
            'meeting_date' => $validated['sessionForm']['meeting_date'],
            'start_time' => $validated['sessionForm']['start_time'] ?: null,
            'end_time' => $validated['sessionForm']['end_time'] ?: null,
            'lecturer_profile_id' => $validated['sessionForm']['lecturer_profile_id'] ?: null,
            'status' => $validated['sessionForm']['status'],
            'topic' => $validated['sessionForm']['topic'] ?: null,
            'notes' => $validated['sessionForm']['notes'] ?: null,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Session berhasil diperbarui.');
    }

    public function saveRecord(int $studentProfileId): void
    {
        if (! isset($this->recordForms[$studentProfileId])) {
            return;
        }

        $status = $this->recordForms[$studentProfileId]['status'] ?? 'Absent';
        $notes = $this->recordForms[$studentProfileId]['notes'] ?? null;

        if (! in_array($status, ['Present', 'Absent', 'Excused', 'Sick', 'Late'], true)) {
            session()->flash('error', 'Status kehadiran tidak valid.');

            return;
        }

        $record = AttendanceRecord::query()->firstOrNew([
            'attendance_session_id' => $this->session->id,
            'student_profile_id' => $studentProfileId,
        ]);

        if (! $record->exists) {
            $record->created_by = auth()->id();
        }

        $record->status = $status;
        $record->notes = $notes ?: null;
        $record->recorded_at = now();
        $record->recorded_by = auth()->id();
        $record->updated_by = auth()->id();
        $record->save();

        session()->flash('success', 'Absensi mahasiswa berhasil disimpan.');
    }

    public function saveAllRecords(): void
    {
        DB::transaction(function (): void {
            foreach ($this->students as $student) {
                $studentProfileId = (int) $student['id'];
                $status = $this->recordForms[$studentProfileId]['status'] ?? 'Absent';
                $notes = $this->recordForms[$studentProfileId]['notes'] ?? null;

                $record = AttendanceRecord::query()->firstOrNew([
                    'attendance_session_id' => $this->session->id,
                    'student_profile_id' => $studentProfileId,
                ]);

                if (! $record->exists) {
                    $record->created_by = auth()->id();
                }

                $record->status = in_array($status, ['Present', 'Absent', 'Excused', 'Sick', 'Late'], true) ? $status : 'Absent';
                $record->notes = $notes ?: null;
                $record->recorded_at = now();
                $record->recorded_by = auth()->id();
                $record->updated_by = auth()->id();
                $record->save();
            }
        });

        session()->flash('success', 'Semua attendance record berhasil disimpan.');
    }

    public function goBack(): void
    {
        $this->redirectRoute('admin.academic.course-offerings.show', ['id' => $this->offeringId]);
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Attendance Session Detail',
            ]);
    }
};
?>

<div class="row">
    <div class="col-12">
        <x-alert />

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Detail Attendance Session</h5>
                <button class="btn btn-secondary" wire:click="goBack">
                    <i class="fas fa-arrow-left me-1"></i> Kembali ke Course Offering
                </button>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>{{ $session->courseOffering?->course?->code }} - {{ $session->courseOffering?->course?->name }}</strong>
                    <div class="text-muted small">{{ $session->courseOffering?->studyProgram?->name ?? '-' }}</div>
                </div>

                <form wire:submit.prevent="saveSession">
                    <div class="row">
                        <div class="form-group col-lg-2 col-md-4 col-sm-6">
                            <label>Pertemuan Ke</label>
                            <input type="number" min="1" max="64" class="form-control" wire:model.defer="sessionForm.meeting_no">
                            @error('sessionForm.meeting_no') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group col-lg-3 col-md-4 col-sm-6">
                            <label>Tanggal</label>
                            <input type="date" class="form-control" wire:model.defer="sessionForm.meeting_date">
                            @error('sessionForm.meeting_date') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group col-lg-2 col-md-4 col-sm-6">
                            <label>Jam Mulai</label>
                            <input type="time" class="form-control" wire:model.defer="sessionForm.start_time">
                            @error('sessionForm.start_time') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group col-lg-2 col-md-4 col-sm-6">
                            <label>Jam Selesai</label>
                            <input type="time" class="form-control" wire:model.defer="sessionForm.end_time">
                            @error('sessionForm.end_time') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group col-lg-3 col-md-8 col-sm-12">
                            <label>Dosen Pengajar</label>
                            <select class="form-control" wire:model.defer="sessionForm.lecturer_profile_id">
                                <option value="">- Pilih Dosen -</option>
                                @foreach ($lecturerOptions as $lecturer)
                                    <option value="{{ $lecturer['id'] }}">{{ $lecturer['name'] }}</option>
                                @endforeach
                            </select>
                            @error('sessionForm.lecturer_profile_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group col-lg-3 col-md-4 col-sm-6">
                            <label>Status Session</label>
                            <select class="form-control" wire:model.defer="sessionForm.status">
                                <option value="Draft">Draft</option>
                                <option value="Opened">Opened</option>
                                <option value="Closed">Closed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                            @error('sessionForm.status') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group col-lg-9 col-md-8 col-sm-12">
                            <label>Topik</label>
                            <input type="text" class="form-control" wire:model.defer="sessionForm.topic">
                            @error('sessionForm.topic') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group col-12">
                            <label>Catatan</label>
                            <textarea rows="2" class="form-control" wire:model.defer="sessionForm.notes"></textarea>
                            @error('sessionForm.notes') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group col-12 mt-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Simpan Session
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Attendance Records</h5>
                <button class="btn btn-success" wire:click="saveAllRecords">
                    <i class="fas fa-save me-1"></i> Simpan Semua
                </button>
            </div>
            <div class="card-body">
                @if (count($students) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;">No</th>
                                    <th>Mahasiswa</th>
                                    <th style="width: 180px;">Status</th>
                                    <th>Catatan</th>
                                    <th style="width: 120px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($students as $index => $student)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $student['name'] }}</div>
                                            <small class="text-muted">NIM: {{ $student['nim'] ?? '-' }}</small>
                                        </td>
                                        <td>
                                            <select class="form-control form-control-sm" wire:model.defer="recordForms.{{ $student['id'] }}.status">
                                                <option value="Present">Present</option>
                                                <option value="Absent">Absent</option>
                                                <option value="Excused">Excused</option>
                                                <option value="Sick">Sick</option>
                                                <option value="Late">Late</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" wire:model.defer="recordForms.{{ $student['id'] }}.notes" placeholder="Catatan absensi">
                                        </td>
                                        <td>
                                            <button class="btn  btn-primary" wire:click="saveRecord({{ $student['id'] }})">
                                                Simpan
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-warning mb-0">
                        Belum ada mahasiswa terdaftar di course offering ini.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
