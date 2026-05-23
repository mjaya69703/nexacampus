<?php

namespace App\Models\StudentService;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentComplaintAttachment extends Model
{
    protected $fillable = [
        'student_complaint_id',
        'student_complaint_message_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
    ];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(StudentComplaint::class, 'student_complaint_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(StudentComplaintMessage::class, 'student_complaint_message_id');
    }
}
