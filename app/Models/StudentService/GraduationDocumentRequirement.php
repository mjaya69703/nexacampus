<?php

namespace App\Models\StudentService;

use App\Models\Academic\StudyProgram;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GraduationDocumentRequirement extends Model
{
    protected $fillable = [
        'study_program_id',
        'document_type',
        'label',
        'is_required',
        'allowed_extensions',
        'max_size_kb',
        'sort_order',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'max_size_kb' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(GraduationDocument::class);
    }

    public function allowedExtensionsList(): array
    {
        return collect(explode(',', (string) $this->allowed_extensions))
            ->map(fn (string $extension) => strtolower(trim($extension)))
            ->filter()
            ->unique()
            ->values()
            ->all() ?: ['pdf', 'jpg', 'jpeg', 'png'];
    }
}
