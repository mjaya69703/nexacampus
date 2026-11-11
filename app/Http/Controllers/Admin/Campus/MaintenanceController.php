<?php

namespace App\Http\Controllers\Admin\Campus;

use App\Http\Controllers\Controller;
use App\Models\Campus\CampusAsset;
use App\Models\Campus\FacilityMaintenanceTicket;
use App\Models\Campus\Room;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Laporan Kerusakan — memakai kit CRUD shared (pola menu academic).
 *
 * Lifecycle: create selalu open; resolve hanya lewat endpoint resolve yang
 * dikawal permission facility-maintenance.resolve; resolved_at dikelola
 * otomatis (diisi saat masuk resolved, dibersihkan saat keluar); resolve
 * mengembalikan aset terkait ke kondisi baik + status aktif.
 */
class MaintenanceController extends Controller
{
    public const PRIORITIES = [
        'low' => 'Rendah',
        'medium' => 'Sedang',
        'high' => 'Tinggi',
        'urgent' => 'Sangat Mendesak',
    ];

    public const STATUSES = [
        'open' => 'Terbuka',
        'in_progress' => 'Dalam Pengerjaan',
        'resolved' => 'Selesai',
        'closed' => 'Ditutup',
        'rejected' => 'Ditolak',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'priority' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
            'sort' => 'nullable|in:id,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = FacilityMaintenanceTicket::query()
            ->with(['room:id,name', 'campusAsset:id,name', 'reporter:id,first_name,last_name']);

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('ticket_number', 'like', "%{$q}%")
                ->orWhere('title', 'like', "%{$q}%"));
        }

        if (filled($validated['priority'] ?? null)) {
            $query->where('priority', $validated['priority']);
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $tickets = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Campus/Maintenance/Index', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Daftar Laporan Kerusakan'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('facility-maintenance.create'),
                'update' => ActivePermission::check('facility-maintenance.update'),
                'delete' => ActivePermission::check('facility-maintenance.delete'),
                'restore' => ActivePermission::any(['facility-maintenance.update', 'facility-maintenance.delete']),
                'resolve' => ActivePermission::check('facility-maintenance.resolve'),
                'view' => ActivePermission::check('facility-maintenance.view'),
            ],
            'stats' => [
                'total' => FacilityMaintenanceTicket::count(),
                'open' => FacilityMaintenanceTicket::where('status', 'open')->count(),
                'progress' => FacilityMaintenanceTicket::where('status', 'in_progress')->count(),
                'resolved' => FacilityMaintenanceTicket::where('status', 'resolved')->count(),
                'trashed' => FacilityMaintenanceTicket::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($tickets->items())->values()->map(fn ($t, $i) => [
                    'id' => $t->id,
                    'no' => ($tickets->firstItem() ?? 0) + $i,
                    'ticketNumber' => $t->ticket_number,
                    'title' => $t->title,
                    'room' => $t->room?->name,
                    'asset' => $t->campusAsset?->name,
                    'reporter' => $t->reporter?->name ?? '-',
                    'priority' => $t->priority,
                    'priorityLabel' => self::PRIORITIES[$t->priority] ?? $t->priority,
                    'status' => $t->status,
                    'statusLabel' => self::STATUSES[$t->status] ?? $t->status,
                    'createdAt' => $t->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.campus.maintenance.edit', $t->id),
                    'showUrl' => route('admin.campus.maintenance.show', $t->id),
                    'deleteUrl' => route('admin.campus.maintenance.destroy', $t->id),
                    'restoreUrl' => route('admin.campus.maintenance.restore', $t->id),
                    'forceUrl' => route('admin.campus.maintenance.force-destroy', $t->id),
                    'resolveUrl' => route('admin.campus.maintenance.resolve', $t->id),
                ])->all(),
                'currentPage' => $tickets->currentPage(),
                'lastPage' => $tickets->lastPage(),
                'perPage' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'priority' => $validated['priority'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'urls' => [
                'index' => route('admin.campus.maintenance.index'),
                'create' => route('admin.campus.maintenance.create'),
                'export' => route('admin.campus.maintenance.export'),
                'exportPdf' => route('admin.campus.exports.pdf', ['resource' => 'facility-maintenances']),
                'bulkDestroy' => route('admin.campus.maintenance.bulk-destroy'),
                'bulkRestore' => route('admin.campus.maintenance.bulk-restore'),
                'bulkForceDestroy' => route('admin.campus.maintenance.bulk-force-destroy'),
            ],
        ]);
    }

    public function show(Request $request, FacilityMaintenanceTicket $ticket): Response
    {
        $ticket->loadMissing(['room.building:id,name', 'campusAsset:id,name,asset_code', 'reporter:id,first_name,last_name,email', 'assignee:id,first_name,last_name']);

        return Inertia::render('Admin/Campus/Maintenance/Show', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Detail Tiket Kerusakan'),
            'ticket' => [
                'id' => $ticket->id,
                'ticketNumber' => $ticket->ticket_number,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'room' => $ticket->room?->name,
                'building' => $ticket->room?->building?->name,
                'asset' => $ticket->campusAsset?->name,
                'assetCode' => $ticket->campusAsset?->asset_code,
                'reporter' => $ticket->reporter?->name ?? '-',
                'reporterEmail' => $ticket->reporter?->email,
                'assignee' => $ticket->assignee?->name,
                'priority' => $ticket->priority,
                'priorityLabel' => self::PRIORITIES[$ticket->priority] ?? $ticket->priority,
                'status' => $ticket->status,
                'statusLabel' => self::STATUSES[$ticket->status] ?? $ticket->status,
                'resolutionNotes' => $ticket->resolution_notes,
                'repairCost' => $ticket->repair_cost !== null ? 'Rp '.number_format((float) $ticket->repair_cost, 0, ',', '.') : null,
                'resolvedAt' => $ticket->resolved_at?->format('d M Y H:i'),
                'createdAt' => $ticket->created_at?->format('d M Y H:i'),
            ],
            'can' => [
                'update' => ActivePermission::check('facility-maintenance.update'),
                'delete' => ActivePermission::check('facility-maintenance.delete'),
                'resolve' => ActivePermission::check('facility-maintenance.resolve'),
            ],
            'urls' => [
                'index' => route('admin.campus.maintenance.index'),
                'edit' => route('admin.campus.maintenance.edit', $ticket),
                'destroy' => route('admin.campus.maintenance.destroy', $ticket),
                'resolve' => route('admin.campus.maintenance.resolve', $ticket),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Campus/Maintenance/Form', [
            'shell' => ShellProps::make($user, 'Kampus', 'Buat Laporan Kerusakan'),
            'mode' => 'create',
            'ticket' => [
                'title' => '', 'description' => '',
                'room_id' => null, 'room_label' => null,
                'campus_asset_id' => null, 'asset_label' => null,
                'reported_by_user_id' => $user->id, 'reporter_label' => $user->name,
                'assigned_to_user_id' => null, 'assignee_label' => null,
                'priority' => 'medium',
            ],
            'priorities' => self::PRIORITIES,
            'urls' => [
                'index' => route('admin.campus.maintenance.index'),
                'submit' => route('admin.campus.maintenance.store'),
                'searchUsers' => route('admin.campus.maintenance.search-users'),
                'searchRooms' => route('admin.campus.maintenance.search-rooms'),
                'searchAssets' => route('admin.campus.maintenance.search-assets'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'campus_asset_id' => 'nullable|integer|exists:campus_assets,id',
            'reported_by_user_id' => 'required|integer|exists:users,id',
            'assigned_to_user_id' => 'nullable|integer|exists:users,id',
            'priority' => 'required|string|in:low,medium,high,urgent',
        ]);

        $ticket = FacilityMaintenanceTicket::create([
            'ticket_number' => $this->generateNumber(),
            'title' => $validated['title'],
            'description' => $validated['description'],
            'room_id' => $validated['room_id'] ?? null,
            'campus_asset_id' => $validated['campus_asset_id'] ?? null,
            'reported_by_user_id' => $validated['reported_by_user_id'],
            'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? null,
            'priority' => $validated['priority'],
            'status' => 'open',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.campus.maintenance.index')
            ->with('success', 'Laporan "'.$ticket->ticket_number.'" berhasil dibuat.');
    }

    public function edit(Request $request, FacilityMaintenanceTicket $ticket): Response
    {
        $ticket->loadMissing(['reporter:id,first_name,last_name', 'assignee:id,first_name,last_name', 'room:id,name', 'campusAsset:id,name,asset_code']);

        return Inertia::render('Admin/Campus/Maintenance/Form', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Edit Laporan Kerusakan'),
            'mode' => 'edit',
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'room_id' => $ticket->room_id,
                'room_label' => $ticket->room?->name,
                'campus_asset_id' => $ticket->campus_asset_id,
                'asset_label' => $ticket->campusAsset ? $ticket->campusAsset->name.' ('.$ticket->campusAsset->asset_code.')' : null,
                'reported_by_user_id' => $ticket->reported_by_user_id,
                'reporter_label' => $ticket->reporter?->name ?? '-',
                'assigned_to_user_id' => $ticket->assigned_to_user_id,
                'assignee_label' => $ticket->assignee?->name,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'resolution_notes' => $ticket->resolution_notes ?? '',
                'repair_cost' => $ticket->repair_cost !== null ? (string) $ticket->repair_cost : '',
            ],
            'priorities' => self::PRIORITIES,
            'statuses' => self::STATUSES,
            'urls' => [
                'index' => route('admin.campus.maintenance.index'),
                'submit' => route('admin.campus.maintenance.update', $ticket),
                'searchUsers' => route('admin.campus.maintenance.search-users'),
                'searchRooms' => route('admin.campus.maintenance.search-rooms'),
                'searchAssets' => route('admin.campus.maintenance.search-assets'),
            ],
        ]);
    }

    public function update(Request $request, FacilityMaintenanceTicket $ticket)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'campus_asset_id' => 'nullable|integer|exists:campus_assets,id',
            'reported_by_user_id' => 'required|integer|exists:users,id',
            'assigned_to_user_id' => 'nullable|integer|exists:users,id',
            'priority' => 'required|string|in:low,medium,high,urgent',
            'status' => 'required|string|in:open,in_progress,resolved,closed,rejected',
            'resolution_notes' => 'nullable|string|max:5000',
            'repair_cost' => 'nullable|numeric|min:0|max:9999999999999.99',
        ]);

        $resolvedAt = $ticket->resolved_at;

        if ($validated['status'] === 'resolved' && $ticket->status !== 'resolved') {
            $resolvedAt = now();
        } elseif ($validated['status'] !== 'resolved') {
            $resolvedAt = null;
        }

        $ticket->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'room_id' => $validated['room_id'] ?? null,
            'campus_asset_id' => $validated['campus_asset_id'] ?? null,
            'reported_by_user_id' => $validated['reported_by_user_id'],
            'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? null,
            'priority' => $validated['priority'],
            'status' => $validated['status'],
            'resolution_notes' => ($validated['resolution_notes'] ?? null) ?: null,
            'repair_cost' => ($validated['repair_cost'] ?? null) !== null && $validated['repair_cost'] !== '' ? $validated['repair_cost'] : null,
            'resolved_at' => $resolvedAt,
            'updated_by' => $request->user()->id,
        ]);

        if ($validated['status'] === 'resolved') {
            $this->restoreAsset($ticket, $request->user()->id);
        }

        return redirect()->route('admin.campus.maintenance.index')
            ->with('success', 'Tiket "'.$ticket->ticket_number.'" berhasil diperbarui.');
    }

    public function resolve(Request $request, FacilityMaintenanceTicket $ticket)
    {
        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return back()->with('error', 'Tiket sudah selesai/ditutup.');
        }

        $validated = $request->validate([
            'resolution_notes' => 'required|string|max:5000',
            'repair_cost' => 'nullable|numeric|min:0|max:9999999999999.99',
        ]);

        $ticket->update([
            'status' => 'resolved',
            'resolution_notes' => $validated['resolution_notes'],
            'repair_cost' => ($validated['repair_cost'] ?? null) !== null && $validated['repair_cost'] !== '' ? $validated['repair_cost'] : $ticket->repair_cost,
            'resolved_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        $this->restoreAsset($ticket, $request->user()->id);

        return back()->with('success', 'Tiket "'.$ticket->ticket_number.'" ditandai selesai.');
    }

    public function destroy(FacilityMaintenanceTicket $ticket)
    {
        $number = $ticket->ticket_number;
        $ticket->delete();

        return redirect()->route('admin.campus.maintenance.index')
            ->with('success', 'Tiket "'.$number.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:facility_maintenance_tickets,id',
        ]);

        $count = FacilityMaintenanceTicket::whereIn('id', $validated['ids'])->count();
        FacilityMaintenanceTicket::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count.' tiket berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $ticket = FacilityMaintenanceTicket::withTrashed()->findOrFail($id);
        $ticket->restore();

        return back()->with('success', 'Tiket "'.$ticket->ticket_number.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $ticket = FacilityMaintenanceTicket::withTrashed()->findOrFail($id);

        $number = $ticket->ticket_number;
        $ticket->forceDelete();

        return back()->with('success', 'Tiket "'.$number.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:facility_maintenance_tickets,id',
        ]);

        $count = 0;

        foreach (FacilityMaintenanceTicket::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $ticket) {
            $ticket->restore();
            $count++;
        }

        return back()->with('success', $count.' tiket berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:facility_maintenance_tickets,id',
        ]);

        $count = FacilityMaintenanceTicket::withTrashed()->whereIn('id', $validated['ids'])->count();
        FacilityMaintenanceTicket::withTrashed()->whereIn('id', $validated['ids'])->forceDelete();

        return back()->with('success', $count.' tiket dihapus permanen.');
    }

    public function searchUsers(Request $request)
    {
        $validated = $request->validate(['q' => 'nullable|string|max:100']);
        $q = $validated['q'] ?? '';

        $users = \App\Models\User::query()
            ->select(['id', 'first_name', 'last_name', 'email'])
            ->when(filled($q), fn ($query) => $query->where(fn ($sub) => $sub
                ->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")))
            ->orderBy('first_name')
            ->limit(8)
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'label' => $u->name.' ('.$u->email.')'])
            ->all();

        return response()->json(['options' => $users]);
    }

    public function searchRooms(Request $request)
    {
        $validated = $request->validate(['q' => 'nullable|string|max:100']);
        $q = $validated['q'] ?? '';

        $rooms = Room::query()
            ->with('building:id,name')
            ->where('is_active', true)
            ->when(filled($q), fn ($query) => $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'building_id'])
            ->map(fn ($r) => ['id' => $r->id, 'label' => $r->name.($r->building ? ' ('.$r->building->name.')' : '')])
            ->all();

        return response()->json(['options' => $rooms]);
    }

    public function searchAssets(Request $request)
    {
        $validated = $request->validate(['q' => 'nullable|string|max:100']);
        $q = $validated['q'] ?? '';

        $assets = CampusAsset::query()
            ->with('room:id,name')
            ->where('status', '!=', 'disposed')
            ->when(filled($q), fn ($query) => $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('asset_code', 'like', "%{$q}%")))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'asset_code', 'room_id'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'label' => $a->name.' ('.$a->asset_code.')',
                'meta' => ['room_id' => $a->room_id, 'room_label' => $a->room?->name],
            ])
            ->all();

        return response()->json(['options' => $assets]);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = FacilityMaintenanceTicket::query()
            ->with(['room:id,name', 'campusAsset:id,name', 'reporter:id,first_name,last_name']);

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(fn ($sub) => $sub
                ->where('ticket_number', 'like', "%{$q}%")
                ->orWhere('title', 'like', "%{$q}%"));
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Tiket', 'Judul', 'Ruangan', 'Aset', 'Pelapor', 'Prioritas', 'Status', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $ticket) {
            $sheet->fromArray([
                $ticket->ticket_number,
                $ticket->title,
                $ticket->room?->name,
                $ticket->campusAsset?->name,
                $ticket->reporter?->name,
                self::PRIORITIES[$ticket->priority] ?? $ticket->priority,
                self::STATUSES[$ticket->status] ?? $ticket->status,
                $ticket->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'facility-maintenances-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    private function restoreAsset(FacilityMaintenanceTicket $ticket, int $by): void
    {
        if (! $ticket->campus_asset_id) {
            return;
        }

        CampusAsset::where('id', $ticket->campus_asset_id)->update([
            'condition' => 'good',
            'status' => 'active',
            'updated_by' => $by,
        ]);
    }

    private function generateNumber(): string
    {
        do {
            $number = 'TCK-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
        } while (FacilityMaintenanceTicket::where('ticket_number', $number)->exists());

        return $number;
    }
}
