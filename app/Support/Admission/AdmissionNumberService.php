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

        $lastNumber = AdmissionApplication::query()
            ->where('admission_period_id', $period->id)
            ->where('application_number', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->value('application_number');

        $next = 1;

        if ($lastNumber && preg_match('/-(\d+)$/', $lastNumber, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function token(): string
    {
        do {
            $token = Str::random(48);
        } while (AdmissionApplication::query()->where('access_token', $token)->exists());

        return $token;
    }
}
