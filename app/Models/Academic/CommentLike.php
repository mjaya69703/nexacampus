<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentLike extends Model
{
    protected $table = 'comment_likes';
    
    protected $fillable = [
        'comment_id',
        'user_id',
    ];
    
    public function comment(): BelongsTo
    {
        return $this->belongsTo(CourseMaterialComment::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
