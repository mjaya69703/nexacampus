<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LecturerWorkloadItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sks' => 'decimal:2',
            'snapshot' => 'array',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(LecturerWorkloadSubmission::class, 'lecturer_workload_submission_id');
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }
}
