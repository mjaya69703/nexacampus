<?php

namespace App\Models\Academic;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Assignment extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'course_offering_id',
        'created_by',
        'title',
        'description',
        'due_at',
        'due_date',
        'max_score',
        'allowed_file_types',
        'max_file_size_kb',
        'max_file_size_mb',
        'max_files',
        'allow_late_submission',
        'late_penalty_percentage',
        'allow_text_submission',
        'allow_file_submission',
        'allow_resubmission',
        'accept_late_submission',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'due_date' => 'datetime',
            'max_score' => 'decimal:2',
            'allowed_file_types' => 'array',
            'max_file_size_kb' => 'integer',
            'max_file_size_mb' => 'integer',
            'max_files' => 'integer',
            'allow_late_submission' => 'boolean',
            'late_penalty_percentage' => 'decimal:2',
            'allow_text_submission' => 'boolean',
            'allow_file_submission' => 'boolean',
            'allow_resubmission' => 'boolean',
            'accept_late_submission' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('assignment')
            ->logOnly(['course_offering_id', 'title', 'due_at', 'max_score', 'is_published'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(AssignmentFile::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(AssignmentStatusHistory::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where(function ($nested) {
                $nested->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function allowedExtensions(): array
    {
        return $this->allowed_file_types ?: ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];
    }

    public function formattedMaxFileSize(): string
    {
        return max(1, (int) ceil($this->max_file_size_kb / 1024)).' MB';
    }

    public function isPastDue(): bool
    {
        return $this->due_at?->isPast() ?? false;
    }

    public function canAcceptSubmission(): bool
    {
        return ! $this->isPastDue() || $this->accept_late_submission;
    }
}
