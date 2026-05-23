<?php

namespace App\Support\StudentService;

use App\Models\StudentService\StudentComplaint;

class StudentComplaintNumberService
{
    public function next(): string
    {
        $year = now()->format('Y');
        $prefix = "CMP-{$year}-";
        $last = StudentComplaint::query()
            ->where('ticket_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('ticket_number');

        $sequence = $last ? ((int) str($last)->afterLast('-')->toString()) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
