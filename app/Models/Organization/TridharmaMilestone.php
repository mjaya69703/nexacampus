<?php

namespace App\Models\Organization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TridharmaMilestone extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'progress_percentage' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(TridharmaRecord::class, 'tridharma_record_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
