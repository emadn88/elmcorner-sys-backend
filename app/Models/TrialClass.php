<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class TrialClass extends Model
{
    protected $fillable = [
        'student_id',
        'teacher_id',
        'course_id',
        'trial_date',
        'start_time',
        'end_time',
        'status',
        'converted_to_package_id',
        'notes',
    ];

    protected $casts = [
        'trial_date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
        'status' => 'string',
    ];

    /**
     * Get the student for the trial class.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the teacher for the trial class.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the course for the trial class.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the package that this trial was converted to.
     */
    public function convertedPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'converted_to_package_id');
    }

    /**
     * Scope a query to only include pending trials.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include completed trials.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include no-show trials.
     */
    public function scopeNoShow(Builder $query): Builder
    {
        return $query->where('status', 'no_show');
    }

    /**
     * Scope a query to only include converted trials.
     */
    public function scopeConverted(Builder $query): Builder
    {
        return $query->where('status', 'converted');
    }
}
