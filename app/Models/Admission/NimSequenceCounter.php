<?php

namespace App\Models\Admission;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NimSequenceCounter extends Model
{
    protected $fillable = [
        'nim_generation_rule_id',
        'scope_key',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(NimGenerationRule::class, 'nim_generation_rule_id');
    }
}
