<?php

namespace App\Models\StudentService;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentComplaintMessage extends Model
{
    protected $fillable = [
        'student_complaint_id',
        'user_id',
        'sender_type',
        'message',
        'is_internal_note',
    ];

    protected function casts(): array
    {
        return [
            'is_internal_note' => 'boolean',
        ];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(StudentComplaint::class, 'student_complaint_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(StudentComplaintAttachment::class);
    }
}
