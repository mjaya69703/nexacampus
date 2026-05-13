<?php

namespace App\Models\Financial;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentInvoice extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'student_profile_id',
        'academic_year_id',
        'semester',
        'invoice_type',
        'source_type',
        'source_id',
        'total_amount',
        'paid_amount',
        'outstanding_amount',
        'status',
        'due_date',
        'paid_at',
        'issued_at',
        'issued_by',
        'cancelled_at',
        'cancelled_by',
        'attachment_path',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('student_invoice')
            ->logOnly(['invoice_number', 'student_profile_id', 'academic_year_id', 'semester', 'invoice_type', 'total_amount', 'paid_amount', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function installmentRequests(): HasMany
    {
        return $this->hasMany(InvoiceInstallmentRequest::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(InvoiceInstallment::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isEditable(): bool
    {
        return (float) $this->paid_amount <= 0
            && ! $this->payments()->exists()
            && ! in_array($this->status, ['paid', 'partially_paid', 'cancelled'], true);
    }

    public function isVisibleToStudent(): bool
    {
        return $this->status !== 'draft';
    }

    public function hasApprovedInstallments(): bool
    {
        return $this->installments()->exists();
    }
}
