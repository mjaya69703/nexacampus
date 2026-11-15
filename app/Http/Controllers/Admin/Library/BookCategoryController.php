<?php

namespace App\Http\Controllers\Admin\Library;

use App\Http\Controllers\Controller;
use App\Models\Library\Book;
use App\Models\Library\BookCategory;
use App\Models\Library\BookCopy;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BookCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:name,code,is_active,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = BookCategory::query()->withCount('books');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%"));
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $categories = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Library/BookCategories/Index', [
            'shell' => ShellProps::make($request->user(), 'Perpustakaan', 'Kategori Buku'),
            'can' => [
                'create' => ActivePermission::check('book-category.create'),
                'update' => ActivePermission::check('book-category.update'),
                'delete' => ActivePermission::check('book-category.delete'),
            ],
            'stats' => [
                'total' => BookCategory::count(),
                'active' => BookCategory::where('is_active', true)->count(),
                'books' => Book::count(),
                'copies' => BookCopy::count(),
            ],
            'data' => [
                'rows' => collect($categories->items())->values()->map(fn (BookCategory $category, int $i) => [
                    'id' => $category->id,
                    'no' => ($categories->firstItem() ?? 0) + $i,
                    'name' => $category->name,
                    'code' => $category->code,
                    'description' => $category->description,
                    'booksCount' => (int) $category->books_count,
                    'isActive' => (bool) $category->is_active,
                    'editUrl' => route('admin.library.book-categories.edit', $category->id),
                    'deleteUrl' => route('admin.library.book-categories.destroy', $category->id),
                ])->all(),
                'currentPage' => $categories->currentPage(),
                'lastPage' => $categories->lastPage(),
                'perPage' => $categories->perPage(),
                'total' => $categories->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'urls' => [
                'index' => route('admin.library.book-categories.index'),
                'create' => route('admin.library.book-categories.create'),
                'bulkDestroy' => route('admin.library.book-categories.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Library/BookCategories/Form', [
            'shell' => ShellProps::make($request->user(), 'Perpustakaan', 'Tambah Kategori Buku'),
            'mode' => 'create',
            'category' => [
                'name' => '',
                'code' => '',
                'description' => '',
                'is_active' => true,
            ],
            'urls' => [
                'index' => route('admin.library.book-categories.index'),
                'submit' => route('admin.library.book-categories.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        BookCategory::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.library.book-categories.index')
            ->with('success', 'Kategori buku berhasil ditambahkan.');
    }

    public function edit(Request $request, int $id): Response
    {
        $category = BookCategory::findOrFail($id);

        return Inertia::render('Admin/Library/BookCategories/Form', [
            'shell' => ShellProps::make($request->user(), 'Perpustakaan', 'Edit Kategori Buku'),
            'mode' => 'edit',
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'description' => $category->description ?? '',
                'is_active' => (bool) $category->is_active,
            ],
            'urls' => [
                'index' => route('admin.library.book-categories.index'),
                'submit' => route('admin.library.book-categories.update', $category->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $category = BookCategory::findOrFail($id);

        $category->update([
            ...$this->validatePayload($request, $category),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.library.book-categories.index')
            ->with('success', 'Kategori buku berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $category = BookCategory::withCount('books')->findOrFail($id);

        if ($category->books_count > 0) {
            return back()->with('error', 'Kategori masih memiliki buku dan tidak dapat dihapus.');
        }

        $category->update(['deleted_by' => $request->user()->id]);
        $category->delete();

        return redirect()->route('admin.library.book-categories.index')
            ->with('success', 'Kategori buku berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:book_categories,id',
        ]);

        $categories = BookCategory::withCount('books')->whereIn('id', $validated['ids'])->get();
        $deleted = 0;
        $skipped = 0;

        foreach ($categories as $category) {
            if ($category->books_count > 0) {
                $skipped++;

                continue;
            }

            $category->update(['deleted_by' => $request->user()->id]);
            $category->delete();
            $deleted++;
        }

        $message = $deleted.' kategori buku berhasil dihapus.';

        if ($skipped > 0) {
            $message .= ' '.$skipped.' kategori dilewati karena masih memiliki buku.';
        }

        return back()->with($deleted > 0 ? 'success' : 'error', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?BookCategory $category = null): array
    {
        $request->merge([
            'code' => str((string) $request->input('code'))->upper()->replace(' ', '_')->toString(),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:40', Rule::unique('book_categories', 'code')->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $validated['is_active'] = (bool) $validated['is_active'];

        return $validated;
    }
}
