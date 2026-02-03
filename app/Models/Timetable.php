<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Timetable extends Model
{
    protected $fillable = [
        'student_id',
        'teacher_id',
        'course_id',
        'days_of_week',
        'time_slots',
        'student_timezone',
        'teacher_timezone',
        'time_difference_minutes',
        'status',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'time_slots' => 'array',
        'time_difference_minutes' => 'integer',
        'status' => 'string',
    ];

    /**
     * Get the student that owns the timetable.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the teacher for the timetable.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the course for the timetable.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the classes for the timetable.
     */
    public function classes(): HasMany
    {
        return $this->hasMany(ClassInstance::class);
    }

    /**
     * Scope a query to only include active timetables.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include paused timetables.
     */
    public function scopePaused(Builder $query): Builder
    {
        return $query->where('status', 'paused');
    }

    /**
     * Scope a query to only include stopped timetables.
     */
    public function scopeStopped(Builder $query): Builder
    {
        return $query->where('status', 'stopped');
    }
}
