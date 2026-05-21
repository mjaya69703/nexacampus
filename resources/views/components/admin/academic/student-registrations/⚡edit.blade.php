<?php

use Livewire\Component;
use App\Models\Academic\StudentRegistration;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public $registration;
    public $registrationForm = [];
    public bool $showApprovalForm = false;
    public string $approvalStatus = 'Approved';
    public string $approvalNotes = '';

    public function mount($id): void
    {
        $this->registration = StudentRegistration::with([
            'studentProfile.user',
            'studentProfile.studyProgram',
            'academicYear',
            'approvedBy',
        ])->findOrFail($id);

        $this->registrationForm = $this->registration->toArray();
    }

    public function cancel(): void
    {
        $this->redirect(route('admin.academic.student-registrations.index'));
    }

    public function openApprovalForm(): void
    {
        $this->showApprovalForm = true;
        $this->approvalStatus = 'Approved';
        $this->approvalNotes = '';
    }

    public function closeApprovalForm(): void
    {
        $this->showApprovalForm = false;
    }

    public function submitApproval(): void
    {
        $this->validate([
            'approvalStatus' => 'required|in:Approved,Rejected',
            'approvalNotes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $updateData = [
                'registration_status' => $this->approvalStatus,
                'updated_by' => auth()->id(),
            ];

            if ($this->approvalStatus === 'Approved') {
                $updateData['approved_at'] = now();
                $updateData['approved_by'] = auth()->id();

                if (
                    $this->registration->academic_status !== 'Cuti'
                    && $this->registration->semester_no !== null
                    && $this->registration->studentProfile
                ) {
                    $this->registration->studentProfile->update([
                        'current_semester' => $this->registration->semester_no,
                        'updated_by' => auth()->id(),
                    ]);
                }

                $message = 'Registrasi berhasil disetujui!';
            } else {
                $message = 'Registrasi berhasil ditolak!';
            }

            if ($this->approvalNotes) {
                $updateData['notes'] = $this->approvalNotes;
            }

            $this->registration->update($updateData);

            DB::commit();
            session()->flash('success', $message);
            $this->registration->refresh();
            $this->closeApprovalForm();
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function update(): void
    {
        $this->validate([
            'registrationForm.semester_no' => 'nullable|integer|min:1|max:14',
            'registrationForm.academic_status' => 'required|in:Aktif,Cuti,Nonaktif,Lulus,Drop Out,Keluar',
            'registrationForm.is_active' => 'nullable|boolean',
            'registrationForm.notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $this->registration->update(array_merge(
                $this->registrationForm,
                ['updated_by' => auth()->id()]
            ));

            DB::commit();
            $this->registration->refresh();
            session()->flash('success', 'Registrasi berhasil diperbarui!');
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Edit Student Registration',
            ]);
    }
};
?>

<div class="row">
    <div class="col-12">
        <x-alert />

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Info Mahasiswa & Registrasi</h5>
                <div>
                    @activecan('student-registration.view')
                        <a href="{{ route('admin.academic.student-registrations.show', ['id' => $registration->id]) }}" class="btn btn-info ">
                            <i class="fas fa-eye me-1"></i> Lihat Detail
                        </a>
                    @endactivecan
                    <button type="button" class="btn btn-secondary " wire:click="cancel">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div><strong>Nama Mahasiswa:</strong> {{ $registration->studentProfile?->user?->name }}</div>
                    </div>
                    <div class="col-md-6">
                        <div><strong>NIM:</strong> {{ $registration->studentProfile?->nim }}</div>
                    </div>
                    <div class="col-md-6">
                        <div><strong>Program Studi:</strong> {{ $registration->studentProfile?->studyProgram?->name }}</div>
                    </div>
                    <div class="col-md-6">
                        <div><strong>Email:</strong> {{ $registration->studentProfile?->user?->email }}</div>
                    </div>
                    <div class="col-md-4">
                        <div><strong>Tahun Akademik:</strong> {{ $registration->academicYear?->name }}</div>
                    </div>
                    <div class="col-md-4">
                        <div><strong>Semester:</strong> {{ $registration->semester_no ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div>
                            <strong>Status Registrasi:</strong>
                            <span class="badge @if($registration->registration_status === 'Approved') bg-success @elseif($registration->registration_status === 'Rejected') bg-danger @elseif($registration->registration_status === 'Submitted') bg-warning @else bg-secondary @endif">
                                {{ $registration->registration_status }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Form Edit & Approval Registrasi</h5>
            </div>
            <div class="card-body">
                @if($showApprovalForm)
                    <div class="alert alert-info mb-3">
                        <strong>Approval Workflow</strong>
                        <p class="mb-0 mt-2">Silakan pilih status approval untuk registrasi mahasiswa ini.</p>
                    </div>

                    <form wire:submit.prevent="submitApproval" class="mb-4 p-3 bg-light rounded">
                        <div class="mb-3">
                            <label class="form-label">Status Approval <span class="text-danger">*</span></label>
                            <div>
                                <label class="form-check mb-2">
                                    <input class="form-check-input" type="radio" wire:model="approvalStatus" value="Approved">
                                    <span class="form-check-label">
                                        <span class="badge bg-success">Setujui Registrasi</span>
                                    </span>
                                </label>
                                <label class="form-check">
                                    <input class="form-check-input" type="radio" wire:model="approvalStatus" value="Rejected">
                                    <span class="form-check-label">
                                        <span class="badge bg-danger">Tolak Registrasi</span>
                                    </span>
                                </label>
                            </div>
                            @error('approvalStatus') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Catatan Approval</label>
                            <textarea class="form-control" wire:model="approvalNotes" rows="3" placeholder="Berikan catatan untuk mahasiswa..."></textarea>
                            @error('approvalNotes') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check me-1"></i> Kirim Approval
                            </button>
                            <button type="button" class="btn btn-secondary" wire:click="closeApprovalForm">
                                Batal
                            </button>
                        </div>
                    </form>

                    <hr>
                @endif

                <form wire:submit.prevent="update">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Semester</label>
                            <input type="number" class="form-control" min="1" max="14" wire:model="registrationForm.semester_no">
                            @error('registrationForm.semester_no') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status Akademik <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="registrationForm.academic_status" required>
                                <option value="">Pilih Status</option>
                                <option value="Aktif">Aktif</option>
                                <option value="Cuti">Cuti</option>
                                <option value="Nonaktif">Nonaktif</option>
                                <option value="Lulus">Lulus</option>
                                <option value="Drop Out">Drop Out</option>
                                <option value="Keluar">Keluar</option>
                            </select>
                            @error('registrationForm.academic_status') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" wire:model="registrationForm.is_active">
                                <span class="form-check-label">Registrasi Aktif</span>
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea class="form-control" wire:model="registrationForm.notes" rows="3"></textarea>
                        @error('registrationForm.notes') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                        @if($registration->registration_status === 'Submitted' || $registration->registration_status === 'Draft')
                            @activecan('student-registration.update')
                                <button type="button" class="btn btn-success" wire:click="openApprovalForm">
                                    <i class="fas fa-check me-1"></i> Approval Registrasi
                                </button>
                            @endactivecan
                        @endif
                    </div>
                </form>
            </div>
        </div>

        @if($registration->approvedBy)
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informasi Approval</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div><strong>Disetujui Oleh:</strong> {{ $registration->approvedBy?->name }}</div>
                        </div>
                        <div class="col-md-6">
                            <div><strong>Tanggal Approval:</strong> {{ $registration->approved_at?->format('d F Y H:i') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
