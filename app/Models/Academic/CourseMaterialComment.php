<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Academic\CourseMaterial;


class CourseMaterialComment extends Model
{
    protected $fillable = [
        'course_material_id',
        'user_id',
        'parent_id',
        'content',
        'likes_count',
        'is_edited',
        'is_deleted',
    ];
    
    protected $casts = [
        'likes_count' => 'integer',
        'is_edited' => 'boolean',
        'is_deleted' => 'boolean',
    ];
    
    public function courseMaterial(): BelongsTo
    {
        return $this->belongsTo(CourseMaterial::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
    
    public function parent(): BelongsTo
    {
        return $this->belongsTo(CourseMaterialComment::class, 'parent_id');
    }
    
    public function replies(): HasMany
    {
        return $this->hasMany(CourseMaterialComment::class, 'parent_id')
            ->orderBy('created_at', 'asc');
    }
    
    public function likes(): HasMany
    {
        return $this->hasMany(\App\Models\Academic\CommentLike::class, 'comment_id');
    }
    
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }
    
    public function getMentionedUsersAttribute(): array
    {
        // Parse @username from content
        preg_match_all('/@([\w.]+)/', $this->content, $matches);
        return $matches[1] ?? [];
    }
}
