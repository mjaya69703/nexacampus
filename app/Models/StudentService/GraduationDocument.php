<?php

namespace App\Models\StudentService;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraduationDocument extends Model
{
    protected $fillable = [
        'graduation_application_id',
        'graduation_document_requirement_id',
        'document_type',
        'file_path',
        'file_name',
        'file_size',
        'verification_status',
        'verification_notes',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(GraduationApplication::class, 'graduation_application_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(GraduationDocumentRequirement::class, 'graduation_document_requirement_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isPdf(): bool
    {
        return strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION)) === 'pdf';
    }

    public function isImage(): bool
    {
        return in_array(strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }
}
