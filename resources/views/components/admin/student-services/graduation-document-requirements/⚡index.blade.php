<?php

use App\Models\StudentService\GraduationDocumentRequirement;
use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Dokumen Yudisium',
        ]);
    }
};
?>

<div>
    <x-alert />

    <div class="row mb-3">
        @foreach ([['label' => 'Requirements', 'value' => GraduationDocumentRequirement::count(), 'color' => 'text-primary'], ['label' => 'Active', 'value' => GraduationDocumentRequirement::where('is_active', true)->count(), 'color' => 'text-success'], ['label' => 'Required', 'value' => GraduationDocumentRequirement::where('is_required', true)->count(), 'color' => 'text-danger']] as $card)
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted text-uppercase">{{ $card['label'] }}</small>
                        <div class="h3 mb-0 {{ $card['color'] }}">{{ $card['value'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Graduation Document Requirements</h3>
                <small class="text-muted">Configure dokumen wajib/opsional untuk pengajuan yudisium.</small>
            </div>
            <a href="{{ route('admin.student-services.graduation-document-requirements.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Buat Dokumen
            </a>
        </div>
        <div class="card-body">
            <livewire:student-service.graduation-document-requirement-table />
        </div>
    </div>
</div>
