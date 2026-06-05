<?php

namespace App\Support\StudentService;

use App\Models\StudentService\StudentLeaveApplication;

class StudentLeaveNumberService
{
    public function next(): string
    {
        $prefix = 'LEV-'.now()->format('Y');

        $last = StudentLeaveApplication::query()
            ->where('application_number', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->value('application_number');

        $sequence = $last ? ((int) str($last)->afterLast('-')->toString()) + 1 : 1;

        return sprintf('%s-%05d', $prefix, $sequence);
    }
}
