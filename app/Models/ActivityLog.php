<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';
    
    protected $fillable = [
        'user_id',
        'student_id',
        'action',
        'description',
        'ip_address',
        'created_at',
    ];
    
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    /**
     * Get the user that performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the student related to the activity.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Scope a query to filter by user.
     */
    public function scopeByUser(Builder $query, ?int $userId): Builder
    {
        if ($userId) {
            return $query->where('user_id', $userId);
        }
        return $query;
    }

    /**
     * Scope a query to filter by student.
     */
    public function scopeByStudent(Builder $query, ?int $studentId): Builder
    {
        if ($studentId) {
            return $query->where('student_id', $studentId);
        }
        return $query;
    }

    /**
     * Scope a query to filter by action type.
     */
    public function scopeByAction(Builder $query, ?string $action): Builder
    {
        if ($action && $action !== 'all') {
            return $query->where('action', 'like', "%{$action}%");
        }
        return $query;
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeByDateRange(Builder $query, ?string $dateFrom, ?string $dateTo): Builder
    {
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }
        return $query;
    }

    /**
     * Scope a query to search in description.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search) {
            return $query->where('description', 'like', "%{$search}%");
        }
        return $query;
    }
}
