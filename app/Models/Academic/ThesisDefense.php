<?php

namespace App\Models\Academic;

use App\Enums\ThesisDefenseResult;
use App\Enums\ThesisDefenseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ThesisDefense extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const TYPE_LABELS = [
        'proposal' => 'Seminar Proposal',
        'result' => 'Sidang Hasil',
    ];

    protected $fillable = [
        'thesis_id',
        'type',
        'scheduled_at',
        'duration_minutes',
        'room',
        'meeting_link',
        'status',
        'result',
        'score',
        'grade',
        'notes',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_minutes' => 'integer',
            'score' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('thesis_defense')
            ->logOnly(['thesis_id', 'type', 'scheduled_at', 'status', 'result', 'score', 'grade'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class);
    }

    public function examiners(): HasMany
    {
        return $this->hasMany(ThesisDefenseExaminer::class);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? ucfirst((string) $this->type);
    }

    public function statusEnum(): ThesisDefenseStatus
    {
        return ThesisDefenseStatus::tryFrom((string) $this->status) ?? ThesisDefenseStatus::SCHEDULED;
    }

    public function statusLabel(): string
    {
        return $this->statusEnum()->label();
    }

    public function statusTone(): string
    {
        return $this->statusEnum()->tone();
    }

    public function resultEnum(): ?ThesisDefenseResult
    {
        return $this->result ? ThesisDefenseResult::tryFrom((string) $this->result) : null;
    }

    public function resultLabel(): ?string
    {
        return $this->resultEnum()?->label();
    }

    public function averageExaminerScore(): ?float
    {
        $scores = $this->examiners
            ->pluck('score')
            ->filter(fn ($score) => $score !== null)
            ->map(fn ($score) => (float) $score);

        return $scores->isEmpty() ? null : round($scores->avg(), 2);
    }
}
