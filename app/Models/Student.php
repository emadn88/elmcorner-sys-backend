<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'family_id',
        'full_name',
        'email',
        'whatsapp',
        'country',
        'currency',
        'timezone',
        'language',
        'status',
        'type',
        'notes',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
        'status' => 'string',
        'type' => 'string',
    ];

    /**
     * Get the family that owns the student.
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * Get the packages for the student.
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    /**
     * Get the timetables for the student.
     */
    public function timetables(): HasMany
    {
        return $this->hasMany(Timetable::class);
    }

    /**
     * Get the classes for the student.
     */
    public function classes(): HasMany
    {
        return $this->hasMany(ClassInstance::class, 'student_id');
    }

    /**
     * Get the bills for the student.
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * Get the duties for the student.
     */
    public function duties(): HasMany
    {
        return $this->hasMany(Duty::class);
    }

    /**
     * Get the reports for the student.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Get the activity logs for the student.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Get the trial classes for the student.
     */
    public function trialClasses(): HasMany
    {
        return $this->hasMany(TrialClass::class);
    }

    /**
     * Get the activity level attribute.
     * Calculates based on recent classes: highly_active, medium, low, stopped
     */
    public function getActivityLevelAttribute(): string
    {
        // Get classes from last 30 days
        $recentClasses = $this->classes()
            ->where('class_date', '>=', now()->subDays(30))
            ->where('status', 'attended')
            ->count();

        if ($recentClasses >= 8) {
            return 'highly_active';
        } elseif ($recentClasses >= 4) {
            return 'medium';
        } elseif ($recentClasses >= 1) {
            return 'low';
        } else {
            return 'stopped';
        }
    }
}
