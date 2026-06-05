<?php

namespace App\Support\StudentService;

use App\Models\StudentService\StudentTransferRequest;

class StudentTransferNumberService
{
    public function next(): string
    {
        $prefix = 'TRF-'.now()->format('Y').'-';
        $next = StudentTransferRequest::withTrashed()
            ->where('request_number', 'like', $prefix.'%')
            ->count() + 1;

        do {
            $number = $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $next++;
        } while (StudentTransferRequest::withTrashed()->where('request_number', $number)->exists());

        return $number;
    }
}
