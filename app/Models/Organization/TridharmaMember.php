<?php

namespace App\Models\Organization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TridharmaMember extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_external' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(TridharmaRecord::class, 'tridharma_record_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
