<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevelopmentAttachment extends Model
{
    protected $guarded = ['id'];

    public function record(): BelongsTo
    {
        return $this->belongsTo(UserDevelopmentRecord::class, 'user_development_record_id');
    }
}
