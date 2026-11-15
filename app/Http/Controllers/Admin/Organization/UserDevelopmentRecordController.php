<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\UserDevelopmentAttachment;
use App\Models\Organization\UserDevelopmentRecord;
use App\Models\User;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD riwayat sertifikasi & pelatihan pegawai — paritas halaman Livewire lama,
 * termasuk unggah berkas sertifikat privat dan aksi verifikasi asesor.
 */
class UserDevelopmentRecordController extends Controller
{
    private const TYPES = [
        'certification' => 'Sertifikasi Profesi & Kompetensi',
        'training' => 'Pelatihan & Kursus',
        'workshop' => 'Workshop & Lokakarya',
        'seminar' => 'Seminar & Konferensi',
        'award' => 'Penghargaan / Prestasi',
        'license' => 'Lisensi & Izin Praktik Kerja',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'type' => 'nullable|in:certification,training,workshop,seminar,award,license',
            'is_verified' => 'nullable|in:0,1',
            'sort' => 'nullable|in:title,type,organizer,start_date,attachments_count,is_verified,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = UserDevelopmentRecord::query()
            ->with(['user', 'verifier'])
            ->withCount('attachments');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('organizer', 'like', "%{$q}%")
                    ->orWhere('credential_number', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($user) => $user
                        ->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('username', 'like', "%{$q}%"));
            });
        }

        if (filled($validated['type'] ?? null)) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['is_verified']) && $validated['is_verified'] !== '') {
            $query->where('is_verified', (bool) $validated['is_verified']);
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        if ($sort !== 'created_at') {
            $query->orderByDesc('created_at');
        }

        $perPage = (int) ($validated['perPage'] ?? 10);
        $records = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Organization/UserDevelopmentRecords/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Sertifikasi & Pelatihan'),
            'can' => [
                'create' => ActivePermission::check('user-development-record.create'),
                'update' => ActivePermission::check('user-development-record.update'),
                'delete' => ActivePermission::check('user-development-record.delete'),
            ],
            'stats' => [
                'total' => UserDevelopmentRecord::count(),
                'verified' => UserDevelopmentRecord::where('is_verified', true)->count(),
                'pending' => UserDevelopmentRecord::where('is_verified', false)->count(),
            ],
            'data' => [
                'rows' => collect($records->items())->values()->map(fn (UserDevelopmentRecord $record, int $i) => [
                    'id' => $record->id,
                    'no' => ($records->firstItem() ?? 0) + $i,
                    'userName' => $record->user?->name ?? '-',
                    'userEmail' => $record->user?->email,
                    'type' => $record->type,
                    'typeLabel' => self::TYPES[$record->type] ?? ucfirst((string) $record->type),
                    'title' => $record->title,
                    'organizer' => $record->organizer,
                    'credentialNumber' => $record->credential_number,
                    'startDate' => $record->start_date?->format('d M Y'),
                    'endDate' => $record->end_date?->format('d M Y'),
                    'expiresAt' => $record->expires_at?->format('d M Y'),
                    'cost' => $record->cost === null ? null : (float) $record->cost,
                    'attachmentsCount' => (int) $record->attachments_count,
                    'isVerified' => (bool) $record->is_verified,
                    'verifierName' => $record->verifier?->name,
                    'verifiedAt' => $record->verified_at?->format('d M Y H:i'),
                    'verificationNotes' => $record->verification_notes,
                    'createdAt' => $record->created_at?->format('d M Y'),
                    'editUrl' => route('admin.organization.user-development-records.edit', $record->id),
                    'verifyUrl' => route('admin.organization.user-development-records.verify', $record->id),
                    'unverifyUrl' => route('admin.organization.user-development-records.unverify', $record->id),
                    'deleteUrl' => route('admin.organization.user-development-records.destroy', $record->id),
                ])->all(),
                'currentPage' => $records->currentPage(),
                'lastPage' => $records->lastPage(),
                'perPage' => $records->perPage(),
                'total' => $records->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'type' => $validated['type'] ?? '',
                'is_verified' => $validated['is_verified'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'types' => $this->optionList(self::TYPES),
            'urls' => [
                'index' => route('admin.organization.user-development-records.index'),
                'create' => route('admin.organization.user-development-records.create'),
                'bulkDestroy' => route('admin.organization.user-development-records.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Organization/UserDevelopmentRecords/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Tambah Sertifikasi & Pelatihan'),
            'mode' => 'create',
            'record' => [
                'type' => 'certification',
                'title' => '',
                'organizer' => '',
                'credential_number' => '',
                'start_date' => '',
                'end_date' => '',
                'expires_at' => '',
                'cost' => '',
                'description' => '',
                'is_verified' => false,
                'verification_notes' => '',
            ],
            'user' => null,
            'attachments' => [],
            'types' => $this->optionList(self::TYPES),
            'urls' => [
                'index' => route('admin.organization.user-development-records.index'),
                'submit' => route('admin.organization.user-development-records.store'),
                'searchUsers' => route('admin.organization.user-development-records.search-users'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request, true);

        $record = DB::transaction(function () use ($request, $validated) {
            $isVerified = (bool) $validated['is_verified'];

            $record = UserDevelopmentRecord::create([
                'user_id' => $validated['user_id'],
                'type' => $validated['type'],
                'title' => $validated['title'],
                'organizer' => $validated['organizer'],
                'credential_number' => $validated['credential_number'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'expires_at' => $validated['expires_at'],
                'cost' => $validated['cost'],
                'description' => $validated['description'],
                'is_verified' => $isVerified,
                'verified_by' => $isVerified ? $request->user()->id : null,
                'verified_at' => $isVerified ? now() : null,
                'verification_notes' => $validated['verification_notes'],
            ]);

            $this->storeAttachment($request, $record);

            return $record;
        });

        return redirect()->route('admin.organization.user-development-records.index')
            ->with('success', 'Riwayat sertifikasi/pelatihan "'.$record->title.'" berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $record = UserDevelopmentRecord::with(['user', 'attachments'])->findOrFail($id);

        return Inertia::render('Admin/Organization/UserDevelopmentRecords/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Edit Sertifikasi & Pelatihan'),
            'mode' => 'edit',
            'record' => [
                'id' => $record->id,
                'type' => $record->type,
                'title' => $record->title,
                'organizer' => $record->organizer ?? '',
                'credential_number' => $record->credential_number ?? '',
                'start_date' => $record->start_date?->format('Y-m-d') ?? '',
                'end_date' => $record->end_date?->format('Y-m-d') ?? '',
                'expires_at' => $record->expires_at?->format('Y-m-d') ?? '',
                'cost' => $record->cost === null ? '' : (float) $record->cost,
                'description' => $record->description ?? '',
                'is_verified' => (bool) $record->is_verified,
                'verification_notes' => $record->verification_notes ?? '',
            ],
            'user' => $record->user ? [
                'id' => $record->user->id,
                'name' => $record->user->name,
                'email' => $record->user->email,
                'username' => $record->user->username,
                'code' => $record->user->code,
            ] : null,
            'attachments' => $record->attachments->map(fn (UserDevelopmentAttachment $attachment) => [
                'id' => $attachment->id,
                'fileName' => $attachment->file_name,
                'fileSize' => (int) $attachment->file_size,
                'previewUrl' => route('admin.organization.user-development-records.attachments.preview', $attachment->id),
            ])->values()->all(),
            'types' => $this->optionList(self::TYPES),
            'urls' => [
                'index' => route('admin.organization.user-development-records.index'),
                'submit' => route('admin.organization.user-development-records.update', $record->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $record = UserDevelopmentRecord::findOrFail($id);
        $validated = $this->validatePayload($request, false);

        DB::transaction(function () use ($request, $record, $validated) {
            $isVerified = (bool) $validated['is_verified'];
            $wasVerified = (bool) $record->is_verified;

            $record->update([
                'type' => $validated['type'],
                'title' => $validated['title'],
                'organizer' => $validated['organizer'],
                'credential_number' => $validated['credential_number'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'expires_at' => $validated['expires_at'],
                'cost' => $validated['cost'],
                'description' => $validated['description'],
                'is_verified' => $isVerified,
                'verified_by' => $isVerified ? ($wasVerified ? $record->verified_by : $request->user()->id) : null,
                'verified_at' => $isVerified ? ($wasVerified ? $record->verified_at : now()) : null,
                'verification_notes' => $validated['verification_notes'],
            ]);

            $this->storeAttachment($request, $record);
        });

        return redirect()->route('admin.organization.user-development-records.index')
            ->with('success', 'Riwayat sertifikasi/pelatihan berhasil diperbarui.');
    }

    public function verify(Request $request, int $id)
    {
        $record = UserDevelopmentRecord::findOrFail($id);

        $record->update([
            'is_verified' => true,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        return back()->with('success', 'Riwayat "'.$record->title.'" berhasil diverifikasi.');
    }

    public function unverify(Request $request, int $id)
    {
        $record = UserDevelopmentRecord::findOrFail($id);

        $record->update([
            'is_verified' => false,
            'verified_by' => null,
            'verified_at' => null,
        ]);

        return back()->with('success', 'Status riwayat "'.$record->title.'" dikembalikan ke menunggu verifikasi.');
    }

    public function destroy(Request $request, int $id)
    {
        $record = UserDevelopmentRecord::findOrFail($id);
        $record->delete();

        return redirect()->route('admin.organization.user-development-records.index')
            ->with('success', 'Riwayat pengembangan "'.$record->title.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:user_development_records,id',
        ]);

        $records = UserDevelopmentRecord::whereIn('id', $validated['ids'])->get();

        foreach ($records as $record) {
            $record->delete();
        }

        return back()->with('success', $records->count().' riwayat sertifikasi/pelatihan berhasil dihapus.');
    }

    /**
     * Endpoint pencarian pegawai untuk picker pemilik riwayat (server-side).
     */
    public function searchUsers(Request $request)
    {
        $validated = $request->validate(['q' => 'nullable|string|max:100']);
        $q = $validated['q'] ?? '';

        $options = User::query()
            ->where('is_active', true)
            ->when(filled($q), function ($query) use ($q) {
                $search = '%'.$q.'%';

                $query->where(function ($sub) use ($search) {
                    $sub->where('first_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('username', 'like', $search)
                        ->orWhere('code', 'like', $search)
                        ->orWhere('identity_number', 'like', $search);
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(12)
            ->get(['id', 'first_name', 'last_name', 'email', 'username', 'code', 'identity_number'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'label' => $user->name.' ('.$user->email.')',
                'meta' => [
                    'email' => $user->email,
                    'username' => $user->username,
                    'code' => $user->code,
                ],
            ])
            ->values()
            ->all();

        return response()->json(['options' => $options]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $withUser): array
    {
        $rules = [
            'type' => 'required|in:certification,training,workshop,seminar,award,license',
            'title' => 'required|string|max:255',
            'organizer' => 'nullable|string|max:255',
            'credential_number' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'expires_at' => 'nullable|date|after:start_date',
            'cost' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:5000',
            'is_verified' => 'boolean',
            'verification_notes' => 'nullable|string|max:5000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
        ];

        if ($withUser) {
            $rules['user_id'] = 'required|integer|exists:users,id';
        }

        $validated = $request->validate($rules);

        $validated['organizer'] = ($validated['organizer'] ?? null) ?: null;
        $validated['credential_number'] = ($validated['credential_number'] ?? null) ?: null;
        $validated['end_date'] = ($validated['end_date'] ?? null) ?: null;
        $validated['expires_at'] = ($validated['expires_at'] ?? null) ?: null;
        $validated['cost'] = filled($validated['cost'] ?? null) ? (float) $validated['cost'] : null;
        $validated['description'] = ($validated['description'] ?? null) ?: null;
        $validated['is_verified'] = (bool) ($validated['is_verified'] ?? false);
        $validated['verification_notes'] = ($validated['verification_notes'] ?? null) ?: null;

        if ($withUser) {
            $validated['user_id'] = (int) $validated['user_id'];
        }

        return $validated;
    }

    private function storeAttachment(Request $request, UserDevelopmentRecord $record): void
    {
        if (! $request->hasFile('attachment')) {
            return;
        }

        $file = $request->file('attachment');
        $filename = 'development_'.$record->user_id.'_'.time().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('private/user-developments', $filename);

        $record->attachments()->create([
            'document_type' => 'certificate',
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
        ]);
    }

    /**
     * @param  array<string, string>  $options
     * @return array<int, array{id: string, label: string}>
     */
    private function optionList(array $options): array
    {
        return Collection::make($options)
            ->map(fn (string $label, string $id) => ['id' => $id, 'label' => $label])
            ->values()
            ->all();
    }
}
