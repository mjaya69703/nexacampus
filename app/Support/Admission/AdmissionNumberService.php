<?php

namespace App\Support\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionPeriod;
use Illuminate\Support\Str;

class AdmissionNumberService
{
    public function generate(AdmissionPeriod $period): string
    {
        $prefix = $period->code ?: 'ADM'.$period->academic_year;

        $next = AdmissionApplication::withTrashed()
            ->where('admission_period_id', $period->id)
            ->where('application_number', 'like', $prefix.'-%')
            ->pluck('application_number')
            ->map(function (string $number): int {
                preg_match('/-(\d+)$/', $number, $matches);

                return (int) ($matches[1] ?? 0);
            })
            ->max() + 1;

        do {
            $applicationNumber = $prefix.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (
            AdmissionApplication::withTrashed()
                ->where('application_number', $applicationNumber)
                ->exists()
        );

        return $applicationNumber;
    }

    public function token(): string
    {
        do {
            $token = Str::random(48);
        } while (AdmissionApplication::withTrashed()->where('access_token', $token)->exists());

        return $token;
    }
}
