<?php

use App\Models\Organization\EmployeeLeaveType;
use App\Models\Organization\EmployeeProfile;
use App\Support\Organization\EmployeeLeaveRequestService;
use Livewire\Component;

new class extends Component
{
    public array $form = [];
    public ?int $selectedEmployeeProfileId = null;
    public string $employeeSearch = '';

    public function mount(): void
    {
        $this->form = [
            'employee_leave_type_id' => '',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->toDateString(),
            'reason' => '',
            'employee_notes' => '',
        ];
    }

    public function selectEmployee(int $employeeId): void
    {
        $this->selectedEmployeeProfileId = $employeeId;
        $this->employeeSearch = '';
    }

    public function clearEmployee(): void
    {
        $this->selectedEmployeeProfileId = null;
    }

    public function save(EmployeeLeaveRequestService $service): void
    {
        $validated = $this->validate([
            'selectedEmployeeProfileId' => ['required', 'exists:employee_profiles,id'],
            'form.employee_leave_type_id' => ['required', 'exists:employee_leave_types,id'],
            'form.starts_at' => ['required', 'date'],
            'form.ends_at' => ['required', 'date', 'after_or_equal:form.starts_at'],
            'form.reason' => ['required', 'string', 'max:2000'],
            'form.employee_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $employee = EmployeeProfile::findOrFail($validated['selectedEmployeeProfileId']);
        $type = EmployeeLeaveType::findOrFail($validated['form']['employee_leave_type_id']);

        $request = $service->create($employee, $type, $validated['form'], auth()->id());
        $request = $service->submit($request, auth()->id());

        session()->flash('success', 'Pengajuan cuti pegawai berhasil dibuat.');
        $this->redirectRoute('admin.organization.employee-leave-requests.show', ['id' => $request->id]);
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.employee-leave-requests.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Ajukan Cuti Pegawai',
        ]);
    }

    public function getSelectedEmployeeProperty()
    {
        return $this->selectedEmployeeProfileId ? EmployeeProfile::with(['user', 'primaryWorkUnit'])->find($this->selectedEmployeeProfileId) : null;
    }

    public function getSearchableEmployeesProperty()
    {
        return EmployeeProfile::query()
            ->with(['user', 'primaryWorkUnit'])
            ->where('is_active', true)
            ->whereHas('user', function ($query) {
                $search = '%'.$this->employeeSearch.'%';
                $query->when(filled($this->employeeSearch), fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('username', 'like', $search)
                        ->orWhere('code', 'like', $search);
                }));
            })
            ->limit(12)
            ->get();
    }

    public function getLeaveTypesProperty()
    {
        return EmployeeLeaveType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'default_days_per_year']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Ajukan Permohonan Cuti"
        description="Buat pengajuan izin cuti baru untuk pegawai yang akan diverifikasi oleh atasan atau tim kepegawaian melalui sistem approval."
        icon="calendar-plus"
    >
        <a href="{{ route('admin.organization.employee-leave-requests.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.organization.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-user-clock fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Pengajuan Cuti</h4>
                            <div class="text-muted small">Lengkapi data pegawai, jenis cuti, rentang tanggal pelaksanaan, serta alasan permohonan dengan jelas.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark required">Pilih Pegawai Pemohon</label>
                            @if ($this->selectedEmployee)
                                <div class="border border-primary rounded-4 p-3 bg-primary bg-opacity-10 d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                            <i class="fas fa-user-check fs-5"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0 fs-6">{{ $this->selectedEmployee->user?->name }}</h6>
                                            <span class="small text-muted">
                                                {{ $this->selectedEmployee->employee_number ?: 'Nomor pegawai belum diisi' }}
                                                @if ($this->selectedEmployee->primaryWorkUnit)
                                                    &bull; {{ $this->selectedEmployee->primaryWorkUnit->name }}
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-medium" wire:click="clearEmployee">
                                        <i class="fas fa-sync-alt me-1"></i> Ganti Pegawai
                                    </button>
                                </div>
                            @else
                                <div class="input-group mb-2">
                                    <span class="input-group-text bg-light text-secondary rounded-start-3"><i class="fas fa-search"></i></span>
                                    <input type="search" class="form-control rounded-end-3" wire:model.live.debounce.350ms="employeeSearch" placeholder="Cari nama, email, username, atau nomor pegawai...">
                                </div>
                                <div class="list-group list-group-flush border rounded-3 overflow-hidden shadow-sm">
                                    @forelse ($this->searchableEmployees as $employee)
                                        <button type="button" class="list-group-item list-group-item-action py-3 px-4 d-flex justify-content-between align-items-center" wire:click="selectEmployee({{ $employee->id }})">
                                            <div>
                                                <span class="fw-bold text-dark d-block">{{ $employee->user?->name }}</span>
                                                <span class="small text-muted">{{ $employee->employee_number ?: 'No. Pegawai belum diisi' }}{{ $employee->user?->email ? ' - '.$employee->user?->email : '' }}</span>
                                            </div>
                                            <span class="badge bg-light text-primary rounded-pill px-3 py-1">Pilih <i class="fas fa-chevron-right ms-1"></i></span>
                                        </button>
                                    @empty
                                        <div class="list-group-item text-muted text-center py-4 small">
                                            <i class="fas fa-user-slash fs-4 d-block mb-1 text-secondary"></i> Tidak ada pegawai aktif yang cocok dengan pencarian di atas.
                                        </div>
                                    @endforelse
                                </div>
                            @endif
                            @error('selectedEmployeeProfileId') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-bold text-dark required">Jenis Cuti</label>
                            <select class="form-select rounded-3 @error('form.employee_leave_type_id') is-invalid @enderror" wire:model.defer="form.employee_leave_type_id">
                                <option value="">-- Pilih Jenis Cuti --</option>
                                @foreach ($this->leaveTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}{{ (float) $type->default_days_per_year > 0 ? ' (Jatah: '.$type->default_days_per_year.' hari/tahun)' : '' }}</option>
                                @endforeach
                            </select>
                            @error('form.employee_leave_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-bold text-dark required">Tanggal Mulai Cuti</label>
                            <input type="date" class="form-control rounded-3 @error('form.starts_at') is-invalid @enderror" wire:model.defer="form.starts_at">
                            @error('form.starts_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-bold text-dark required">Tanggal Selesai Cuti</label>
                            <input type="date" class="form-control rounded-3 @error('form.ends_at') is-invalid @enderror" wire:model.defer="form.ends_at">
                            @error('form.ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold text-dark required">Alasan Pengajuan Cuti</label>
                            <textarea class="form-control rounded-3 @error('form.reason') is-invalid @enderror @error('balance') is-invalid @enderror" rows="4" wire:model.defer="form.reason" placeholder="Tuliskan alasan lengkap mengapa pegawai mengajukan cuti pada rentang tanggal tersebut..."></textarea>
                            @error('form.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @error('balance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">Catatan Tambahan (Opsional)</label>
                            <textarea class="form-control rounded-3 @error('form.employee_notes') is-invalid @enderror" rows="2" wire:model.defer="form.employee_notes" placeholder="Catatan atau keterangan pendukung untuk approver..."></textarea>
                            @error('form.employee_notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 border-top pt-4 mt-4 d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" wire:click="cancel">
                                <i class="fas fa-times me-2"></i> Batal
                            </button>
                            <button type="button" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium" wire:click="save">
                                <i class="fas fa-paper-plane me-2"></i> Kirim & Submit Pengajuan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-circle-info"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Informasi Pengajuan</h5>
                            <div class="text-muted small">Catatan penting pengajuan cuti.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Sistem akan otomatis mengecek dan memotong <strong>Saldo Cuti</strong> yang tersedia sesuai dengan jenis cuti yang dipilih.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Setelah disubmit, permohonan akan diteruskan ke <strong>Alur Persetujuan (Approval Flow)</strong> atasan/kepegawaian.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Selama pengajuan berstatus <strong>Submitted</strong> atau <strong>In Approval</strong>, saldo cuti akan masuk ke kuota pending.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Permohonan</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Persetujuan Otomatis vs Atasan</div>
                    <p class="text-muted small mb-0">Jika jenis cuti tidak membutuhkan persetujuan khusus, status pengajuan akan langsung menjadi Approved setelah disimpan.</p>
                </div>
            </div>
        </div>
    </div>
</div>
