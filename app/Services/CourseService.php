<?php

namespace App\Services;

use App\Models\Course;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class CourseService
{
    /**
     * Search and filter courses
     */
    public function getCourses(array $filters = [], int $perPage = 15)
    {
        $query = Course::with('teachers');

        // Search by name or description
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Filter by category
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        return $query->orderBy('name', 'asc')->paginate($perPage);
    }

    /**
     * Get course with teachers
     */
    public function getCourse(int $courseId): Course
    {
        return Course::with('teachers')->findOrFail($courseId);
    }

    /**
     * Assign teachers to course
     */
    public function assignTeachers(int $courseId, array $teacherIds): Course
    {
        $course = Course::findOrFail($courseId);
        $course->teachers()->sync($teacherIds);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'assign_teachers',
            'description' => "Teachers assigned to course {$course->name}",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return $course->fresh()->load('teachers');
    }

    /**
     * Get course statistics
     */
    public function getCourseStats(): array
    {
        return [
            'total' => Course::count(),
            'active' => Course::where('status', 'active')->count(),
            'inactive' => Course::where('status', 'inactive')->count(),
        ];
    }
}
