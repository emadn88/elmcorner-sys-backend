<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Report extends Model
{
    protected $fillable = [
        'student_id',
        'teacher_id',
        'report_type',
        'content',
        'pdf_path',
        'sent_via_whatsapp',
    ];

    protected $casts = [
        'content' => 'array',
        'sent_via_whatsapp' => 'boolean',
    ];

    /**
     * Get the student that owns the report.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the teacher that owns the report.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Scope a query to only include reports of a given type.
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('report_type', $type);
    }

    /**
     * Scope a query to only include reports for a given student.
     */
    public function scopeByStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * Scope a query to only include reports for a given teacher.
     */
    public function scopeByTeacher(Builder $query, int $teacherId): Builder
    {
        return $query->where('teacher_id', $teacherId);
    }

    /**
     * Scope a query to only include reports sent via WhatsApp.
     */
    public function scopeSentViaWhatsApp(Builder $query): Builder
    {
        return $query->where('sent_via_whatsapp', true);
    }

    /**
     * Get formatted content attribute.
     */
    public function getFormattedContentAttribute(): array
    {
        return $this->content ?? [];
    }
}
