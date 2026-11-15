<?php

namespace App\Http\Controllers\Admin\StudentService;

use App\Http\Controllers\Controller;
use App\Models\StudentService\ServiceLetterType;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD jenis surat layanan mahasiswa — paritas halaman Livewire lama.
 */
class ServiceLetterTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'fulfillment_mode' => 'nullable|in:auto_generate,manual_upload,hybrid',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:name,code,fulfillment_mode,is_active,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = ServiceLetterType::query()->withCount('requests');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"));
        }

        if (filled($validated['fulfillment_mode'] ?? null)) {
            $query->where('fulfillment_mode', $validated['fulfillment_mode']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $types = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/StudentServices/LetterTypes/Index', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Jenis Surat'),
            'can' => [
                'create' => ActivePermission::check('service-letter-type.create'),
                'update' => ActivePermission::check('service-letter-type.update'),
                'delete' => ActivePermission::check('service-letter-type.delete'),
            ],
            'stats' => [
                'total' => ServiceLetterType::count(),
                'active' => ServiceLetterType::where('is_active', true)->count(),
                'autoGenerate' => ServiceLetterType::where('fulfillment_mode', 'auto_generate')->count(),
                'clearance' => ServiceLetterType::where('requires_financial_clearance', true)->count(),
            ],
            'data' => [
                'rows' => collect($types->items())->values()->map(fn (ServiceLetterType $type, int $i) => [
                    'id' => $type->id,
                    'no' => ($types->firstItem() ?? 0) + $i,
                    'name' => $type->name,
                    'code' => $type->code,
                    'fulfillmentMode' => $type->fulfillment_mode,
                    'fulfillmentLabel' => match ($type->fulfillment_mode) {
                        'auto_generate' => 'Auto Generate',
                        'manual_upload' => 'Unggah Manual',
                        'hybrid' => 'Hybrid',
                        default => Str::title(str_replace('_', ' ', (string) $type->fulfillment_mode)),
                    },
                    'requiresClearance' => (bool) $type->requires_financial_clearance,
                    'clearanceLabel' => $type->requires_financial_clearance
                        ? Str::title(str_replace('_', ' ', (string) ($type->clearance_hold_type ?: 'required')))
                        : 'Tanpa clearance',
                    'requestsCount' => (int) $type->requests_count,
                    'isActive' => (bool) $type->is_active,
                    'editUrl' => route('admin.student-services.letter-types.edit', $type->id),
                    'toggleUrl' => route('admin.student-services.letter-types.toggle', $type->id),
                    'deleteUrl' => route('admin.student-services.letter-types.destroy', $type->id),
                ])->all(),
                'currentPage' => $types->currentPage(),
                'lastPage' => $types->lastPage(),
                'perPage' => $types->perPage(),
                'total' => $types->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'fulfillment_mode' => $validated['fulfillment_mode'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'urls' => [
                'index' => route('admin.student-services.letter-types.index'),
                'create' => route('admin.student-services.letter-types.create'),
                'bulkDestroy' => route('admin.student-services.letter-types.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/StudentServices/LetterTypes/Form', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Tambah Jenis Surat'),
            'mode' => 'create',
            'letterType' => [
                'name' => '',
                'code' => '',
                'description' => '',
                'fulfillment_mode' => 'auto_generate',
                'template_key' => '',
                'requires_financial_clearance' => false,
                'clearance_hold_type' => '',
                'required_fields' => 'purpose',
                'requires_attachment' => false,
                'allowed_extensions' => 'pdf,jpg,jpeg,png',
                'max_file_size_kb' => 2048,
                'signer_name' => '',
                'signer_position' => '',
                'is_active' => true,
            ],
            'fulfillmentModes' => [
                ['id' => 'auto_generate', 'name' => 'Auto Generate (sistem cetak PDF)'],
                ['id' => 'manual_upload', 'name' => 'Unggah Manual'],
                ['id' => 'hybrid', 'name' => 'Hybrid'],
            ],
            'clearanceTypes' => [
                ['id' => 'registration', 'name' => 'Registrasi'],
                ['id' => 'study_plan', 'name' => 'KRS'],
                ['id' => 'exam_card', 'name' => 'Kartu Ujian'],
                ['id' => 'transcript', 'name' => 'Transkrip'],
                ['id' => 'graduation', 'name' => 'Yudisium'],
            ],
            'urls' => [
                'index' => route('admin.student-services.letter-types.index'),
                'submit' => route('admin.student-services.letter-types.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        ServiceLetterType::create([
            ...$validated,
            'required_fields' => $this->requiredFieldsArray($validated['required_fields'] ?? null),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.student-services.letter-types.index')
            ->with('success', 'Jenis surat berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $letterType = ServiceLetterType::findOrFail($id);

        return Inertia::render('Admin/StudentServices/LetterTypes/Form', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Edit Jenis Surat'),
            'mode' => 'edit',
            'letterType' => [
                'id' => $letterType->id,
                'name' => $letterType->name,
                'code' => $letterType->code,
                'description' => $letterType->description ?? '',
                'fulfillment_mode' => $letterType->fulfillment_mode,
                'template_key' => $letterType->template_key ?? '',
                'requires_financial_clearance' => (bool) $letterType->requires_financial_clearance,
                'clearance_hold_type' => $letterType->clearance_hold_type ?? '',
                'required_fields' => implode(', ', $letterType->required_fields ?? []),
                'requires_attachment' => (bool) $letterType->requires_attachment,
                'allowed_extensions' => $letterType->allowed_extensions ?? '',
                'max_file_size_kb' => $letterType->max_file_size_kb,
                'signer_name' => $letterType->signer_name ?? '',
                'signer_position' => $letterType->signer_position ?? '',
                'is_active' => (bool) $letterType->is_active,
            ],
            'fulfillmentModes' => [
                ['id' => 'auto_generate', 'name' => 'Auto Generate (sistem cetak PDF)'],
                ['id' => 'manual_upload', 'name' => 'Unggah Manual'],
                ['id' => 'hybrid', 'name' => 'Hybrid'],
            ],
            'clearanceTypes' => [
                ['id' => 'registration', 'name' => 'Registrasi'],
                ['id' => 'study_plan', 'name' => 'KRS'],
                ['id' => 'exam_card', 'name' => 'Kartu Ujian'],
                ['id' => 'transcript', 'name' => 'Transkrip'],
                ['id' => 'graduation', 'name' => 'Yudisium'],
            ],
            'urls' => [
                'index' => route('admin.student-services.letter-types.index'),
                'submit' => route('admin.student-services.letter-types.update', $letterType->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $letterType = ServiceLetterType::findOrFail($id);
        $validated = $this->validatePayload($request, $letterType);

        $letterType->update([
            ...$validated,
            'required_fields' => $this->requiredFieldsArray($validated['required_fields'] ?? null),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.student-services.letter-types.index')
            ->with('success', 'Jenis surat berhasil diperbarui.');
    }

    public function toggle(Request $request, int $id)
    {
        $letterType = ServiceLetterType::findOrFail($id);
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $letterType->update([
            'is_active' => (bool) $validated['is_active'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status jenis surat berhasil diperbarui.');
    }

    public function destroy(int $id)
    {
        $letterType = ServiceLetterType::findOrFail($id);
        $name = $letterType->name;
        $letterType->delete();

        return redirect()->route('admin.student-services.letter-types.index')
            ->with('success', 'Jenis surat "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:service_letter_types,id',
        ]);

        $count = ServiceLetterType::whereIn('id', $validated['ids'])->count();
        ServiceLetterType::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count.' jenis surat berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?ServiceLetterType $letterType = null): array
    {
        $request->merge([
            'code' => str((string) $request->input('code'))->upper()->replace(' ', '_')->toString(),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80', Rule::unique('service_letter_types', 'code')->ignore($letterType?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'fulfillment_mode' => ['required', Rule::in(['auto_generate', 'manual_upload', 'hybrid'])],
            'template_key' => ['nullable', 'string', 'max:120'],
            'requires_financial_clearance' => ['boolean'],
            'clearance_hold_type' => ['nullable', Rule::in(['registration', 'study_plan', 'exam_card', 'transcript', 'graduation'])],
            'required_fields' => ['nullable', 'string', 'max:500'],
            'requires_attachment' => ['boolean'],
            'allowed_extensions' => ['nullable', 'string', 'max:255'],
            'max_file_size_kb' => ['nullable', 'integer', 'min:1', 'max:20480'],
            'signer_name' => ['nullable', 'string', 'max:255'],
            'signer_position' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $validated['requires_financial_clearance'] = (bool) ($validated['requires_financial_clearance'] ?? false);
        $validated['requires_attachment'] = (bool) ($validated['requires_attachment'] ?? false);
        $validated['is_active'] = (bool) $validated['is_active'];
        $validated['clearance_hold_type'] = ($validated['clearance_hold_type'] ?? null) ?: null;

        return $validated;
    }

    /**
     * @return array<int, string>
     */
    private function requiredFieldsArray(?string $fields): array
    {
        return collect(explode(',', (string) $fields))
            ->map(fn ($field) => trim($field))
            ->filter()
            ->values()
            ->all();
    }
}
