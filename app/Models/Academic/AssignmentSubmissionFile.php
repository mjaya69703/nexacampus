<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmissionFile extends Model
{
    protected $fillable = [
        'assignment_submission_id',
        'submission_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'original_name',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'assignment_submission_id');
    }

    public function getFormattedFileSizeAttribute(): string
    {
        return AssignmentFile::formatBytes($this->file_size);
    }
}
