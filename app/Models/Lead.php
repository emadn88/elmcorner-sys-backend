<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Lead extends Model
{
    protected $fillable = [
        'name',
        'whatsapp',
        'country',
        'timezone',
        'number_of_students',
        'ages',
        'source',
        'status',
        'priority',
        'assigned_to',
        'next_follow_up',
        'notes',
        'converted_to_student_id',
        'last_contacted_at',
    ];

    protected $casts = [
        'ages' => 'array',
        'status' => 'string',
        'priority' => 'string',
        'next_follow_up' => 'datetime',
        'last_contacted_at' => 'datetime',
    ];

    /**
     * Get the user assigned to this lead.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the student this lead was converted to.
     */
    public function convertedStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'converted_to_student_id');
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        if ($status === 'all') {
            return $query;
        }
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include leads that need follow-up.
     */
    public function scopeNeedsFollowUp(Builder $query): Builder
    {
        return $query->where('status', 'needs_follow_up')
            ->orWhere(function ($q) {
                $q->whereNotNull('next_follow_up')
                  ->where('next_follow_up', '<=', now());
            });
    }

    /**
     * Scope a query to only include overdue follow-ups.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('next_follow_up')
            ->where('next_follow_up', '<', now());
    }

    /**
     * Scope a query to filter by priority.
     */
    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        if ($priority === 'all') {
            return $query;
        }
        return $query->where('priority', $priority);
    }

    /**
     * Scope a query to filter by country.
     */
    public function scopeByCountry(Builder $query, string $country): Builder
    {
        if (empty($country)) {
            return $query;
        }
        return $query->where('country', $country);
    }

    /**
     * Scope a query to filter by assigned user.
     */
    public function scopeByAssignedTo(Builder $query, ?int $userId): Builder
    {
        if ($userId === null) {
            return $query;
        }
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope a query to search by name or whatsapp.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('whatsapp', 'like', "%{$search}%");
        });
    }
}
