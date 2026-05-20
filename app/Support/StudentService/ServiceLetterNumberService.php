<?php

namespace App\Support\StudentService;

use App\Models\StudentService\ServiceLetterRequest;

class ServiceLetterNumberService
{
    public function next(): string
    {
        $prefix = 'LTR-'.now()->format('Y');

        $last = ServiceLetterRequest::query()
            ->where('request_number', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->value('request_number');

        $sequence = $last
            ? ((int) str($last)->afterLast('-')->toString()) + 1
            : 1;

        return sprintf('%s-%05d', $prefix, $sequence);
    }
}
