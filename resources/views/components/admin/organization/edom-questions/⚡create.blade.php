<?php

use App\Models\Organization\EdomQuestion;
use Livewire\Component;

new class extends Component
{
    public array $form = ['category' => 'teaching', 'question_text' => '', 'answer_type' => 'scale', 'sort_order' => 0, 'is_required' => true, 'is_active' => true];

    public function save(): void
    {
        $data = $this->validate([
            'form.category' => ['required', 'string', 'max:80'],
            'form.question_text' => ['required', 'string', 'max:500'],
            'form.answer_type' => ['required', 'in:scale,text'],
            'form.sort_order' => ['required', 'integer', 'min:0'],
            'form.is_required' => ['boolean'],
            'form.is_active' => ['boolean'],
        ])['form'];

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        EdomQuestion::create($data);
        session()->flash('success', 'Pertanyaan EDOM berhasil dibuat.');
        $this->redirectRoute('admin.organization.edom-questions.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Tambah Pertanyaan EDOM']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Tambah Pertanyaan EDOM"
        description="Buat item pertanyaan baru untuk kuesioner evaluasi pengajaran dosen oleh mahasiswa."
        icon="plus-circle"
    >
        <a href="{{ route('admin.organization.edom-questions.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.organization.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <form wire:submit.prevent="save" class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-circle-question fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Pertanyaan Evaluasi</h4>
                            <div class="text-muted small">Lengkapi teks pertanyaan, kategori penilaian, dan format jawaban kuesioner.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.organization.edom-questions._form')
                </div>
                <div class="card-footer bg-light bg-opacity-50 border-top p-4 d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.organization.edom-questions.index') }}" class="btn btn-light rounded-pill px-4 fw-medium d-inline-flex align-items-center gap-2">
                        <i class="fa fa-times"></i> <span>Batal</span>
                    </a>
                    <button type="submit" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium d-inline-flex align-items-center gap-2">
                        <i class="fa fa-save"></i> <span>Simpan Pertanyaan</span>
                    </button>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-circle-info"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Pedoman Kuesioner</h5>
                            <div class="text-muted small">Panduan pembuatan instrumen EDOM.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Kategori Pertanyaan</strong>: Kelompokkan pertanyaan agar analisis hasil evaluasi dapat dipetakan secara terstruktur (misal: aspek pengajaran vs aspek materi).</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Tipe Skala (1-5)</strong>: Digunakan untuk mengalkulasi nilai kuantitatif performa dosen pada sistem EDOM.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Nomor Urut</strong>: Menentukan posisi tampil pertanyaan pada halaman pengisian kuesioner oleh mahasiswa.</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
