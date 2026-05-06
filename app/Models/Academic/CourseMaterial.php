<?php

namespace App\Models\Academic;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CourseMaterial extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'course_materials';

    protected $fillable = [
        'course_offering_id',
        'uploaded_by',
        'title',
        'description',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'category',
        'meeting_number',
        'is_published',
        'download_count',
        'last_accessed_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'last_accessed_at' => 'datetime',
            'file_size' => 'integer',
            'download_count' => 'integer',
            'meeting_number' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('course_material')
            ->logOnly(['title', 'file_name', 'file_type', 'category', 'meeting_number', 'is_published'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(CourseMaterialDownload::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(CourseMaterialBookmark::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(CourseMaterialFile::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function getFormattedFileSizeAttribute(): string
    {
        if (! $this->file_size) {
            return '-';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
