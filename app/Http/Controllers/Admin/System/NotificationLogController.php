<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use App\Models\Settings\NotificationLog;
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
 * Log Notifikasi — read-only (paritas NotificationLogTable).
 * Tanpa bulk, tanpa hapus: log adalah jejak audit.
 */
class NotificationLogController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'channel' => 'nullable|in:whatsapp,email,in_app,web_push',
            'status' => 'nullable|in:queued,sent,failed,skipped',
            'sort' => 'nullable|in:id,event_key,channel,status,sent_at,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = NotificationLog::query();

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('event_key', 'like', "%{$q}%")
                ->orWhere('subject', 'like', "%{$q}%")
                ->orWhere('recipient_name', 'like', "%{$q}%")
                ->orWhere('recipient_phone', 'like', "%{$q}%"));
        }

        if (filled($validated['channel'] ?? null)) {
            $query->where('channel', $validated['channel']);
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $logs = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/System/NotificationLog/Index', [
            'shell' => ShellProps::make($request->user(), 'Sistem', 'Log Notifikasi'),
            'stats' => [
                'total' => NotificationLog::count(),
                'sent' => NotificationLog::where('status', 'sent')->count(),
                'failed' => NotificationLog::where('status', 'failed')->count(),
                'skipped' => NotificationLog::where('status', 'skipped')->count(),
            ],
            'data' => [
                'rows' => collect($logs->items())->values()->map(fn ($log, $i) => [
                    'id' => $log->id,
                    'no' => ($logs->firstItem() ?? 0) + $i,
                    'event' => $log->event_key,
                    'channel' => $log->channel,
                    'provider' => $log->provider,
                    'status' => $log->status,
                    'statusTone' => $this->statusTone($log->status),
                    'recipient' => $log->recipient_name,
                    'phone' => $log->recipient_phone,
                    'subject' => $log->subject,
                    'bodyPreview' => str($log->body ?? '-')->limit(120)->toString(),
                    'errorPreview' => str($log->error_message ?? '-')->limit(120)->toString(),
                    'sentAt' => $log->sent_at?->format('d M Y H:i'),
                    'createdAt' => $log->created_at?->format('d M Y H:i'),
                ])->all(),
                'currentPage' => $logs->currentPage(),
                'lastPage' => $logs->lastPage(),
                'perPage' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'channel' => $validated['channel'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'urls' => [
                'index' => route('admin.system.notification-logs.index'),
                'export' => route('admin.system.notification-logs.export'),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = NotificationLog::query();

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(fn ($sub) => $sub
                ->where('event_key', 'like', "%{$q}%")
                ->orWhere('subject', 'like', "%{$q}%")
                ->orWhere('recipient_name', 'like', "%{$q}%")
                ->orWhere('recipient_phone', 'like', "%{$q}%"));
        }

        foreach (['channel', 'status'] as $column) {
            if (filled($request->query($column))) {
                $query->where($column, $request->query($column));
            }
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Event', 'Channel', 'Provider', 'Status', 'Penerima', 'No. WA', 'Subject', 'Error', 'Terkirim', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $log) {
            $sheet->fromArray([
                $log->event_key,
                $log->channel,
                $log->provider,
                $log->status,
                $log->recipient_name,
                $log->recipient_phone,
                $log->subject,
                $log->error_message,
                $log->sent_at?->format('Y-m-d H:i:s'),
                $log->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'notification-logs-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    private function statusTone(?string $status): string
    {
        return match ($status) {
            'sent' => 'green',
            'failed' => 'red',
            'skipped' => 'gray',
            default => 'amber',
        };
    }
}
