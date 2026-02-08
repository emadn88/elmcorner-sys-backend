<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bill extends Model
{
    protected $fillable = [
        'class_id',
        'package_id',
        'student_id',
        'teacher_id',
        'duration',
        'total_hours',
        'amount',
        'currency',
        'status',
        'bill_date',
        'payment_date',
        'payment_method',
        'payment_reason',
        'paypal_transaction_id',
        'payment_token',
        'is_custom',
        'sent_at',
        'class_ids',
        'description',
    ];

    protected $casts = [
        'duration' => 'integer',
        'total_hours' => 'decimal:2',
        'amount' => 'decimal:2',
        'bill_date' => 'date',
        'payment_date' => 'date',
        'status' => 'string',
        'is_custom' => 'boolean',
        'sent_at' => 'datetime',
        'class_ids' => 'array',
    ];

    /**
     * Get the class for the bill.
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassInstance::class, 'class_id');
    }

    /**
     * Get the student for the bill.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the teacher for the bill.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the package for the bill.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
