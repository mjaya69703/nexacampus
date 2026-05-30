<?php

use App\Models\Organization\UserDevelopmentRecord;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => UserDevelopmentRecord::count(),
            'verified' => UserDevelopmentRecord::where('is_verified', true)->count(),
            'pending' => UserDevelopmentRecord::where('is_verified', false)->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Sertifikasi & Pelatihan',
        ]);
    }
};
?>

<div>
    <div class="row mb-3">
        @foreach ([['label' => 'Total Sertifikasi', 'value' => $this->stats()['total'], 'color' => 'text-primary'], ['label' => 'Terverifikasi', 'value' => $this->stats()['verified'], 'color' => 'text-success'], ['label' => 'Menunggu Verifikasi', 'value' => $this->stats()['pending'], 'color' => 'text-warning']] as $card)
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted text-uppercase">{{ $card['label'] }}</small>
                        <h2 class="{{ $card['color'] }} mb-0">{{ $card['value'] }}</h2>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Sertifikasi & Pelatihan</h3>
                <small class="text-muted">Kelola riwayat sertifikasi, pelatihan, dan pengembangan dari pengguna sistem.</small>
            </div>
            <a href="{{ route('admin.organization.user-development-records.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Tambah Riwayat
            </a>
        </div>
        <div class="card-body">
            <livewire:organization.user-development-record-table />
        </div>
    </div>
</div>
