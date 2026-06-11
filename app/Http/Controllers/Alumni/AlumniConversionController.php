<?php

namespace App\Http\Controllers\Alumni;

use App\Http\Controllers\Controller;
use App\Support\Alumni\AlumniConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AlumniConversionController extends Controller
{
    public function batchConvert(Request $request): RedirectResponse
    {
        $request->validate([
            'graduation_batch_id' => 'required|integer|exists:graduation_batches,id',
        ]);

        $service = app(AlumniConversionService::class);
        $result = $service->batchConvert((int) $request->input('graduation_batch_id'));

        $message = "{$result['converted']} alumni berhasil dikonversi.";

        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} dilewati.";
        }

        if (! empty($result['errors'])) {
            return redirect()
                ->route('admin.alumni.profiles.index')
                ->with('success', $message)
                ->with('error', implode(' ', $result['errors']));
        }

        return redirect()
            ->route('admin.alumni.profiles.index')
            ->with('success', $message);
    }
}
