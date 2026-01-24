<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bill extends Model
{
    protected $fillable = [
        'class_id',
        'student_id',
        'teacher_id',
        'duration',
        'amount',
        'currency',
        'status',
        'bill_date',
        'payment_date',
        'payment_method',
    ];

    protected $casts = [
        'duration' => 'integer',
        'amount' => 'decimal:2',
        'bill_date' => 'date',
        'payment_date' => 'date',
        'status' => 'string',
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
}
