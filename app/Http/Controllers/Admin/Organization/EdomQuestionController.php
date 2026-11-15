<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\EdomQuestion;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD bank pertanyaan EDOM — paritas halaman Livewire lama.
 */
class EdomQuestionController extends Controller
{
    private const CATEGORIES = [
        'teaching' => 'Pengajaran',
        'material' => 'Materi Perkuliahan',
        'evaluation' => 'Evaluasi & Tugas',
        'general' => 'Umum',
    ];

    private const ANSWER_TYPES = [
        'scale' => 'Skala 1-5',
        'text' => 'Komentar Teks',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|in:teaching,material,evaluation,general',
            'answer_type' => 'nullable|in:scale,text',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:category,question_text,answer_type,sort_order,is_active,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = EdomQuestion::query()->withCount('answers');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('question_text', 'like', "%{$q}%")
                ->orWhere('category', 'like', "%{$q}%"));
        }

        if (filled($validated['category'] ?? null)) {
            $query->where('category', $validated['category']);
        }

        if (filled($validated['answer_type'] ?? null)) {
            $query->where('answer_type', $validated['answer_type']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'sort_order';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $questions = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Organization/EdomQuestions/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Pertanyaan EDOM'),
            'can' => [
                'create' => ActivePermission::check('edom-question.create'),
                'update' => ActivePermission::check('edom-question.update'),
                'delete' => ActivePermission::check('edom-question.delete'),
            ],
            'stats' => [
                'total' => EdomQuestion::count(),
                'active' => EdomQuestion::where('is_active', true)->count(),
                'scale' => EdomQuestion::where('answer_type', 'scale')->count(),
                'text' => EdomQuestion::where('answer_type', 'text')->count(),
            ],
            'data' => [
                'rows' => collect($questions->items())->values()->map(fn (EdomQuestion $question, int $i) => [
                    'id' => $question->id,
                    'no' => ($questions->firstItem() ?? 0) + $i,
                    'category' => $question->category,
                    'categoryLabel' => self::CATEGORIES[$question->category] ?? $question->category,
                    'questionText' => $question->question_text,
                    'answerType' => $question->answer_type,
                    'answerTypeLabel' => self::ANSWER_TYPES[$question->answer_type] ?? $question->answer_type,
                    'sortOrder' => (int) $question->sort_order,
                    'isRequired' => (bool) $question->is_required,
                    'isActive' => (bool) $question->is_active,
                    'answersCount' => (int) $question->answers_count,
                    'editUrl' => route('admin.organization.edom-questions.edit', $question->id),
                    'toggleUrl' => route('admin.organization.edom-questions.toggle', $question->id),
                    'deleteUrl' => route('admin.organization.edom-questions.destroy', $question->id),
                ])->all(),
                'currentPage' => $questions->currentPage(),
                'lastPage' => $questions->lastPage(),
                'perPage' => $questions->perPage(),
                'total' => $questions->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'category' => $validated['category'] ?? '',
                'answer_type' => $validated['answer_type'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'categories' => $this->optionList(self::CATEGORIES),
            'answerTypes' => $this->optionList(self::ANSWER_TYPES),
            'urls' => [
                'index' => route('admin.organization.edom-questions.index'),
                'create' => route('admin.organization.edom-questions.create'),
                'bulkDestroy' => route('admin.organization.edom-questions.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Organization/EdomQuestions/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Tambah Pertanyaan EDOM'),
            'mode' => 'create',
            'question' => [
                'category' => 'teaching',
                'question_text' => '',
                'answer_type' => 'scale',
                'sort_order' => 0,
                'is_required' => true,
                'is_active' => true,
            ],
            'categories' => $this->optionList(self::CATEGORIES),
            'answerTypes' => $this->optionList(self::ANSWER_TYPES),
            'urls' => [
                'index' => route('admin.organization.edom-questions.index'),
                'submit' => route('admin.organization.edom-questions.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        EdomQuestion::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.organization.edom-questions.index')
            ->with('success', 'Pertanyaan EDOM berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $question = EdomQuestion::findOrFail($id);

        return Inertia::render('Admin/Organization/EdomQuestions/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Edit Pertanyaan EDOM'),
            'mode' => 'edit',
            'question' => [
                'id' => $question->id,
                'category' => $question->category,
                'question_text' => $question->question_text,
                'answer_type' => $question->answer_type,
                'sort_order' => (int) $question->sort_order,
                'is_required' => (bool) $question->is_required,
                'is_active' => (bool) $question->is_active,
            ],
            'categories' => $this->optionList(self::CATEGORIES),
            'answerTypes' => $this->optionList(self::ANSWER_TYPES),
            'urls' => [
                'index' => route('admin.organization.edom-questions.index'),
                'submit' => route('admin.organization.edom-questions.update', $question->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $question = EdomQuestion::findOrFail($id);
        $validated = $this->validatePayload($request);

        $question->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.organization.edom-questions.index')
            ->with('success', 'Pertanyaan EDOM berhasil diperbarui.');
    }

    public function toggle(Request $request, int $id)
    {
        $question = EdomQuestion::findOrFail($id);
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $question->update([
            'is_active' => (bool) $validated['is_active'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status pertanyaan berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $question = EdomQuestion::findOrFail($id);

        if ($question->answers()->exists()) {
            return back()->with('error', 'Pertanyaan ini tidak dapat dihapus karena sudah memiliki jawaban mahasiswa.');
        }

        $question->update(['deleted_by' => $request->user()->id]);
        $question->delete();

        return redirect()->route('admin.organization.edom-questions.index')
            ->with('success', 'Pertanyaan berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:edom_questions,id',
        ]);

        $questions = EdomQuestion::whereIn('id', $validated['ids'])->get();

        foreach ($questions as $question) {
            $question->update(['deleted_by' => $request->user()->id]);
            $question->delete();
        }

        return back()->with('success', $questions->count().' pertanyaan EDOM berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'question_text' => ['required', 'string', 'max:500'],
            'answer_type' => ['required', Rule::in(array_keys(self::ANSWER_TYPES))],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_required' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_required'] = (bool) ($validated['is_required'] ?? false);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }

    /**
     * @param  array<string, string>  $options
     * @return array<int, array{id: string, label: string}>
     */
    private function optionList(array $options): array
    {
        return collect($options)
            ->map(fn (string $label, string $id) => ['id' => $id, 'label' => $label])
            ->values()
            ->all();
    }
}
