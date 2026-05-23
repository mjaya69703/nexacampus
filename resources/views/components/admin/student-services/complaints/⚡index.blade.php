<?php

use App\Models\StudentService\StudentComplaint;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => StudentComplaint::count(),
            'open' => StudentComplaint::whereNotIn('status', ['closed', 'rejected'])->count(),
            'waiting' => StudentComplaint::where('status', 'waiting_student')->count(),
            'overdue' => StudentComplaint::whereNotIn('status', ['resolved', 'closed', 'rejected'])->whereNotNull('due_at')->where('due_at', '<', now())->count(),
        ];
    }

    public function refreshComplaintTable(): void
    {
        $this->dispatch('pg:eventRefresh-studentComplaintTable');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Layanan Mahasiswa', 'pages' => 'Pengaduan']);
    }
};
?>

<div wire:poll.5s="refreshComplaintTable">
    <x-alert />
    <div class="row mb-3">
        @foreach ([['label' => 'Total', 'value' => $this->stats()['total'], 'icon' => 'fa-ticket', 'color' => 'primary'], ['label' => 'Open', 'value' => $this->stats()['open'], 'icon' => 'fa-inbox', 'color' => 'info'], ['label' => 'Menunggu Mahasiswa', 'value' => $this->stats()['waiting'], 'icon' => 'fa-user-clock', 'color' => 'warning'], ['label' => 'Lewat SLA', 'value' => $this->stats()['overdue'], 'icon' => 'fa-triangle-exclamation', 'color' => 'danger']] as $card)
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="avatar bg-{{ $card['color'] }}-lt text-{{ $card['color'] }}"><i class="fas {{ $card['icon'] }}"></i></span>
                        <div>
                            <small class="text-muted text-uppercase">{{ $card['label'] }}</small>
                            <h2 class="mb-0">{{ $card['value'] }}</h2>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <h3 class="card-title mb-0">Pengaduan Mahasiswa</h3>
                <small class="text-muted">Kelola tiket pengaduan, assignment unit, SLA, dan balasan mahasiswa.</small>
            </div>
            <span class="badge bg-blue-lt text-blue align-self-start"><i class="fas fa-rotate me-1"></i>Auto refresh 5 detik</span>
        </div>
        <div class="card-body">
            <livewire:student-service.student-complaint-table />
        </div>
    </div>
</div>
