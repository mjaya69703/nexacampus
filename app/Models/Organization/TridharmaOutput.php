<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TridharmaOutput extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'published_at' => 'date',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(TridharmaRecord::class, 'tridharma_record_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(TridharmaAttachment::class, 'attachable');
    }
}
