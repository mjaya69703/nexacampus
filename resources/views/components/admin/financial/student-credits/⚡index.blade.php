<?php

use App\Models\Financial\StudentCreditBalance;
use App\Models\Financial\StudentCreditTransaction;
use App\Support\Financial\StudentCreditService;
use App\Models\Academic\StudentProfile;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];
    public array $students = [];
    public string $studentSearch = '';
    public array $selectedStudentProfileIds = [];
    public array $form = [
        'transaction_type' => 'refund',
        'amount' => '',
        'notes' => '',
    ];

    public function mount(): void
    {
        $this->reloadStats();
    }

    public function updatedStudentSearch(): void
    {
        $this->reloadStats();
    }

    public function record(StudentCreditService $creditService): void
    {
        $validated = $this->validate([
            'selectedStudentProfileIds' => ['required', 'array', 'min:1'],
            'selectedStudentProfileIds.*' => ['exists:student_profiles,id'],
            'form.transaction_type' => ['required', Rule::in(['refund', 'manual_adjustment'])],
            'form.amount' => ['required', 'numeric', 'min:1'],
            'form.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $created = 0;
        $errors = [];
        try {
            foreach ($this->selectedStudentProfileIds as $studentProfileId) {
                $studentProfile = StudentProfile::findOrFail($studentProfileId);

                try {
                    if ($validated['form']['transaction_type'] === 'refund') {
                        $creditService->refund($studentProfile, (float) $validated['form']['amount'], auth()->id(), $validated['form']['notes'] ?: null);
                    } else {
                        $creditService->manualAdjustment($studentProfile, (float) $validated['form']['amount'], auth()->id(), $validated['form']['notes'] ?: null);
                    }

                    $created++;
                } catch (\Throwable $exception) {
                    $errors[] = ($studentProfile->nim ?: $studentProfile->id).': '.$exception->getMessage();
                }
            }

            $this->form = [
                'transaction_type' => 'refund',
                'amount' => '',
                'notes' => '',
            ];
            $this->selectedStudentProfileIds = [];
            $this->reloadStats();
            $this->dispatch('pg:eventRefresh-studentCreditTable');

            if ($created > 0) {
                session()->flash('success', $created.' credit transaction berhasil dicatat.');
            }

            if (! empty($errors)) {
                session()->flash('error', implode(' ', array_slice($errors, 0, 5)));
            }
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function reloadStats(): void
    {
        $this->stats = [
            'students' => StudentCreditBalance::where('balance', '>', 0)->count(),
            'balance' => (float) StudentCreditBalance::sum('balance'),
            'overpayment' => (float) StudentCreditTransaction::where('transaction_type', 'overpayment')->sum('amount'),
            'refund' => abs((float) StudentCreditTransaction::where('transaction_type', 'refund')->sum('amount')),
        ];

        $creditBalances = StudentCreditBalance::query()
            ->pluck('balance', 'student_profile_id');

        $this->students = StudentProfile::query()
            ->with('user')
            ->where('is_active', true)
            ->when($this->studentSearch !== '', function ($query) {
                $search = '%'.$this->studentSearch.'%';

                $query->where(function ($query) use ($search) {
                    $query->where('nim', 'like', $search)
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhere('email', 'like', $search));
                });
            })
            ->orderBy('nim')
            ->limit(25)
            ->get()
            ->map(fn (StudentProfile $student) => [
                'id' => $student->id,
                'label' => ($student->nim ?: '-').' - '.$student->user?->name.' (Credit: '.$this->money($creditBalances[$student->id] ?? 0).')',
            ])
            ->toArray();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Student Credits',
        ]);
    }

    public function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
};
?>

<div>
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Students With Credit</div><div class="h1 mb-0">{{ number_format($stats['students']) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Credit Balance</div><div class="h2 mb-0 text-success">{{ $this->money($stats['balance']) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Overpayment</div><div class="h2 mb-0 text-primary">{{ $this->money($stats['overpayment']) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Refund</div><div class="h2 mb-0 text-warning">{{ $this->money($stats['refund']) }}</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">Student Credits</h3>
                <small class="text-muted">Credit balance created from verified overpayment or finance correction.</small>
            </div>
        </div>
        <div class="card-body border-bottom">
            <x-alert />
            <form wire:submit.prevent="record" class="row g-2">
                <div class="col-md-4">
                    <label class="form-label">Students</label>
                    <input type="text" class="form-control mb-2" wire:model.live.debounce.300ms="studentSearch" placeholder="Cari NIM, nama, atau email...">
                    <div class="border rounded p-2" style="max-height: 160px; overflow-y: auto;">
                        @foreach($students as $student)
                            <label class="form-check mb-2" wire:key="credit-student-{{ $student['id'] }}">
                                <input class="form-check-input" type="checkbox" wire:model="selectedStudentProfileIds" value="{{ $student['id'] }}">
                                <span class="form-check-label">{{ $student['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    <small class="text-muted">{{ count($selectedStudentProfileIds) }} mahasiswa dipilih.</small>
                    @error('selectedStudentProfileIds') <span class="text-danger d-block">{{ $message }}</span> @enderror
                    @error('selectedStudentProfileIds.*') <span class="text-danger d-block">{{ $message }}</span> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select class="form-control" wire:model="form.transaction_type">
                        <option value="refund">Refund</option>
                        <option value="manual_adjustment">Manual Add Credit</option>
                    </select>
                    @error('form.transaction_type') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Amount</label>
                    <input type="number" min="1" step="1" class="form-control" wire:model="form.amount">
                    @error('form.amount') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Notes</label>
                    <input type="text" class="form-control" wire:model="form.notes" placeholder="Optional">
                    @error('form.notes') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="fas fa-save"></i>
                    </button>
                </div>
            </form>
        </div>
        <div class="card-body">
            <livewire:financial.student-credit-table />
        </div>
    </div>
</div>
