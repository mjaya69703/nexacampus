<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Log Aktivitas — read-only + detail (paritas ActivityLogTable + show).
 * Tanpa bulk delete: permission delete tidak terdaftar untuk resource ini.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'log' => 'nullable|string|max:100',
            'event' => 'nullable|string|max:100',
            'sort' => 'nullable|in:id,log_name,description,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = Activity::query()->with('causer:id,first_name,last_name');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('log_name', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%")
                ->orWhere('event', 'like', "%{$q}%"));
        }

        if (filled($validated['log'] ?? null)) {
            $query->where('log_name', 'like', '%'.$validated['log'].'%');
        }

        if (filled($validated['event'] ?? null)) {
            $query->where('event', 'like', '%'.$validated['event'].'%');
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $activities = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/System/ActivityLog/Index', [
            'shell' => ShellProps::make($request->user(), 'Sistem', 'Log Aktivitas'),
            'stats' => [
                'total' => Activity::count(),
                'today' => Activity::whereDate('created_at', today())->count(),
                'logNames' => Activity::distinct()->count('log_name'),
            ],
            'data' => [
                'rows' => collect($activities->items())->values()->map(fn ($act, $i) => [
                    'id' => $act->id,
                    'no' => ($activities->firstItem() ?? 0) + $i,
                    'log' => $act->log_name,
                    'description' => $act->description,
                    'event' => $act->event,
                    'subject' => $act->subject_type ? class_basename($act->subject_type).' #'.$act->subject_id : '-',
                    'causer' => $this->causerName($act),
                    'preview' => $this->preview($act),
                    'createdAt' => $act->created_at?->format('d/m/Y H:i:s'),
                    'detailUrl' => route('admin.system.activity-logs.show', $act->id),
                ])->all(),
                'currentPage' => $activities->currentPage(),
                'lastPage' => $activities->lastPage(),
                'perPage' => $activities->perPage(),
                'total' => $activities->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'log' => $validated['log'] ?? '',
                'event' => $validated['event'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'urls' => [
                'index' => route('admin.system.activity-logs.index'),
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $activity = Activity::query()->with('causer:id,first_name,last_name')->findOrFail($id);
        $properties = $activity->properties?->toArray() ?? [];

        return Inertia::render('Admin/System/ActivityLog/Show', [
            'shell' => ShellProps::make($request->user(), 'Sistem', 'Detail Log Aktivitas'),
            'activity' => [
                'id' => $activity->id,
                'log' => $activity->log_name,
                'description' => $activity->description,
                'event' => $activity->event,
                'subject' => $activity->subject_type
                    ? class_basename($activity->subject_type).' #'.$activity->subject_id
                    : '-',
                'causer' => $this->causerName($activity),
                'createdAt' => $activity->created_at?->format('d/m/Y H:i:s'),
                'properties' => $properties,
                'prettyJson' => json_encode($properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
            'urls' => [
                'index' => route('admin.system.activity-logs.index'),
            ],
        ]);
    }

    private function causerName(Activity $activity): string
    {
        if (! $activity->causer) {
            return 'System';
        }

        $fullName = trim(($activity->causer->first_name ?? '').' '.($activity->causer->last_name ?? ''));

        return $fullName !== '' ? $fullName : 'User #'.$activity->causer_id;
    }

    private function preview(Activity $activity): string
    {
        $properties = $activity->properties?->toArray() ?? [];

        if (isset($properties['active_role'])) {
            return 'Role: '.$properties['active_role'];
        }

        if (isset($properties['attributes']['name'])) {
            return 'Target: '.$properties['attributes']['name'];
        }

        if (isset($properties['old']['name'])) {
            return 'Old: '.$properties['old']['name'];
        }

        if (isset($properties['ip'])) {
            return 'IP: '.$properties['ip'];
        }

        return ! empty($properties)
            ? str(json_encode($properties, JSON_UNESCAPED_UNICODE))->limit(80)->toString()
            : '-';
    }
}
