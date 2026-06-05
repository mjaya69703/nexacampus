<?php

namespace App\Models\StudentService;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTransferStatusHistory extends Model
{
    protected $fillable = [
        'student_transfer_request_id',
        'from_status',
        'to_status',
        'notes',
        'changed_by',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(StudentTransferRequest::class, 'student_transfer_request_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
