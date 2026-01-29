<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAvailability extends Model
{
    protected $table = 'teacher_availability';

    protected $fillable = [
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
        'timezone',
        'is_available',
    ];

    protected $casts = [
        'start_time' => 'string',
        'end_time' => 'string',
        'is_available' => 'boolean',
        'day_of_week' => 'integer',
    ];

    /**
     * Get start_time formatted as H:i
     */
    public function getStartTimeAttribute($value): string
    {
        if (empty($value)) {
            return '';
        }
        
        // If already in H:i format, return as is
        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value;
        }
        
        // Parse and format to H:i
        try {
            return \Carbon\Carbon::parse($value)->format('H:i');
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Get end_time formatted as H:i
     */
    public function getEndTimeAttribute($value): string
    {
        if (empty($value)) {
            return '';
        }
        
        // If already in H:i format, return as is
        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value;
        }
        
        // Parse and format to H:i
        try {
            return \Carbon\Carbon::parse($value)->format('H:i');
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Set start_time and normalize to H:i format
     */
    public function setStartTimeAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['start_time'] = null;
            return;
        }
        
        // If already in H:i format, use as is
        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            $this->attributes['start_time'] = $value;
            return;
        }
        
        // Parse and format to H:i
        try {
            $this->attributes['start_time'] = \Carbon\Carbon::parse($value)->format('H:i');
        } catch (\Exception $e) {
            $this->attributes['start_time'] = $value;
        }
    }

    /**
     * Set end_time and normalize to H:i format
     */
    public function setEndTimeAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['end_time'] = null;
            return;
        }
        
        // If already in H:i format, use as is
        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            $this->attributes['end_time'] = $value;
            return;
        }
        
        // Parse and format to H:i
        try {
            $this->attributes['end_time'] = \Carbon\Carbon::parse($value)->format('H:i');
        } catch (\Exception $e) {
            $this->attributes['end_time'] = $value;
        }
    }

    /**
     * Get the teacher that owns the availability.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get day name
     */
    public function getDayNameAttribute(): string
    {
        $days = [
            1 => 'Sunday',
            2 => 'Monday',
            3 => 'Tuesday',
            4 => 'Wednesday',
            5 => 'Thursday',
            6 => 'Friday',
            7 => 'Saturday',
        ];
        return $days[$this->day_of_week] ?? '';
    }
}
