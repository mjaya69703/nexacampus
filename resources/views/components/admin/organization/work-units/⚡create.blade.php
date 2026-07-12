<?php

use App\Models\Organization\WorkUnit;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = [];
    public array $members = [];
    public array $selectedUserIds = [];
    public string $memberSearch = '';
    public string $bulkPosition = 'member';
    public bool $bulkIsActive = true;

    public function mount(): void
    {
        $this->form = [
            'name' => '',
            'code' => '',
            'description' => '',
            'is_active' => true,
        ];
    }

    public function addSelectedMembers(): void
    {
        $selectedIds = collect($this->selectedUserIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            session()->flash('error', 'Pilih minimal satu user terlebih dahulu.');

            return;
        }

        $existingIds = collect($this->members)
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($selectedIds as $userId) {
            if (in_array($userId, $existingIds, true)) {
                continue;
            }

            $this->members[] = [
                'user_id' => (string) $userId,
                'position' => $this->bulkPosition,
                'is_active' => $this->bulkIsActive,
            ];
        }

        $this->selectedUserIds = [];
        $this->memberSearch = '';
    }

    public function removeMember(int $index): void
    {
        unset($this->members[$index]);
        $this->members = array_values($this->members);
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.work-units.index');
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        $unit = WorkUnit::create([
            'name' => $validated['form']['name'],
            'code' => strtoupper($validated['form']['code']),
            'description' => $validated['form']['description'] ?: null,
            'is_active' => (bool) $validated['form']['is_active'],
            'created_by' => auth()->id(),
        ]);

        $this->syncMembers($unit, $validated['members'] ?? []);

        session()->flash('success', 'Unit kerja berhasil dibuat.');
        $this->redirectRoute('admin.organization.work-units.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Buat Unit Kerja',
        ]);
    }

    public function getSearchableUsersProperty()
    {
        $existingIds = collect($this->members)
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        return User::query()
            ->where('is_active', true)
            ->when($existingIds !== [], fn ($query) => $query->whereNotIn('id', $existingIds))
            ->when(filled($this->memberSearch), function ($query) {
                $search = '%'.$this->memberSearch.'%';

                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('username', 'like', $search)
                        ->orWhere('code', 'like', $search)
                        ->orWhere('identity_number', 'like', $search);
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(12)
            ->get(['id', 'first_name', 'last_name', 'email', 'username', 'code']);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:30', Rule::unique('work_units', 'code')],
            'form.description' => ['nullable', 'string'],
            'form.is_active' => ['boolean'],
            'members' => ['array'],
            'members.*.user_id' => ['required', 'integer', 'exists:users,id', 'distinct'],
            'members.*.position' => ['required', 'string', 'in:member,coordinator,head'],
            'members.*.is_active' => ['boolean'],
        ];
    }

    private function syncMembers(WorkUnit $unit, array $members): void
    {
        $payload = collect($members)
            ->filter(fn (array $member) => filled($member['user_id'] ?? null))
            ->mapWithKeys(fn (array $member) => [
                (int) $member['user_id'] => [
                    'position' => $member['position'] ?? 'member',
                    'is_active' => (bool) ($member['is_active'] ?? true),
                ],
            ])
            ->all();

        $unit->members()->sync($payload);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Tambah Unit Kerja / Biro Baru"
        description="Buat struktur unit kerja atau lembaga operasional kampus untuk penugasan tiket, routing approval, dan manajemen pembatasan lingkup data SDM."
        icon="plus-circle"
    >
        <a href="{{ route('admin.organization.work-units.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.organization.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-building fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Pendaftaran Unit Kerja</h4>
                            <div class="text-muted small">Isi nama unit, kode identitas, status keaktifan, dan tambahkan anggota pegawai.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.organization.work-units._form')
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
                            <h5 class="fw-bold mb-1">Panduan Singkat</h5>
                            <div class="text-muted small">Susun struktur organisasi dengan kode yang konsisten.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Kode unit kerja bersifat unik dan digunakan dalam prefix tiket dan routing persetujuan.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pegawai yang ditugaskan sebagai Kepala Unit (Head) akan menerima notifikasi persetujuan (approval) tingkat pertama.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Menonaktifkan unit akan menyembunyikan unit ini dari pilihan penugasan baru.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Awal</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Aktif</div>
                    <p class="text-muted small mb-0">Unit kerja baru langsung siap digunakan dalam penugasan dan alur kerja setelah disimpan.</p>
                </div>
            </div>
        </div>
    </div>
</div>
