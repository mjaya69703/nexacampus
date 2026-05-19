<?php

namespace App\Models\Admission;

use App\Models\Academic\StudyProgram;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionDocumentRequirement extends Model
{
    protected $fillable = [
        'admission_period_id',
        'study_program_id',
        'document_type',
        'label',
        'is_required',
        'allowed_extensions',
        'max_size_kb',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'max_size_kb' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AdmissionPeriod::class, 'admission_period_id');
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }
}
