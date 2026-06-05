<?php

namespace App\Models\StudentService;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ServiceLetterType extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'fulfillment_mode',
        'template_key',
        'requires_financial_clearance',
        'clearance_hold_type',
        'required_fields',
        'requires_attachment',
        'allowed_extensions',
        'max_file_size_kb',
        'signer_name',
        'signer_position',
        'signature_path',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'required_fields' => 'array',
            'requires_financial_clearance' => 'boolean',
            'requires_attachment' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('service_letter_type')
            ->logOnly(['name', 'code', 'fulfillment_mode', 'requires_financial_clearance', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceLetterRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function normalizedAllowedExtensions(): array
    {
        return collect(explode(',', (string) $this->allowed_extensions))
            ->map(fn (string $extension) => strtolower(trim($extension)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
