<?php

namespace App\Models\StudentService;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequestStatusHistory extends Model
{
    protected $fillable = [
        'service_letter_request_id',
        'from_status',
        'to_status',
        'notes',
        'changed_by',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceLetterRequest::class, 'service_letter_request_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
