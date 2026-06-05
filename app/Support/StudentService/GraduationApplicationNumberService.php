<?php

namespace App\Support\StudentService;

use App\Models\StudentService\GraduationApplication;

class GraduationApplicationNumberService
{
    public function next(): string
    {
        do {
            $next = GraduationApplication::withTrashed()
                ->whereYear('created_at', now()->year)
                ->count() + 1;

            $number = sprintf('YDS-%s-%05d', now()->format('Y'), $next);
        } while (GraduationApplication::withTrashed()->where('application_number', $number)->exists());

        return $number;
    }
}
