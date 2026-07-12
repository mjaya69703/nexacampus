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

<div>
    <x-alert />

    <x-admin.academic.header
        title="Edit & Approval Registrasi Mahasiswa"
        description="Perbarui data registrasi semester, ubah status akademik, atau lakukan proses persetujuan (approval)."
        icon="user-check"
    >
        <div class="d-flex align-items-center gap-2">
            @activecan('student-registration.view')
                <a href="{{ route('admin.academic.student-registrations.show', ['id' => $registration->id]) }}" class="btn btn-sm btn-light text-primary fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2">
                    <i class="fa fa-eye"></i> <span>Lihat Detail</span>
                </a>
            @endactivecan
            <button type="button" class="btn btn-sm btn-light text-dark fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2" wire:click="cancel">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </button>
        </div>
    </x-admin.academic.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-user-graduate fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Informasi Mahasiswa & Registrasi</h4>
                            <div class="text-muted small">Ringkasan biodata mahasiswa yang terdaftar pada semester ini.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Nama Mahasiswa</label>
                            <div class="fw-bold text-dark fs-6">{{ $registration->studentProfile?->user?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Nomor Induk Mahasiswa (NIM)</label>
                            <div class="fw-semibold text-dark">{{ $registration->studentProfile?->nim ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Program Studi</label>
                            <div class="fw-semibold text-dark">{{ $registration->studentProfile?->studyProgram?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Alamat Email</label>
                            <div class="fw-semibold text-dark">{{ $registration->studentProfile?->user?->email ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small d-block mb-1">Tahun Akademik</label>
                            <div class="fw-bold text-primary">{{ $registration->academicYear?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small d-block mb-1">Semester Ke</label>
                            <div class="fw-semibold text-dark">{{ $registration->semester_no ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small d-block mb-1">Status Registrasi Saat Ini</label>
                            <div>
                                <span class="badge rounded-pill px-3 py-2 @if($registration->registration_status === 'Approved') bg-success @elseif($registration->registration_status === 'Rejected') bg-danger @elseif($registration->registration_status === 'Submitted') bg-warning text-dark @else bg-secondary @endif">
                                    {{ $registration->registration_status }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-edit fs-5"></i>
                            </div>
                            <div>
                                <h4 class="card-title fw-bold mb-1 text-dark">Form Edit Parameter Registrasi</h4>
                                <div class="text-muted small">Perbarui semester, status akademik, dan catatan mahasiswa.</div>
                            </div>
                        </div>
                        @if($registration->registration_status === 'Submitted' || $registration->registration_status === 'Draft')
                            @activecan('student-registration.update')
                                <button type="button" class="btn btn-success rounded-pill px-3 py-2 shadow-sm fw-semibold d-inline-flex align-items-center gap-2" wire:click="openApprovalForm">
                                    <i class="fa fa-check-circle"></i> <span>Approval Registrasi</span>
                                </button>
                            @endactivecan
                        @endif
                    </div>
                </div>
                <div class="card-body p-4">
                    @if($showApprovalForm)
                        <div class="alert alert-info border-0 shadow-sm rounded-4 mb-4 p-4">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="fa fa-check-circle fs-5 text-info"></i>
                                <h6 class="fw-bold mb-0 text-dark">Proses Approval Registrasi</h6>
                            </div>
                            <p class="small text-muted mb-3">Pilih apakah pengajuan her-registrasi mahasiswa ini disetujui atau ditolak.</p>

                            <form wire:submit.prevent="submitApproval" class="bg-white p-4 rounded-4 shadow-sm border">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Keputusan Approval <span class="text-danger">*</span></label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check border rounded-3 p-3 px-4 flex-fill @if($approvalStatus === 'Approved') border-success bg-success bg-opacity-10 @endif">
                                            <input class="form-check-input me-2" type="radio" id="appApproved" wire:model="approvalStatus" value="Approved">
                                            <label class="form-check-label fw-bold text-success cursor-pointer" for="appApproved">
                                                <i class="fa fa-check me-1"></i> Setujui Registrasi (Approved)
                                            </label>
                                        </div>
                                        <div class="form-check border rounded-3 p-3 px-4 flex-fill @if($approvalStatus === 'Rejected') border-danger bg-danger bg-opacity-10 @endif">
                                            <input class="form-check-input me-2" type="radio" id="appRejected" wire:model="approvalStatus" value="Rejected">
                                            <label class="form-check-label fw-bold text-danger cursor-pointer" for="appRejected">
                                                <i class="fa fa-times me-1"></i> Tolak Registrasi (Rejected)
                                            </label>
                                        </div>
                                    </div>
                                    @error('approvalStatus') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Catatan Approval (Opsional)</label>
                                    <textarea class="form-control" wire:model="approvalNotes" rows="3" placeholder="Berikan catatan alasan persetujuan atau penolakan kepada mahasiswa..."></textarea>
                                    @error('approvalNotes') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>

                                <div class="d-flex justify-content-end gap-2 border-top pt-3 mt-3">
                                    <button type="button" class="btn btn-light rounded-pill px-3 py-2" wire:click="closeApprovalForm">
                                        <i class="fa fa-times me-1"></i> Batal
                                    </button>
                                    <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-semibold">
                                        <i class="fa fa-check me-1"></i> Simpan Keputusan Approval
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif

                    <form wire:submit.prevent="update">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Semester Ke</label>
                                <input type="number" class="form-control" min="1" max="14" wire:model="registrationForm.semester_no" placeholder="Contoh: 3">
                                @error('registrationForm.semester_no') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status Akademik <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model="registrationForm.academic_status" required>
                                    <option value="">Pilih Status</option>
                                    <option value="Aktif">Aktif</option>
                                    <option value="Cuti">Cuti</option>
                                    <option value="Nonaktif">Nonaktif</option>
                                    <option value="Lulus">Lulus</option>
                                    <option value="Drop Out">Drop Out</option>
                                    <option value="Keluar">Keluar</option>
                                </select>
                                @error('registrationForm.academic_status') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Catatan Registrasi</label>
                                <textarea class="form-control" wire:model="registrationForm.notes" rows="3" placeholder="Catatan internal mengenai registrasi..."></textarea>
                                @error('registrationForm.notes') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12 border-top pt-3 mt-4 d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-semibold">
                                    <i class="fa fa-save me-1"></i> Simpan Perubahan
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-sliders fs-5"></i>
                        </div>
                        <div>
                            <h5 class="card-title fw-bold mb-1 text-dark">Status Keaktifan</h5>
                            <div class="text-muted small">Atur hak akses akademik.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="regActive" wire:model="registrationForm.is_active">
                        <label class="form-check-label fw-semibold" for="regActive">Registrasi Aktif</label>
                        <div class="text-muted small mt-1">Jika nonaktif, mahasiswa tidak dapat melakukan pengisian maupun perubahan KRS.</div>
                    </div>
                </div>
            </div>

            @if($registration->approvedBy)
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-stamp fs-5"></i>
                            </div>
                            <div>
                                <h5 class="card-title fw-bold mb-1 text-dark">Informasi Approval</h5>
                                <div class="text-muted small">Riwayat pengesahan.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="text-muted small d-block mb-1">Disetujui / Divalidasi Oleh</label>
                            <div class="fw-bold text-dark">{{ $registration->approvedBy?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <label class="text-muted small d-block mb-1">Waktu Approval</label>
                            <div class="fw-semibold text-dark">{{ $registration->approved_at?->format('d F Y H:i') ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
