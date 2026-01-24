<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'student_id',
        'start_date',
        'total_classes',
        'remaining_classes',
        'total_hours',
        'remaining_hours',
        'hour_price',
        'currency',
        'round_number',
        'status',
        'last_notification_sent',
        'notification_count',
    ];

    protected $casts = [
        'start_date' => 'date',
        'total_classes' => 'integer',
        'remaining_classes' => 'integer',
        'total_hours' => 'decimal:2',
        'remaining_hours' => 'decimal:2',
        'hour_price' => 'decimal:2',
        'round_number' => 'integer',
        'status' => 'string',
        'last_notification_sent' => 'datetime',
        'notification_count' => 'integer',
    ];

    /**
     * Get the student that owns the package.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the classes for the package.
     */
    public function classes(): HasMany
    {
        return $this->hasMany(ClassInstance::class);
    }

    /**
     * Check if package is finished.
     */
    public function getIsFinishedAttribute(): bool
    {
        // Package is finished if remaining hours or remaining classes are <= 0
        if ($this->remaining_hours !== null) {
            return $this->remaining_hours <= 0;
        }
        return $this->remaining_classes <= 0;
    }

    /**
     * Get classes in waiting list for this package.
     */
    public function waitingListClasses(): HasMany
    {
        return $this->hasMany(ClassInstance::class)->where('status', 'waiting_list');
    }
}
