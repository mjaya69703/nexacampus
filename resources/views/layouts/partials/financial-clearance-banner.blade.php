@php
    $financialClearanceSummary = null;
    $financialStudentProfile = auth()->user()?->studentProfile;

    $isStudentArea = request()->routeIs('student.*') || ($activeRole ?? null) === 'student';

    if ($financialStudentProfile && $isStudentArea) {
        $financialClearanceSummary = app(\App\Support\Financial\FinancialClearanceService::class)->evaluate($financialStudentProfile);
    }
@endphp

@if($financialClearanceSummary && $financialClearanceSummary['has_warning'])
    <div class="mb-3">
        <div class="alert {{ $financialClearanceSummary['has_blocking_hold'] ? 'alert-danger' : 'alert-warning' }} mb-0">
            <div class="d-flex gap-3 align-items-start">
                <div class="mt-1">
                    <i class="fas {{ $financialClearanceSummary['has_blocking_hold'] ? 'fa-lock' : 'fa-triangle-exclamation' }}"></i>
                </div>
                <div class="flex-fill">
                    <div class="fw-bold">
                        {{ $financialClearanceSummary['has_blocking_hold'] ? 'Akses akademik tertentu sedang ditahan' : 'Ada tagihan overdue yang perlu diselesaikan' }}
                    </div>
                    <div class="small">
                        @if($financialClearanceSummary['has_blocking_hold'])
                            Beberapa workflow akademik seperti registrasi atau KRS dapat diblok sampai pembayaran diverifikasi atau finance memberikan dispensasi.
                        @else
                            Kamu masih dalam masa tenggang. Selesaikan pembayaran sebelum hold berubah menjadi blocking.
                        @endif
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @foreach($financialClearanceSummary['holds']->take(3) as $hold)
                            <span class="badge {{ $hold->isBlocking() ? 'bg-red-lt text-red' : 'bg-yellow-lt text-yellow' }}">
                                {{ str($hold->hold_type)->replace('_', ' ')->title() }} / {{ $hold->invoice?->invoice_number ?? 'Invoice' }}
                            </span>
                        @endforeach
                        <a href="{{ route('student.financial.invoices') }}" class="btn btn-sm {{ $financialClearanceSummary['has_blocking_hold'] ? 'btn-danger' : 'btn-warning' }}">
                            Lihat Tagihan
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
