<?php

namespace App\Models\Publication;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GalleryAlbum extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'gallery_albums';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'cover_image_path',
        'category_id',
        'is_published',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('gallery_album')
            ->logOnly(['title', 'slug', 'is_published'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PublicationCategory::class, 'category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(GalleryImage::class, 'gallery_album_id')->orderBy('sort_order');
    }

    public function scopePublished($q)
    {
        return $q->where('is_published', true);
    }
}
