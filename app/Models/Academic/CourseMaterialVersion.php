<?php

namespace App\Models\Academic;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseMaterialVersion extends Model
{
    protected $fillable = [
        'course_material_id',
        'version_number',
        'change_type',
        'title',
        'description',
        'category',
        'meeting_number',
        'is_published',
        'files_snapshot',
        'change_summary',
        'created_by',
    ];

    protected $casts = [
        'files_snapshot' => 'array',
        'is_published' => 'boolean',
        'meeting_number' => 'integer',
        'version_number' => 'integer',
    ];

    public function courseMaterial(): BelongsTo
    {
        return $this->belongsTo(CourseMaterial::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
