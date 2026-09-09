<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use App\Models\Settings\Menu;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Menu sidebar — memakai kit CRUD shared (pola menu access).
 * Paritas form Blade lama + parent ber-indentasi agar hierarki terbaca.
 */
class MenuController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'type' => 'nullable|in:link,group',
            'sort' => 'nullable|in:id,title,type,sort_order,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = Menu::query()->with('parent:id,title');

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('title', 'like', "%{$q}%")
                ->orWhere('route_name', 'like', "%{$q}%")
                ->orWhere('url', 'like', "%{$q}%")
                ->orWhere('permission_name', 'like', "%{$q}%"));
        }

        if (filled($validated['type'] ?? null)) {
            $query->where('type', $validated['type']);
        }

        $sort = $validated['sort'] ?? 'sort_order';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy($sort, $direction)->orderBy('id', $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $menus = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/System/Menu/Index', [
            'shell' => ShellProps::make($request->user(), 'Sistem', 'Daftar Menu'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('menu.create'),
                'update' => ActivePermission::check('menu.update'),
                'delete' => ActivePermission::check('menu.delete'),
                'restore' => ActivePermission::any(['menu.update', 'menu.delete']),
            ],
            'stats' => [
                'total' => Menu::count(),
                'groups' => Menu::where('type', 'group')->count(),
                'links' => Menu::where('type', 'link')->count(),
                'inactive' => Menu::where('is_active', false)->count(),
                'trashed' => Menu::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($menus->items())->values()->map(fn ($m, $i) => [
                    'id' => $m->id,
                    'no' => ($menus->firstItem() ?? 0) + $i,
                    'title' => $m->title,
                    'parent' => $m->parent?->title,
                    'type' => $m->type,
                    'route' => $m->route_name,
                    'url' => $m->url,
                    'icon' => $m->icon,
                    'permission' => $m->permission_name,
                    'sort' => (int) $m->sort_order,
                    'isActive' => (bool) $m->is_active,
                    'createdAt' => $m->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.system.menus.edit', $m->id),
                    'deleteUrl' => route('admin.system.menus.destroy', $m->id),
                    'restoreUrl' => route('admin.system.menus.restore', $m->id),
                    'forceUrl' => route('admin.system.menus.force-destroy', $m->id),
                ])->all(),
                'currentPage' => $menus->currentPage(),
                'lastPage' => $menus->lastPage(),
                'perPage' => $menus->perPage(),
                'total' => $menus->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'type' => $validated['type'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'urls' => [
                'index' => route('admin.system.menus.index'),
                'create' => route('admin.system.menus.create'),
                'export' => route('admin.system.menus.export'),
                'bulkDestroy' => route('admin.system.menus.bulk-destroy'),
                'bulkRestore' => route('admin.system.menus.bulk-restore'),
                'bulkForceDestroy' => route('admin.system.menus.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/System/Menu/Form', [
            'shell' => ShellProps::make($request->user(), 'Sistem', 'Tambah Menu'),
            'mode' => 'create',
            'menu' => [
                'type' => 'link', 'parent_id' => '', 'title' => '', 'route_name' => '',
                'url' => '', 'icon' => '', 'permission_name' => '', 'sort_order' => 0,
                'is_active' => true,
            ],
            'parents' => $this->parentOptions(),
            'urls' => [
                'index' => route('admin.system.menus.index'),
                'submit' => route('admin.system.menus.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:link,group',
            'parent_id' => 'nullable|exists:menus,id',
            'title' => 'required|string|max:255',
            'route_name' => 'nullable|string|max:255',
            'url' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'permission_name' => 'nullable|string|max:255',
            'sort_order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $menu = Menu::create([
            ...$validated,
            'parent_id' => $validated['parent_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('admin.system.menus.index')
            ->with('success', 'Menu "'.$menu->title.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, Menu $menu): Response
    {
        return Inertia::render('Admin/System/Menu/Form', [
            'shell' => ShellProps::make($request->user(), 'Sistem', 'Edit Menu'),
            'mode' => 'edit',
            'menu' => [
                'id' => $menu->id,
                'type' => $menu->type,
                'parent_id' => $menu->parent_id ?? '',
                'title' => $menu->title,
                'route_name' => $menu->route_name ?? '',
                'url' => $menu->url ?? '',
                'icon' => $menu->icon ?? '',
                'permission_name' => $menu->permission_name ?? '',
                'sort_order' => (int) $menu->sort_order,
                'is_active' => (bool) $menu->is_active,
            ],
            'parents' => $this->parentOptions($menu->id),
            'urls' => [
                'index' => route('admin.system.menus.index'),
                'submit' => route('admin.system.menus.update', $menu),
            ],
        ]);
    }

    public function update(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'type' => 'required|in:link,group',
            'parent_id' => 'nullable|exists:menus,id',
            'title' => 'required|string|max:255',
            'route_name' => 'nullable|string|max:255',
            'url' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'permission_name' => 'nullable|string|max:255',
            'sort_order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        if (! empty($validated['parent_id']) && (int) $validated['parent_id'] === $menu->id) {
            return back()->with('error', 'Menu tidak bisa menjadi parent untuk dirinya sendiri.')->withInput();
        }

        $menu->update([
            ...$validated,
            'parent_id' => $validated['parent_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('admin.system.menus.index')
            ->with('success', 'Menu "'.$menu->title.'" berhasil diperbarui.');
    }

    public function destroy(Menu $menu)
    {
        $title = $menu->title;
        $menu->delete();

        return redirect()->route('admin.system.menus.index')
            ->with('success', 'Menu "'.$title.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:menus,id',
        ]);

        $count = Menu::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count.' menu berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $menu = Menu::withTrashed()->findOrFail($id);
        $menu->restore();

        return back()->with('success', 'Menu "'.$menu->title.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $menu = Menu::withTrashed()->findOrFail($id);
        $title = $menu->title;
        $menu->forceDelete();

        return back()->with('success', 'Menu "'.$title.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:menus,id',
        ]);

        $count = 0;

        foreach (Menu::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $menu) {
            $menu->restore();
            $count++;
        }

        return back()->with('success', $count.' menu berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:menus,id',
        ]);

        $count = 0;

        foreach (Menu::withTrashed()->whereIn('id', $validated['ids'])->get() as $menu) {
            $menu->forceDelete();
            $count++;
        }

        return back()->with('success', $count.' menu dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = Menu::query()->with('parent:id,title');

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(fn ($sub) => $sub
                ->where('title', 'like', "%{$q}%")
                ->orWhere('route_name', 'like', "%{$q}%")
                ->orWhere('url', 'like', "%{$q}%")
                ->orWhere('permission_name', 'like', "%{$q}%"));
        }

        if (filled($request->query('type'))) {
            $query->where('type', $request->query('type'));
        }

        $rows = $query->orderBy('sort_order')->orderBy('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Judul', 'Parent', 'Tipe', 'Route', 'URL', 'Ikon', 'Permission', 'Urutan', 'Aktif', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $menu) {
            $sheet->fromArray([
                $menu->title,
                $menu->parent?->title,
                $menu->type,
                $menu->route_name,
                $menu->url,
                $menu->icon,
                $menu->permission_name,
                $menu->sort_order,
                $menu->is_active ? 'Ya' : 'Tidak',
                $menu->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'menus-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    /**
     * Opsi parent ber-indentasi mengikuti hierarki (tanpa $excludeId).
     *
     * @return array<int, array{id: int|string, name: string}>
     */
    private function parentOptions(?int $excludeId = null): array
    {
        $menus = Menu::query()->orderBy('sort_order')->orderBy('title')->get(['id', 'parent_id', 'title']);
        $byParent = $menus->groupBy(fn ($m) => $m->parent_id ?? 0);
        $options = [['id' => '', 'name' => 'Tanpa Parent']];

        $walk = function ($parentId, int $depth) use (&$walk, &$options, $byParent, $excludeId) {
            foreach ($byParent->get($parentId, collect()) as $menu) {
                if ($excludeId !== null && $menu->id === $excludeId) {
                    continue;
                }

                $options[] = ['id' => $menu->id, 'name' => str_repeat('— ', $depth).$menu->title];
                $walk($menu->id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $options;
    }
}
