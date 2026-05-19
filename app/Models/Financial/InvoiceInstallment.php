<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceInstallment extends Model
{
    protected $fillable = [
        'student_invoice_id',
        'invoice_installment_request_id',
        'installment_no',
        'amount',
        'fee_amount',
        'paid_amount',
        'due_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'installment_no' => 'integer',
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StudentInvoice::class, 'student_invoice_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(InvoiceInstallmentRequest::class, 'invoice_installment_request_id');
    }
}
