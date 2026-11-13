<?php

namespace App\Models\Academic;

use App\Enums\PlagiarismStatus;
use App\Enums\ThesisDefenseResult;
use App\Enums\ThesisDefenseStatus;
use App\Enums\ThesisDocumentCategory;
use App\Enums\ThesisMilestoneStatus;
use App\Enums\ThesisStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Thesis extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const TYPE_SKRIPSI = 'skripsi';

    public const TYPE_TUGAS_AKHIR = 'tugas_akhir';

    public const TYPE_LABELS = [
        self::TYPE_SKRIPSI => 'Skripsi',
        self::TYPE_TUGAS_AKHIR => 'Tugas Akhir',
    ];

    public const SUPERVISOR_ROLE_LABELS = [
        'primary' => 'Pembimbing Utama',
        'secondary' => 'Pembimbing Pendamping',
    ];

    public const EXAMINER_ROLE_LABELS = [
        'chair' => 'Ketua Penguji',
        'secretary' => 'Sekretaris Penguji',
        'examiner' => 'Penguji',
        'supervisor' => 'Pembimbing',
    ];

    protected $fillable = [
        'student_profile_id',
        'academic_year_id',
        'type',
        'title',
        'topic',
        'abstract',
        'keywords',
        'status',
        'proposal_file_path',
        'proposal_file_name',
        'proposal_submitted_at',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejection_reason',
        'final_score',
        'final_grade',
        'completed_at',
        'repository_published',
        'repository_published_at',
        'plagiarism_status',
        'plagiarism_score',
        'plagiarism_tool',
        'plagiarism_checked_at',
        'plagiarism_notes',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'proposal_submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'completed_at' => 'datetime',
            'repository_published_at' => 'datetime',
            'repository_published' => 'boolean',
            'plagiarism_checked_at' => 'datetime',
            'plagiarism_score' => 'decimal:2',
            'final_score' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('thesis')
            ->logOnly([
                'title', 'topic', 'type', 'status', 'final_score', 'final_grade',
                'repository_published', 'plagiarism_status', 'plagiarism_score',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function supervisors(): HasMany
    {
        return $this->hasMany(ThesisSupervisor::class);
    }

    public function activeSupervisors(): HasMany
    {
        return $this->supervisors()->where('is_active', true);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ThesisMilestone::class)->orderBy('sequence');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ThesisDocument::class)->latest();
    }

    public function defenses(): HasMany
    {
        return $this->hasMany(ThesisDefense::class)->latest('scheduled_at');
    }

    public function resultDefense(): HasOne
    {
        return $this->hasOne(ThesisDefense::class)
            ->where('type', 'result')
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ThesisStatus::activeValues());
    }

    public function scopeRepositoryPublished($query)
    {
        return $query->where('status', ThesisStatus::COMPLETED->value)
            ->where('repository_published', true);
    }

    public function statusEnum(): ThesisStatus
    {
        return ThesisStatus::tryFrom((string) $this->status) ?? ThesisStatus::DRAFT;
    }

    public function statusLabel(): string
    {
        return $this->statusEnum()->label();
    }

    public function statusTone(): string
    {
        return $this->statusEnum()->tone();
    }

    public function plagiarismStatusEnum(): PlagiarismStatus
    {
        return PlagiarismStatus::tryFrom((string) $this->plagiarism_status) ?? PlagiarismStatus::NOT_CHECKED;
    }

    public function plagiarismStatusLabel(): string
    {
        return $this->plagiarismStatusEnum()->label();
    }

    public function plagiarismStatusTone(): string
    {
        return $this->plagiarismStatusEnum()->tone();
    }

    public function isRepositoryPublished(): bool
    {
        return $this->status === ThesisStatus::COMPLETED->value && (bool) $this->repository_published;
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? ucfirst((string) $this->type);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ThesisStatus::activeValues(), true);
    }

    public function hasSupervisor(int $lecturerProfileId): bool
    {
        return $this->supervisors()
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->where('is_active', true)
            ->exists();
    }

    public function hasExaminer(int $lecturerProfileId): bool
    {
        return $this->defenses()
            ->whereHas('examiners', fn ($query) => $query->where('lecturer_profile_id', $lecturerProfileId))
            ->exists();
    }

    public function allMilestonesApproved(): bool
    {
        $milestones = $this->relationLoaded('milestones') ? $this->milestones : $this->milestones()->get();

        return $milestones->isNotEmpty()
            && $milestones->every(fn (ThesisMilestone $milestone) => $milestone->status === ThesisMilestoneStatus::APPROVED->value);
    }

    public function latestDefense(): ?ThesisDefense
    {
        return $this->defenses()->first();
    }

    public static function milestoneStatusLabel(string $status): string
    {
        return ThesisMilestoneStatus::tryFrom($status)?->label() ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function defenseStatusLabel(string $status): string
    {
        return ThesisDefenseStatus::tryFrom($status)?->label() ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function defenseResultLabel(?string $result): ?string
    {
        return $result ? (ThesisDefenseResult::tryFrom($result)?->label() ?? ucfirst(str_replace('_', ' ', $result))) : null;
    }

    public static function documentCategoryLabel(string $category): string
    {
        return ThesisDocumentCategory::tryFrom($category)?->label() ?? ucfirst(str_replace('_', ' ', $category));
    }
}
