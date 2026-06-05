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
<div><x-alert /><div class="card"><div class="card-header"><h3 class="card-title mb-0">Tambah Pertanyaan EDOM</h3></div><div class="card-body">@include('components.admin.organization.edom-questions._form')</div></div></div>
