<?php

namespace App\Models\StudentAffairs;

use App\Enums\AchievementCategory;
use App\Enums\AchievementLevel;
use App\Enums\AchievementRank;
use App\Enums\AchievementStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentAchievement extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'user_id',
        'student_organization_id',
        'title',
        'category',
        'level',
        'rank',
        'achieved_at',
        'organizer',
        'description',
        'certificate_path',
        'certificate_original_name',
        'status',
        'is_featured',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => AchievementCategory::class,
            'level' => AchievementLevel::class,
            'rank' => AchievementRank::class,
            'status' => AchievementStatus::class,
            'achieved_at' => 'date',
            'verified_at' => 'datetime',
            'is_featured' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('student_achievement')
            ->logOnly(['user_id', 'title', 'category', 'level', 'rank', 'status', 'is_featured'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(StudentOrganization::class, 'student_organization_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
