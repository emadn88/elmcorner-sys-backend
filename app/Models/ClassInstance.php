<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class ClassInstance extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'timetable_id',
        'package_id',
        'student_id',
        'teacher_id',
        'course_id',
        'class_date',
        'start_time',
        'end_time',
        'duration',
        'status',
        'cancelled_by',
        'cancellation_reason',
        'notes',
        'student_evaluation',
        'class_report',
        'meet_link_used',
        'meet_link_accessed_at',
        'cancellation_request_status',
        'reminder_5min_before_sent',
        'reminder_start_time_sent',
        'reminder_5min_after_sent',
    ];

    protected $casts = [
        'class_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration' => 'integer',
        'status' => 'string',
        'meet_link_used' => 'boolean',
        'meet_link_accessed_at' => 'datetime',
        'cancellation_request_status' => 'string',
        'reminder_5min_before_sent' => 'boolean',
        'reminder_start_time_sent' => 'boolean',
        'reminder_5min_after_sent' => 'boolean',
    ];

    /**
     * Get the start time as HH:mm:ss format for JSON serialization
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Append formatted time attributes to array/JSON
     */
    protected $appends = [];

    /**
     * Get start_time formatted as HH:mm:ss
     */
    public function getFormattedStartTimeAttribute()
    {
        $value = $this->attributes['start_time'] ?? null;
        if ($value) {
            try {
                return Carbon::parse($value)->format('H:i:s');
            } catch (\Exception $e) {
                return $value;
            }
        }
        return $value;
    }

    /**
     * Get end_time formatted as HH:mm:ss
     */
    public function getFormattedEndTimeAttribute()
    {
        $value = $this->attributes['end_time'] ?? null;
        if ($value) {
            try {
                return Carbon::parse($value)->format('H:i:s');
            } catch (\Exception $e) {
                return $value;
            }
        }
        return $value;
    }

    /**
     * Override toArray to format times properly
     */
    public function toArray()
    {
        $array = parent::toArray();
        
        // Format start_time and end_time if they exist
        if (isset($this->attributes['start_time'])) {
            try {
                $array['start_time'] = Carbon::parse($this->attributes['start_time'])->format('H:i:s');
            } catch (\Exception $e) {
                $array['start_time'] = $this->attributes['start_time'];
            }
        }
        
        if (isset($this->attributes['end_time'])) {
            try {
                $array['end_time'] = Carbon::parse($this->attributes['end_time'])->format('H:i:s');
            } catch (\Exception $e) {
                $array['end_time'] = $this->attributes['end_time'];
            }
        }
        
        return $array;
    }

    /**
     * Get the timetable that owns the class.
     */
    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    /**
     * Get the package for the class.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get the student for the class.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the teacher for the class.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the course for the class.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the bill for the class.
     */
    public function bill(): HasOne
    {
        return $this->hasOne(Bill::class, 'class_id');
    }

    /**
     * Get the user who cancelled the class.
     */
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Scope a query to only include pending classes.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include attended classes.
     */
    public function scopeAttended(Builder $query): Builder
    {
        return $query->where('status', 'attended');
    }

    /**
     * Scope a query to only include cancelled classes.
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->whereIn('status', ['cancelled_by_student', 'cancelled_by_teacher']);
    }

    /**
     * Scope a query to only include upcoming classes.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('class_date', '>=', Carbon::today());
    }

    /**
     * Scope a query to only include past classes.
     */
    public function scopePast(Builder $query): Builder
    {
        return $query->where('class_date', '<', Carbon::today());
    }
}
