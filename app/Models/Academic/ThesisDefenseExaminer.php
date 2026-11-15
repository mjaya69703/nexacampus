<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ThesisDefenseExaminer extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'thesis_defense_id',
        'lecturer_profile_id',
        'role',
        'score',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('thesis_defense_examiner')
            ->logOnly(['thesis_defense_id', 'lecturer_profile_id', 'role', 'score'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function defense(): BelongsTo
    {
        return $this->belongsTo(ThesisDefense::class, 'thesis_defense_id');
    }

    public function lecturerProfile(): BelongsTo
    {
        return $this->belongsTo(LecturerProfile::class);
    }

    public function roleLabel(): string
    {
        return Thesis::EXAMINER_ROLE_LABELS[$this->role] ?? ucfirst((string) $this->role);
    }
}
