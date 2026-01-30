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
        'student_date',
        'student_start_time',
        'student_end_time',
        'teacher_date',
        'teacher_start_time',
        'teacher_end_time',
        'status',
        'converted_to_package_id',
        'notes',
        'meet_link_used',
        'meet_link_accessed_at',
        'reminder_5min_before_sent',
        'reminder_start_time_sent',
        'reminder_5min_after_sent',
    ];

    protected $casts = [
        'trial_date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
        'student_date' => 'date',
        'student_start_time' => 'string',
        'student_end_time' => 'string',
        'teacher_date' => 'date',
        'teacher_start_time' => 'string',
        'teacher_end_time' => 'string',
        'status' => 'string',
        'meet_link_used' => 'boolean',
        'meet_link_accessed_at' => 'datetime',
        'reminder_5min_before_sent' => 'boolean',
        'reminder_start_time_sent' => 'boolean',
        'reminder_5min_after_sent' => 'boolean',
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
