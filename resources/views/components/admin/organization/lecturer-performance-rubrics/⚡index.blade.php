<?php

use App\Models\Organization\LecturerPerformanceRubric;
use App\Support\ActivePermission;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        $active = LecturerPerformanceRubric::where('is_active', true)->first();

        return [
            'total' => LecturerPerformanceRubric::count(),
            'active_code' => $active ? $active->code : '-',
            'active_name' => $active ? $active->name : 'Belum Atur',
            'target_sks' => $active ? $active->target_workload_sks : 12,
        ];
    }

    public function confirmToggle(int $id): void
    {
        $rubric = LecturerPerformanceRubric::find($id);
        if (! $rubric) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Aktifkan Rubrik Ini?",
                text: "Rubrik \''.$rubric->name.'\' akan dijadikan standar penilaian performa dosen (rubrik aktif saat ini akan dinonaktifkan).",
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Ya, Aktifkan!",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("toggleItem", {id: '.$id.'})
                }
            });
        ');
    }

    #[On('toggleItem')]
    public function toggleItem($id = null): void
    {
        if (! ActivePermission::check('lecturer-performance-rubric.update')) {
            session()->flash('error', 'Anda tidak memiliki izin mengubah rubrik.');
            return;
        }

        $rubric = LecturerPerformanceRubric::findOrFail($id);
        LecturerPerformanceRubric::query()->whereKeyNot($rubric->id)->update(['is_active' => false]);
        $rubric->update(['is_active' => true, 'updated_by' => auth()->id()]);
        session()->flash('success', 'Rubrik aktif berhasil diperbarui.');
    }

    public function confirmDelete(int $id): void
    {
        $rubric = LecturerPerformanceRubric::find($id);
        if (! $rubric) {
            return;
        }

        if ($rubric->is_active) {
            $this->js('Swal.fire("Gagal", "Tidak dapat menghapus rubrik yang sedang aktif digunakan.", "error");');
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus Rubrik?",
                text: "Rubrik \''.$rubric->name.'\' akan dihapus permanen.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya, Hapus!",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("deleteItem", {id: '.$id.'})
                }
            });
        ');
    }

    #[On('deleteItem')]
    public function deleteItem($id = null): void
    {
        if (! ActivePermission::check('lecturer-performance-rubric.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus rubrik.');
            return;
        }

        $rubric = LecturerPerformanceRubric::findOrFail($id);
        if ($rubric->is_active) {
            session()->flash('error', 'Tidak dapat menghapus rubrik yang sedang aktif.');
            return;
        }

        $rubric->delete();
        session()->flash('success', 'Rubrik berhasil dihapus.');
    }

    public function render()
    {
        return $this->view([
            'rubrics' => LecturerPerformanceRubric::query()->latest('is_active')->latest('updated_at')->get(),
        ])->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Rubrik Performa']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Daftar Rubrik Performa Dosen"
        description="Konfigurasikan persentase bobot penilaian evaluasi dosen oleh mahasiswa (EDOM), kepatuhan jadwal mengajar, kedisiplinan absensi, dan realisasi BKD untuk menghitung skor akhir kinerja tri dharma."
        icon="balance-scale"
    >
        @activecan('lecturer-performance-rubric.create')
            <a href="{{ route('admin.organization.lecturer-performance-rubrics.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Rubrik Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-scale-balanced fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Rubrik</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Rubrik Aktif</div>
                        <div class="fw-bold">{{ $this->stats()['active_code'] }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-bullseye fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Target BKD Aktif</div>
                        <div class="fw-bold">{{ number_format((float) $this->stats()['target_sks'], 1) }} SKS</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.organization.header>

    <div class="row g-4">
        @forelse ($rubrics as $rubric)
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden {{ $rubric->is_active ? 'border-primary border-2' : '' }}">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span class="badge bg-light text-dark border px-3 py-1 fs-6 fw-bold">{{ $rubric->code }}</span>
                                    @if ($rubric->is_active)
                                        <span class="badge bg-success text-white px-3 py-1 fs-6 rounded-pill"><i class="fa fa-circle-check me-1"></i> Rubrik Aktif Digunakan</span>
                                    @else
                                        <span class="badge bg-secondary text-white px-3 py-1 fs-6 rounded-pill">Nonaktif</span>
                                    @endif
                                </div>
                                <h4 class="fw-bold text-dark mb-1 fs-5">{{ $rubric->name }}</h4>
                                <span class="small text-muted d-block">Minimal respon valid EDOM: <strong>{{ $rubric->minimum_responses }} mahasiswa</strong> &bull; Target BKD: <strong>{{ $rubric->target_workload_sks }} SKS</strong></span>
                            </div>
                            <div class="text-end bg-light p-2 rounded-3 border">
                                <h3 class="fw-bold text-primary mb-0 fs-4">{{ number_format($rubric->totalWeight(), 1) }}%</h3>
                                <span class="small text-muted d-block">Total Bobot</span>
                            </div>
                        </div>

                        <div class="row g-3 mt-2 text-center">
                            @foreach ([
                                ['label' => 'Bobot EDOM', 'value' => $rubric->edom_weight, 'color' => 'primary'],
                                ['label' => 'Mengajar', 'value' => $rubric->teaching_weight, 'color' => 'success'],
                                ['label' => 'Absensi', 'value' => $rubric->attendance_weight, 'color' => 'info'],
                                ['label' => 'BKD', 'value' => $rubric->workload_weight, 'color' => 'warning']
                            ] as $item)
                                <div class="col-6 col-md-3">
                                    <div class="p-3 rounded-3 bg-{{ $item['color'] }} bg-opacity-10 border border-{{ $item['color'] }} border-opacity-25 h-100 d-flex flex-column justify-content-center">
                                        <span class="small text-muted fw-medium d-block mb-1">{{ $item['label'] }}</span>
                                        <h5 class="fw-bold text-{{ $item['color'] }} mb-0 fs-5">{{ $item['value'] }}%</h5>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="card-footer bg-light bg-opacity-50 border-0 px-4 py-3 d-flex justify-content-end align-items-center gap-2">
                        @activecan('lecturer-performance-rubric.update')
                            @unless ($rubric->is_active)
                                <button type="button" wire:click="confirmToggle({{ $rubric->id }})" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-medium d-inline-flex align-items-center gap-1">
                                    <i class="fa fa-check"></i> <span>Aktifkan Rubrik Ini</span>
                                </button>
                            @endunless
                            <a href="{{ route('admin.organization.lecturer-performance-rubrics.edit', $rubric->id) }}" class="btn btn-sm btn-primary rounded-pill shadow-sm px-4 fw-medium d-inline-flex align-items-center gap-1">
                                <i class="fa fa-edit"></i> <span>Edit Rubrik</span>
                            </a>
                        @endactivecan
                        @activecan('lecturer-performance-rubric.delete')
                            @unless ($rubric->is_active)
                                <button type="button" wire:click="confirmDelete({{ $rubric->id }})" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-medium d-inline-flex align-items-center gap-1">
                                    <i class="fa fa-trash"></i> <span>Hapus</span>
                                </button>
                            @endunless
                        @endactivecan
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                    <i class="fa fa-folder-open fs-1 text-muted mb-3"></i>
                    <h5 class="fw-bold text-dark">Belum Ada Rubrik Penilaian</h5>
                    <p class="text-muted small mb-3">Silakan buat rubrik performa baru untuk menentukan persentase bobot penilaian dosen.</p>
                    @activecan('lecturer-performance-rubric.create')
                        <div>
                            <a href="{{ route('admin.organization.lecturer-performance-rubrics.create') }}" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium d-inline-flex align-items-center gap-2">
                                <i class="fa fa-plus"></i> <span>Buat Rubrik Sekarang</span>
                            </a>
                        </div>
                    @endactivecan
                </div>
            </div>
        @endforelse
    </div>
</div>
