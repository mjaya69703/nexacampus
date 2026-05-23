<?php

namespace App\Models\StudentService;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentComplaintStatusHistory extends Model
{
    protected $fillable = [
        'student_complaint_id',
        'from_status',
        'to_status',
        'notes',
        'changed_by',
    ];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(StudentComplaint::class, 'student_complaint_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
