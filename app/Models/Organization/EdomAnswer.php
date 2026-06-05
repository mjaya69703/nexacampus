<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdomAnswer extends Model
{
    protected $guarded = ['id'];

    public function response(): BelongsTo
    {
        return $this->belongsTo(EdomResponse::class, 'edom_response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(EdomQuestion::class, 'edom_question_id');
    }
}
