<?php

namespace App\Support\Financial;

use App\Models\Financial\Payment;

class PaymentNumberService
{
    public function generate(): string
    {
        $prefix = 'PAY-'.now()->format('Ymd');
        $lastNumber = Payment::query()
            ->where('payment_number', 'like', $prefix.'-%')
            ->lockForUpdate()
            ->orderByDesc('payment_number')
            ->value('payment_number');

        $sequence = $lastNumber
            ? ((int) str($lastNumber)->afterLast('-')->toString()) + 1
            : 1;

        return $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
