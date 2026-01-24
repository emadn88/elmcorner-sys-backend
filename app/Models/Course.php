<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'name',
        'category',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Get the teachers for the course.
     */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'teacher_courses')
            ->withTimestamps();
    }

    /**
     * Get the timetables for the course.
     */
    public function timetables(): HasMany
    {
        return $this->hasMany(Timetable::class);
    }

    /**
     * Get the classes for the course.
     */
    public function classes(): HasMany
    {
        return $this->hasMany(ClassInstance::class, 'course_id');
    }

    /**
     * Get the trial classes for the course.
     */
    public function trialClasses(): HasMany
    {
        return $this->hasMany(TrialClass::class);
    }

    /**
     * Get the count of active teachers teaching this course.
     */
    public function getActiveTeachersCount(): int
    {
        return $this->teachers()
            ->where('status', 'active')
            ->count();
    }
}
