<?php

namespace App\Http\Controllers\Admin\Campus;

use App\Http\Controllers\Controller;
use App\Models\Campus\CampusAsset;
use App\Models\Campus\Room;
use App\Support\ActivePermission;
use App\Support\Campus\AssetCodeGenerator;
use App\Support\Campus\AssetProductService;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Aset Kampus — memakai kit CRUD shared (pola menu academic).
 *
 * Paritas Blade lama + koreksi: hapus ditolak bila masih punya tiket
 * maintenance, dropdown ruangan hanya yang aktif.
 */
class CampusAssetController extends Controller
{
    public const CONDITIONS = [
        'good' => 'Baik (Normal)',
        'minor_damage' => 'Rusak Ringan',
        'major_damage' => 'Rusak Berat',
        'in_repair' => 'Sedang Dalam Perbaikan',
    ];

    public const STATUSES = [
        'active' => 'Aktif Digunakan',
        'maintenance' => 'Maintenance',
        'disposed' => 'Penghapusan / Afkir',
    ];

    public const FUNDINGS = [
        'anggaran_kampus' => 'Anggaran Kampus',
        'hibah' => 'Hibah',
        'bantuan_pemerintah' => 'Bantuan Pemerintah',
        'sumbangan' => 'Sumbangan',
        'lainnya' => 'Lainnya',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|integer|exists:asset_categories,id',
            'condition' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
            'funding' => 'nullable|string|max:50',
            'sort' => 'nullable|in:name,qty,latest',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';

        if ($isTrash) {
            return $this->trashIndex($request, $validated);
        }

        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';
        $perPage = (int) ($validated['perPage'] ?? 10);

        $groups = AssetProductService::paginatedGroups(
            [
                'q' => $validated['q'] ?? null,
                'category' => $validated['category'] ?? null,
                'condition' => $validated['condition'] ?? null,
                'status' => $validated['status'] ?? null,
                'funding' => $validated['funding'] ?? null,
            ],
            $sort,
            $direction,
            $perPage
        );

        return Inertia::render('Admin/Campus/Asset/Index', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Daftar Aset Kampus'),
            'can' => $this->permissions(),
            'stats' => $this->stats(),
            'data' => [
                'rows' => collect($groups->items())->values()->map(fn ($g, $i) => [
                    'id' => $i + 1,
                    'no' => ($groups->firstItem() ?? 0) + $i,
                    'name' => $g['name'],
                    'brand' => $g['brand'],
                    'model' => $g['model'],
                    'categoryId' => $g['categoryId'],
                    'categoryLabel' => $g['categoryLabel'],
                    'qty' => $g['qty'],
                    'good' => $g['good'],
                    'damaged' => $g['damaged'],
                    'totalValue' => $g['totalValue'] > 0 ? 'Rp '.number_format($g['totalValue'], 0, ',', '.') : null,
                    'rooms' => $g['rooms'],
                    'showUrl' => route('admin.campus.products.show', $g['key']),
                ])->all(),
                'currentPage' => $groups->currentPage(),
                'lastPage' => $groups->lastPage(),
                'perPage' => $groups->perPage(),
                'total' => $groups->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'category' => $validated['category'] ?? '',
                'condition' => $validated['condition'] ?? '',
                'status' => $validated['status'] ?? '',
                'funding' => $validated['funding'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => 'all',
                'perPage' => $perPage,
            ],
            'categoryOptions' => $this->categoryOptions(),
            'importResult' => $request->session()->get('import_result'),
            'urls' => $this->urls(),
        ]);
    }

