<?php

namespace App\Models\StudentAffairs;

use App\Enums\MemberPosition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentOrganizationMember extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'student_organization_id',
        'user_id',
        'position',
        'period',
        'joined_at',
        'left_at',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'position' => MemberPosition::class,
            'joined_at' => 'date',
            'left_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('student_organization_member')
            ->logOnly(['student_organization_id', 'user_id', 'position', 'period', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(StudentOrganization::class, 'student_organization_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
