<?php

use App\Models\Organization\EdomQuestion;
use Livewire\Component;

new class extends Component
{
    public EdomQuestion $question;
    public array $form = [];

    public function mount($id): void
    {
        $this->question = EdomQuestion::findOrFail($id);
        $this->form = $this->question->only(['category', 'question_text', 'answer_type', 'sort_order', 'is_required', 'is_active']);
    }

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

        $data['updated_by'] = auth()->id();
        $this->question->update($data);
        session()->flash('success', 'Pertanyaan EDOM berhasil diperbarui.');
        $this->redirectRoute('admin.organization.edom-questions.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Edit Pertanyaan EDOM']);
    }
};
?>
<div><x-alert /><div class="card"><div class="card-header"><h3 class="card-title mb-0">Edit Pertanyaan EDOM</h3></div><div class="card-body">@include('components.admin.organization.edom-questions._form')</div></div></div>
