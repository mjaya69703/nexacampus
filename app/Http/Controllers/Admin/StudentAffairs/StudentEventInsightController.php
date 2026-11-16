<?php

namespace App\Http\Controllers\Admin\StudentAffairs;

use App\Enums\EventBudgetCategory;
use App\Enums\EventDocumentCategory;
use App\Http\Controllers\Controller;
use App\Models\StudentAffairs\StudentEvent;
use App\Models\StudentAffairs\StudentEventBudget;
use App\Models\StudentAffairs\StudentEventDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StudentEventInsightController extends Controller
{
    public function storeDocument(Request $request, int $id)
    {
        $event = StudentEvent::findOrFail($id);

        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(EventDocumentCategory::options()))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,zip'],
        ]);

        $file = $request->file('file');
        $path = $file->store('student-affairs/events/'.$event->id.'/documents', 'public');

        StudentEventDocument::create([
            'student_event_id' => $event->id,
            'category' => $validated['category'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Dokumentasi event berhasil diunggah.');
    }

    public function destroyDocument(Request $request, int $id, int $documentId)
    {
        $event = StudentEvent::findOrFail($id);
        $document = StudentEventDocument::where('student_event_id', $event->id)->findOrFail($documentId);

        Storage::disk('public')->delete($document->file_path);

        $document->update(['deleted_by' => $request->user()->id]);
        $document->delete();

        return back()->with('success', 'Dokumentasi event berhasil dihapus.');
    }

    public function downloadDocument(Request $request, int $id, int $documentId)
    {
        $document = StudentEventDocument::where('student_event_id', $id)->findOrFail($documentId);
        $disk = Storage::disk('public');

        abort_unless($disk->exists($document->file_path), 404);

        return $disk->download($document->file_path, $document->original_name);
    }

    public function storeBudget(Request $request, int $id)
    {
        $event = StudentEvent::findOrFail($id);
        $validated = $this->validateBudget($request);

        StudentEventBudget::create([
            ...$validated,
            'student_event_id' => $event->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Item anggaran berhasil ditambahkan.');
    }

    public function updateBudget(Request $request, int $id, int $budgetId)
    {
        $event = StudentEvent::findOrFail($id);
        $budget = StudentEventBudget::where('student_event_id', $event->id)->findOrFail($budgetId);

        $budget->update([
            ...$this->validateBudget($request),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Item anggaran berhasil diperbarui.');
    }

    public function destroyBudget(Request $request, int $id, int $budgetId)
    {
        $event = StudentEvent::findOrFail($id);
        $budget = StudentEventBudget::where('student_event_id', $event->id)->findOrFail($budgetId);

        $budget->update(['deleted_by' => $request->user()->id]);
        $budget->delete();

        return back()->with('success', 'Item anggaran berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBudget(Request $request): array
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(EventBudgetCategory::options()))],
            'planned_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'realized_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['realized_amount'] = (float) ($validated['realized_amount'] ?? 0);

        return $validated;
    }
}
