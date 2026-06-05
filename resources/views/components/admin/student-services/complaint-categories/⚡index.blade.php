<?php

use App\Models\StudentService\StudentComplaintCategory;
use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Kategori Pengaduan',
        ]);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Kategori Pengaduan</h3>
                <small class="text-muted">Atur kategori, SLA, dan unit kerja default untuk routing tiket.</small>
            </div>
            <a href="{{ route('admin.student-services.complaint-categories.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Buat Kategori
            </a>
        </div>
        <div class="card-body">
            <livewire:student-service.student-complaint-category-table />
        </div>
    </div>
</div>
