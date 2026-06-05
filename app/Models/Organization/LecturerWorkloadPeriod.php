<?php

namespace App\Models\Organization;

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LecturerWorkloadPeriod extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'minimum_sks' => 'decimal:2',
            'maximum_sks' => 'decimal:2',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(LecturerWorkloadSubmission::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open'
            && (! $this->starts_at || $this->starts_at->lte(today()))
            && (! $this->ends_at || $this->ends_at->gte(today()));
    }
}