    /**
     * Mode sampah tetap per-unit (bukan per-grup) agar pulih selektif.
     *
     * Halaman memakai komponen Index yang sama (tab "Sampah") supaya
     * perpindahan mode tidak memuat ulang kerangka halaman.
     */
    private function trashIndex(Request $request, array $validated): Response
    {
        $query = CampusAsset::onlyTrashed()->with('room:id,name');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('asset_code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('brand', 'like', "%{$q}%"));
        }

        $perPage = (int) ($validated['perPage'] ?? 10);
        $assets = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Campus/Asset/Index', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Sampah Aset'),
            'can' => $this->permissions(),
            'stats' => $this->stats(),
            'data' => [
                'rows' => [],
                'currentPage' => 1,
                'lastPage' => 1,
                'perPage' => $perPage,
                'total' => 0,
            ],
            'trash' => [
                'rows' => collect($assets->items())->values()->map(fn ($a, $i) => [
                    'id' => $a->id,
                    'no' => ($assets->firstItem() ?? 0) + $i,
                    'assetCode' => $a->asset_code,
                    'name' => $a->name,
                    'room' => $a->room?->name,
                    'restoreUrl' => route('admin.campus.assets.restore', $a->id),
                    'forceUrl' => route('admin.campus.assets.force-destroy', $a->id),
                ])->all(),
                'currentPage' => $assets->currentPage(),
                'lastPage' => $assets->lastPage(),
                'perPage' => $assets->perPage(),
                'total' => $assets->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'category' => '',
                'condition' => '',
                'status' => '',
                'funding' => '',
                'sort' => 'id',
                'direction' => 'desc',
                'mode' => 'trash',
                'perPage' => $perPage,
            ],
            'categoryOptions' => $this->categoryOptions(),
            'importResult' => $request->session()->get('import_result'),
            'urls' => $this->urls(),
        ]);
    }

    /**
     * @return array{create: bool, update: bool, delete: bool, restore: bool, view: bool}
     */
    private function permissions(): array
    {
        return [
            'create' => ActivePermission::check('campus-asset.create'),
            'update' => ActivePermission::check('campus-asset.update'),
            'delete' => ActivePermission::check('campus-asset.delete'),
            'restore' => ActivePermission::any(['campus-asset.update', 'campus-asset.delete']),
            'view' => ActivePermission::check('campus-asset.view'),
        ];
    }

    /**
     * @return array{products: int, total: int, good: int, damaged: int, trashed: int}
     */
    private function stats(): array
    {
        return [
            'products' => $this->productCount(),
            'total' => CampusAsset::count(),
            'good' => CampusAsset::where('condition', 'good')->count(),
            'damaged' => CampusAsset::whereIn('condition', ['minor_damage', 'major_damage'])->count(),
            'trashed' => CampusAsset::onlyTrashed()->count(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function urls(): array
    {
        return [
            'index' => route('admin.campus.assets.index'),
            'create' => route('admin.campus.assets.create'),
            'bulkCreate' => route('admin.campus.assets.bulk-create'),
            'export' => route('admin.campus.assets.export'),
            'exportPdf' => route('admin.campus.exports.pdf', ['resource' => 'campus-assets']),
            'importTemplate' => route('admin.campus.assets.import-template'),
            'importSubmit' => route('admin.campus.assets.import'),
            'bulkRestore' => route('admin.campus.assets.bulk-restore'),
            'bulkForceDestroy' => route('admin.campus.assets.bulk-force-destroy'),
        ];
    }

    private function productCount(): int
    {
        return CampusAsset::query()
            ->select(['name', 'brand', 'model_number', 'category_id'])
            ->distinct()
            ->get()
            ->count();
    }

    public function show(Request $request, CampusAsset $asset): Response
    {
        $asset->loadMissing(['room:id,name', 'room.building:id,name', 'category:id,name', 'maintenanceTickets' => fn ($q) => $q->orderByDesc('id')->limit(10)]);

        return Inertia::render('Admin/Campus/Asset/Show', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Detail Aset'),
            'asset' => [
                'id' => $asset->id,
                'assetCode' => $asset->asset_code,
                'name' => $asset->name,
                'category' => $asset->category?->name,
                'room' => $asset->room?->name,
                'building' => $asset->room?->building?->name,
                'brand' => $asset->brand,
                'modelNumber' => $asset->model_number,
                'serialNumber' => $asset->serial_number,
                'condition' => $asset->condition,
                'conditionLabel' => self::CONDITIONS[$asset->condition] ?? $asset->condition,
                'status' => $asset->status,
                'statusLabel' => self::STATUSES[$asset->status] ?? $asset->status,
                'purchaseDate' => $asset->purchase_date?->format('d M Y'),
                'purchaseCost' => $asset->purchase_cost !== null ? 'Rp '.number_format((float) $asset->purchase_cost, 0, ',', '.') : null,
                'funding' => $asset->funding_source ? ((self::FUNDINGS[$asset->funding_source] ?? $asset->funding_source).($asset->funding_detail ? ' — '.$asset->funding_detail : '')) : null,
                'isBorrowable' => (bool) $asset->is_borrowable,
                'notes' => $asset->notes,
                'createdAt' => $asset->created_at?->format('d M Y H:i'),
                'updatedAt' => $asset->updated_at?->format('d M Y H:i'),
                'tickets' => $asset->maintenanceTickets->map(fn ($t) => [
                    'ticketNumber' => $t->ticket_number,
                    'title' => $t->title,
                    'status' => $t->status,
                    'priority' => $t->priority,
                ])->all(),
            ],
            'can' => [
                'update' => ActivePermission::check('campus-asset.update'),
                'delete' => ActivePermission::check('campus-asset.delete'),
            ],
            'urls' => [
                'index' => route('admin.campus.assets.index'),
                'edit' => route('admin.campus.assets.edit', $asset),
                'destroy' => route('admin.campus.assets.destroy', $asset),
            ],
        ]);
    }

    public function showProduct(Request $request): Response
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'category' => 'nullable|integer|exists:asset_categories,id',
        ]);

        $key = AssetProductService::normalizeKey($validated);
        $summary = AssetProductService::summarize($key);

        abort_if($summary['qty'] === 0, 404);

        $units = AssetProductService::unitsFor($key)->map(fn ($u) => [
            'id' => $u->id,
            'assetCode' => $u->asset_code,
            'serialNumber' => $u->serial_number,
            'room' => $u->room?->name,
            'building' => $u->room?->building?->name,
            'condition' => $u->condition,
            'conditionLabel' => self::CONDITIONS[$u->condition] ?? $u->condition,
            'status' => $u->status,
            'statusLabel' => self::STATUSES[$u->status] ?? $u->status,
            'funding' => $u->funding_source ? (self::FUNDINGS[$u->funding_source] ?? $u->funding_source) : null,
            'fundingDetail' => $u->funding_detail,
            'purchaseDate' => $u->purchase_date?->format('d M Y'),
            'editUrl' => route('admin.campus.assets.edit', $u->id),
            'showUrl' => route('admin.campus.assets.show', $u->id),
            'deleteUrl' => route('admin.campus.assets.destroy', $u->id),
            'reportUrl' => route('admin.campus.assets.report-damage', $u->id),
        ])->all();

        return Inertia::render('Admin/Campus/Asset/Product', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Detail Produk Aset'),
            'product' => [...$summary, 'units' => $units],
            'can' => [
                'update' => ActivePermission::check('campus-asset.update'),
                'delete' => ActivePermission::check('campus-asset.delete'),
                'report' => ActivePermission::check('campus-asset.update'),
            ],
            'urls' => [
                'index' => route('admin.campus.assets.index'),
            ],
        ]);
    }

    public function bulkCreate(Request $request): Response
    {
        return Inertia::render('Admin/Campus/Asset/BulkForm', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Tambah Banyak Unit'),
            'categoryOptions' => $this->categoryOptions(),
            'roomOptions' => $this->roomOptions(),
            'fundings' => self::FUNDINGS,
            'urls' => [
                'index' => route('admin.campus.assets.index'),
                'submit' => route('admin.campus.assets.bulk-store'),
            ],
        ]);
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|integer|exists:asset_categories,id',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'brand' => 'nullable|string|max:100',
            'model_number' => 'nullable|string|max:100',
            'condition' => ['required', 'string', Rule::in(array_keys(self::CONDITIONS))],
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0|max:9999999999999.99',
            'funding_source' => 'nullable|string|in:'.implode(',', array_keys(self::FUNDINGS)),
            'funding_detail' => 'nullable|string|max:255',
            'is_borrowable' => 'boolean',
            'status' => ['required', 'string', Rule::in(array_keys(self::STATUSES))],
            'quantity' => 'required|integer|min:2|max:100',
            'notes' => 'nullable|string',
        ]);

        $codes = [];

        for ($i = 0; $i < $validated['quantity']; $i++) {
            $codes[] = [
                'asset_code' => AssetCodeGenerator::next(),
                'name' => $validated['name'],
                'category_id' => $validated['category_id'] ?? null,
                'room_id' => $validated['room_id'] ?? null,
                'brand' => ($validated['brand'] ?? null) ?: null,
                'model_number' => ($validated['model_number'] ?? null) ?: null,
                'condition' => $validated['condition'],
                'purchase_date' => $validated['purchase_date'] ?? null,
                'purchase_cost' => ($validated['purchase_cost'] ?? null) !== null && $validated['purchase_cost'] !== '' ? $validated['purchase_cost'] : null,
                'funding_source' => $validated['funding_source'] ?? null,
                'funding_detail' => ($validated['funding_detail'] ?? null) ?: null,
                'is_borrowable' => $validated['is_borrowable'] ?? false,
                'status' => $validated['status'],
                'notes' => ($validated['notes'] ?? null) ?: null,
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        CampusAsset::insert($codes);

        return redirect()->route('admin.campus.assets.index')
            ->with('success', count($codes).' unit "'.$validated['name'].'" berhasil ditambahkan ('.$codes[0]['asset_code'].' s.d. '.$codes[count($codes) - 1]['asset_code'].').');
    }

    public function reportDamage(Request $request, CampusAsset $asset)
    {
        $validated = $request->validate([
            'condition' => 'required|string|in:minor_damage,major_damage',
            'priority' => 'required|string|in:low,medium,high,urgent',
            'notes' => 'nullable|string|max:2000',
        ]);

        $ticket = \App\Models\Campus\FacilityMaintenanceTicket::create([
            'ticket_number' => $this->generateTicketNumber(),
            'title' => 'Kerusakan: '.$asset->name.' ('.$asset->asset_code.')',
            'description' => ($validated['notes'] ?? null) ?: 'Dilaporkan dari halaman aset.',
            'room_id' => $asset->room_id,
            'campus_asset_id' => $asset->id,
            'reported_by_user_id' => $request->user()->id,
            'priority' => $validated['priority'],
            'status' => 'open',
            'created_by' => $request->user()->id,
        ]);

        $asset->update([
            'condition' => $validated['condition'],
            'status' => 'maintenance',
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.campus.maintenance.show', $ticket)
            ->with('success', 'Tiket "'.$ticket->ticket_number.'" dibuat. Aset masuk maintenance.');
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Campus/Asset/Form', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Tambah Aset'),
            'mode' => 'create',
            'asset' => [
                'asset_code' => $this->generateCode(), 'name' => '', 'category_id' => null,
                'room_id' => null, 'brand' => '', 'model_number' => '', 'serial_number' => '',
                'condition' => 'good', 'purchase_date' => '', 'purchase_cost' => '',
                'funding_source' => '', 'funding_detail' => '',
                'is_borrowable' => false, 'status' => 'active', 'notes' => '',
            ],
            'categoryOptions' => $this->categoryOptions(),
            'conditions' => self::CONDITIONS,
            'statuses' => self::STATUSES,
            'fundings' => self::FUNDINGS,
            'roomOptions' => $this->roomOptions(),
            'urls' => [
                'index' => route('admin.campus.assets.index'),
                'submit' => route('admin.campus.assets.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_code' => 'required|string|max:50|unique:campus_assets,asset_code',
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|integer|exists:asset_categories,id',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'brand' => 'nullable|string|max:100',
            'model_number' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'condition' => ['required', 'string', Rule::in(array_keys(self::CONDITIONS))],
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0|max:9999999999999.99',
            'funding_source' => 'nullable|string|in:'.implode(',', array_keys(self::FUNDINGS)),
            'funding_detail' => 'nullable|string|max:255',
            'is_borrowable' => 'boolean',
            'status' => ['required', 'string', Rule::in(array_keys(self::STATUSES))],
            'notes' => 'nullable|string',
        ]);

        $asset = CampusAsset::create([
            'asset_code' => $validated['asset_code'],
            'name' => $validated['name'],
            'category_id' => $validated['category_id'] ?? null,
            'room_id' => $validated['room_id'] ?? null,
            'brand' => ($validated['brand'] ?? null) ?: null,
            'model_number' => ($validated['model_number'] ?? null) ?: null,
            'serial_number' => ($validated['serial_number'] ?? null) ?: null,
            'condition' => $validated['condition'],
            'purchase_date' => $validated['purchase_date'] ?? null,
            'purchase_cost' => ($validated['purchase_cost'] ?? null) !== null && $validated['purchase_cost'] !== '' ? $validated['purchase_cost'] : null,
            'funding_source' => $validated['funding_source'] ?? null,
            'funding_detail' => ($validated['funding_detail'] ?? null) ?: null,
            'is_borrowable' => $validated['is_borrowable'] ?? false,
            'status' => $validated['status'],
            'notes' => ($validated['notes'] ?? null) ?: null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.campus.assets.index')
            ->with('success', 'Aset "'.$asset->name.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, CampusAsset $asset): Response
    {
        return Inertia::render('Admin/Campus/Asset/Form', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Edit Aset'),
            'mode' => 'edit',
            'asset' => [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'category_id' => $asset->category_id,
                'room_id' => $asset->room_id,
                'brand' => $asset->brand ?? '',
                'model_number' => $asset->model_number ?? '',
                'serial_number' => $asset->serial_number ?? '',
                'condition' => $asset->condition,
                'purchase_date' => $asset->purchase_date?->format('Y-m-d') ?? '',
                'purchase_cost' => $asset->purchase_cost !== null ? (string) $asset->purchase_cost : '',
                'funding_source' => $asset->funding_source ?? '',
                'funding_detail' => $asset->funding_detail ?? '',
                'is_borrowable' => (bool) $asset->is_borrowable,
                'status' => $asset->status,
                'notes' => $asset->notes ?? '',
            ],
            'categoryOptions' => $this->categoryOptions(),
            'conditions' => self::CONDITIONS,
            'statuses' => self::STATUSES,
            'fundings' => self::FUNDINGS,
            'roomOptions' => $this->roomOptions(),
            'urls' => [
                'index' => route('admin.campus.assets.index'),
                'submit' => route('admin.campus.assets.update', $asset),
            ],
        ]);
    }

    public function update(Request $request, CampusAsset $asset)
    {
        $validated = $request->validate([
            'asset_code' => ['required', 'string', 'max:50', Rule::unique('campus_assets', 'asset_code')->ignore($asset->id)],
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|integer|exists:asset_categories,id',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'brand' => 'nullable|string|max:100',
            'model_number' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'condition' => ['required', 'string', Rule::in(array_keys(self::CONDITIONS))],
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0|max:9999999999999.99',
            'funding_source' => 'nullable|string|in:'.implode(',', array_keys(self::FUNDINGS)),
            'funding_detail' => 'nullable|string|max:255',
            'is_borrowable' => 'boolean',
            'status' => ['required', 'string', Rule::in(array_keys(self::STATUSES))],
            'notes' => 'nullable|string',
        ]);

        $asset->update([
            'asset_code' => $validated['asset_code'],
            'name' => $validated['name'],
            'category_id' => $validated['category_id'] ?? null,
            'room_id' => $validated['room_id'] ?? null,
            'brand' => ($validated['brand'] ?? null) ?: null,
            'model_number' => ($validated['model_number'] ?? null) ?: null,
            'serial_number' => ($validated['serial_number'] ?? null) ?: null,
            'condition' => $validated['condition'],
            'purchase_date' => $validated['purchase_date'] ?? null,
            'purchase_cost' => ($validated['purchase_cost'] ?? null) !== null && $validated['purchase_cost'] !== '' ? $validated['purchase_cost'] : null,
            'funding_source' => $validated['funding_source'] ?? null,
            'funding_detail' => ($validated['funding_detail'] ?? null) ?: null,
            'is_borrowable' => $validated['is_borrowable'] ?? false,
            'status' => $validated['status'],
            'notes' => ($validated['notes'] ?? null) ?: null,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.campus.assets.index')
            ->with('success', 'Aset "'.$asset->name.'" berhasil diperbarui.');
    }

    public function destroy(CampusAsset $asset)
    {
        if ($asset->maintenanceTickets()->count() > 0) {
            return back()->with('error', 'Aset "'.$asset->name.'" masih memiliki tiket maintenance. Selesaikan atau hapus tiket terlebih dahulu.');
        }

        $name = $asset->name;
        $asset->delete();

        return redirect()->route('admin.campus.assets.index')
            ->with('success', 'Aset "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:campus_assets,id',
        ]);

        $assets = CampusAsset::whereIn('id', $validated['ids'])->withCount('maintenanceTickets')->get();
        $blocked = $assets->where('maintenance_tickets_count', '>', 0);
        $deletable = $assets->where('maintenance_tickets_count', 0);

        CampusAsset::whereIn('id', $deletable->pluck('id'))->delete();

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' aset dihapus. '.$blocked->count().' tidak bisa dihapus karena masih memiliki tiket maintenance.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Aset tidak bisa dihapus karena masih memiliki tiket maintenance.');
        }

        return back()->with('success', $deletable->count().' aset berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $asset = CampusAsset::withTrashed()->findOrFail($id);
        $asset->restore();

        return back()->with('success', 'Aset "'.$asset->name.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $asset = CampusAsset::withTrashed()->findOrFail($id);

        if ($asset->maintenanceTickets()->withTrashed()->count() > 0) {
            return back()->with('error', 'Aset "'.$asset->name.'" masih memiliki tiket maintenance.');
        }

        $name = $asset->name;
        $asset->forceDelete();

        return back()->with('success', 'Aset "'.$name.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:campus_assets,id',
        ]);

        $count = 0;

        foreach (CampusAsset::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $asset) {
            $asset->restore();
            $count++;
        }

        return back()->with('success', $count.' aset berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:campus_assets,id',
        ]);

        $assets = CampusAsset::withTrashed()->whereIn('id', $validated['ids'])->withCount(['maintenanceTickets' => fn ($q) => $q->withTrashed()])->get();
        $blocked = $assets->where('maintenance_tickets_count', '>', 0);
        $deletable = $assets->where('maintenance_tickets_count', 0);

        foreach ($deletable as $asset) {
            $asset->forceDelete();
        }

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' aset dihapus permanen. '.$blocked->count().' tidak bisa dihapus karena masih memiliki tiket.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Aset tidak bisa dihapus karena masih memiliki tiket maintenance.');
        }

        return back()->with('success', $deletable->count().' aset dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = CampusAsset::query()->with(['room:id,name', 'category:id,name']);

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(fn ($sub) => $sub
                ->where('asset_code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('brand', 'like', "%{$q}%"));
        }

        $rows = $query->orderBy('name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Kode', 'Nama', 'Kategori', 'Ruangan', 'Kondisi', 'Status', 'Sumber Dana', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $asset) {
            $sheet->fromArray([
                $asset->asset_code,
                $asset->name,
                $asset->category?->name,
                $asset->room?->name,
                self::CONDITIONS[$asset->condition] ?? $asset->condition,
                self::STATUSES[$asset->status] ?? $asset->status,
                $asset->funding_source ? ((self::FUNDINGS[$asset->funding_source] ?? $asset->funding_source).($asset->funding_detail ? ' — '.$asset->funding_detail : '')) : null,
                $asset->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'campus-assets-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    public function importTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['asset_code', 'name', 'category', 'room', 'brand', 'condition', 'status', 'funding_source', 'funding_detail', 'is_borrowable']], null, 'A1');
        $sheet->fromArray([['AST-2026-XXXXX', 'Proyektor Epson', 'electronics', 'Ruang 101', 'Epson', 'good', 'active', 'hibah', 'Hibah Kemendikbud 2025', '0']], null, 'A2');
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-campus-assets.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (\Throwable) {
            return back()->with('error', 'File tidak bisa dibaca. Gunakan template yang disediakan.');
        }

        $rows = $spreadsheet->getActiveSheet()->toArray();
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $rows ? array_shift($rows) : []);
        $missing = array_diff(['asset_code', 'name'], $header);

        if (! empty($missing)) {
            return back()->with('error', 'Header wajib hilang: '.implode(', ', $missing).'. Unduh template terbaru.');
        }

        $rows = array_values(array_filter(
            $rows,
            fn ($row) => collect($row)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()
        ));

        if (empty($rows)) {
            return back()->with('error', 'File tidak berisi data.');
        }

        if (count($rows) > 500) {
            return back()->with('error', 'Maksimal 500 baris per import.');
        }

        $seenCodes = [];
        $valid = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $data = array_combine(
                $header,
                array_pad(array_slice($row, 0, count($header)), count($header), null)
            );
            $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
            $rowErrors = [];

            $code = $data['asset_code'] ?? null;

            if ($code === null || $code === '') {
                $rowErrors[] = 'asset_code wajib diisi';
            } else {
                if (strlen((string) $code) > 50) {
                    $rowErrors[] = 'asset_code maksimal 50 karakter';
                } elseif (in_array($code, $seenCodes, true)) {
                    $rowErrors[] = 'asset_code duplikat di dalam file';
                } elseif (CampusAsset::where('asset_code', $code)->exists()) {
                    $rowErrors[] = 'asset_code sudah terdaftar';
                } else {
                    $seenCodes[] = $code;
                }
            }

            if (($data['name'] ?? null) === null || $data['name'] === '') {
                $rowErrors[] = 'name wajib diisi';
            } elseif (strlen((string) $data['name']) > 255) {
                $rowErrors[] = 'name maksimal 255 karakter';
            }

            $categoryId = null;

            if (isset($data['category']) && $data['category'] !== '') {
                $categoryId = \App\Models\Campus\AssetCategory::where('name', $data['category'])
                    ->orWhere('code', $data['category'])
                    ->value('id');

                if (! $categoryId) {
                    $rowErrors[] = 'category tidak ditemukan';
                }
            }

            $roomId = null;

            if (isset($data['room']) && $data['room'] !== '') {
                $roomId = Room::where('name', $data['room'])->value('id');

                if (! $roomId) {
                    $rowErrors[] = 'room tidak ditemukan';
                }
            }

            $condition = $data['condition'] ?? 'good';

            if (! array_key_exists($condition, self::CONDITIONS)) {
                $rowErrors[] = 'condition tidak valid';
            }

            $funding = $data['funding_source'] ?? null;

            if ($funding !== null && $funding !== '' && ! array_key_exists($funding, self::FUNDINGS)) {
                $rowErrors[] = 'funding_source tidak valid ('.implode('/', array_keys(self::FUNDINGS)).')';
            }

            if (isset($data['funding_detail']) && strlen((string) $data['funding_detail']) > 255) {
                $rowErrors[] = 'funding_detail maksimal 255 karakter';
            }

            $status = $data['status'] ?? 'active';

            if (! array_key_exists($status, self::STATUSES)) {
                $rowErrors[] = 'status tidak valid';
            }

            if (! empty($errors) && count($errors) >= 20) {
                break;
            }

            if (! empty($rowErrors)) {
                $errors[] = ['row' => $line, 'messages' => $rowErrors];
                continue;
            }

            $valid[] = [
                'asset_code' => $code,
                'name' => $data['name'],
                'category_id' => $categoryId,
                'room_id' => $roomId,
                'brand' => $data['brand'] ?? null,
                'condition' => $condition,
                'status' => $status,
                'funding_source' => ($funding ?? null) !== null && $funding !== '' ? $funding : null,
                'funding_detail' => isset($data['funding_detail']) && $data['funding_detail'] !== '' ? $data['funding_detail'] : null,
                'is_borrowable' => $this->parseImportBool($data['is_borrowable'] ?? null, false),
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.campus.assets.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        CampusAsset::insert($valid);

        return redirect()->route('admin.campus.assets.index')
            ->with('success', count($valid).' aset berhasil diimpor.')
            ->with('import_result', [
                'success' => true, 'created' => count($valid), 'rejected' => 0, 'errors' => [],
            ]);
    }

    private function categoryOptions(): array
    {
        return \App\Models\Campus\AssetCategory::query()->where('is_active', true)
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all();
    }

    private function roomOptions(): array
    {        return Room::query()->where('is_active', true)
            ->with('building:id,name')
            ->orderBy('name')->get(['id', 'name', 'building_id'])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name.($r->building ? ' ('.$r->building->name.')' : '')])->all();
    }

    private function generateTicketNumber(): string
    {
        do {
            $number = 'TCK-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
        } while (\App\Models\Campus\FacilityMaintenanceTicket::where('ticket_number', $number)->exists());

        return $number;
    }

    private function generateCode(): string
    {
        return AssetCodeGenerator::next();
    }

    private function parseImportBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'ya', 'y'], true);
    }
}
