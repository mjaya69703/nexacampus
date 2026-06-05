<?php

namespace App\Models\StudentService;

use App\Models\Academic\StudentProfile;
use App\Models\Organization\WorkUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentComplaint extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ticket_number',
        'student_profile_id',
        'student_complaint_category_id',
        'assigned_work_unit_id',
        'assigned_user_id',
        'subject',
        'description',
        'priority',
        'status',
        'due_at',
        'last_message_at',
        'resolved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'last_message_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(StudentComplaintCategory::class, 'student_complaint_category_id');
    }

    public function assignedWorkUnit(): BelongsTo
    {
        return $this->belongsTo(WorkUnit::class, 'assigned_work_unit_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(StudentComplaintMessage::class)->orderBy('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(StudentComplaintAttachment::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(StudentComplaintStatusHistory::class)->orderBy('created_at');
    }
}
