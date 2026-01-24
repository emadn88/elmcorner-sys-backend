<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    protected $fillable = [
        'user_id',
        'hourly_rate',
        'currency',
        'timezone',
        'status',
        'bio',
        'meet_link',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'status' => 'string',
    ];

    /**
     * Get the user that owns the teacher.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the courses for the teacher.
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'teacher_courses')
            ->withTimestamps();
    }

    /**
     * Get the timetables for the teacher.
     */
    public function timetables(): HasMany
    {
        return $this->hasMany(Timetable::class);
    }

    /**
     * Get the classes for the teacher.
     */
    public function classes(): HasMany
    {
        return $this->hasMany(ClassInstance::class, 'teacher_id');
    }

    /**
     * Get the bills for the teacher.
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * Get the duties for the teacher.
     */
    public function duties(): HasMany
    {
        return $this->hasMany(Duty::class);
    }

    /**
     * Get the reports for the teacher.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Get the trial classes for the teacher.
     */
    public function trialClasses(): HasMany
    {
        return $this->hasMany(TrialClass::class);
    }

    /**
     * Get the availability slots for the teacher.
     */
    public function availability(): HasMany
    {
        return $this->hasMany(TeacherAvailability::class);
    }

    /**
     * Get the full name attribute from related user.
     */
    public function getFullNameAttribute(): string
    {
        return $this->user ? $this->user->name : '';
    }

    /**
     * Get performance stats for the teacher.
     */
    public function getPerformanceStats(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = $this->classes();

        if ($dateFrom) {
            $query->where('class_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('class_date', '<=', $dateTo);
        }

        $classes = $query->get();
        $attendedClasses = $classes->where('status', 'attended');
        
        $totalClasses = $classes->count();
        $attendedCount = $attendedClasses->count();
        $attendanceRate = $totalClasses > 0 ? ($attendedCount / $totalClasses) * 100 : 0;

        $totalRevenue = $this->bills()
            ->when($dateFrom, fn($q) => $q->where('bill_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('bill_date', '<=', $dateTo))
            ->where('status', 'paid')
            ->sum('amount');

        $totalDuration = $attendedClasses->sum('duration');
        $averageDuration = $attendedCount > 0 ? $totalDuration / $attendedCount : 0;

        $studentCount = $this->classes()
            ->when($dateFrom, fn($q) => $q->where('class_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('class_date', '<=', $dateTo))
            ->distinct('student_id')
            ->count('student_id');

        return [
            'total_classes' => $totalClasses,
            'attended_classes' => $attendedCount,
            'attendance_rate' => round($attendanceRate, 2),
            'total_revenue' => $totalRevenue,
            'average_duration' => round($averageDuration, 2),
            'student_count' => $studentCount,
        ];
    }
}
