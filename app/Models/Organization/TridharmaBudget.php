<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TridharmaBudget extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'planned_amount' => 'decimal:2',
            'realized_amount' => 'decimal:2',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(TridharmaRecord::class, 'tridharma_record_id');
    }
}
