<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\ActivityLog;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LeadService
{
    /**
     * Get leads list with filters and pagination
     */
    public function getLeads(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Lead::with(['assignedUser', 'convertedStudent']);

        // Apply filters
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->byStatus($filters['status']);
        }

        if (isset($filters['priority']) && $filters['priority'] !== 'all') {
            $query->byPriority($filters['priority']);
        }

        if (isset($filters['country']) && !empty($filters['country'])) {
            $query->byCountry($filters['country']);
        }

        if (isset($filters['assigned_to'])) {
            $query->byAssignedTo($filters['assigned_to']);
        }

        if (isset($filters['source']) && !empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (isset($filters['overdue_follow_up']) && $filters['overdue_follow_up']) {
            $query->overdue();
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $query->search($filters['search']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get single lead with relationships
     */
    public function getLead(int $id): Lead
    {
        return Lead::with(['assignedUser', 'convertedStudent'])
            ->findOrFail($id);
    }

    /**
     * Create new lead
     */
    public function createLead(array $data): Lead
    {
        // Auto-set timezone from country if not provided
        if (isset($data['country']) && empty($data['timezone'])) {
            $data['timezone'] = $this->getTimezoneForCountry($data['country']);
        }

        $lead = Lead::create($data);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'create_lead',
            'description' => "Lead created: {$lead->name} ({$lead->whatsapp})",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return $lead->fresh()->load(['assignedUser', 'convertedStudent']);
    }

    /**
     * Update lead
     */
    public function updateLead(int $id, array $data): Lead
    {
        $lead = Lead::findOrFail($id);
        
        // Auto-set timezone from country if country changed
        if (isset($data['country']) && $data['country'] !== $lead->country && empty($data['timezone'])) {
            $data['timezone'] = $this->getTimezoneForCountry($data['country']);
        }

        $lead->update($data);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'update_lead',
            'description' => "Lead updated: {$lead->name}",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return $lead->fresh()->load(['assignedUser', 'convertedStudent']);
    }

    /**
     * Update lead status
     */
    public function updateLeadStatus(int $id, string $status, ?string $notes = null): Lead
    {
        $lead = Lead::findOrFail($id);
        $oldStatus = $lead->status;
        
        $lead->status = $status;
        
        // Update last_contacted_at if status changed to contacted
        if ($status === 'contacted' && $oldStatus !== 'contacted') {
            $lead->last_contacted_at = now();
        }
        
        if ($notes) {
            $lead->notes = ($lead->notes ? $lead->notes . "\n\n" : '') . 
                now()->format('Y-m-d H:i') . ': ' . $notes;
        }
        
        $lead->save();

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'update_lead_status',
            'description' => "Lead status changed from {$oldStatus} to {$status}: {$lead->name}",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return $lead->fresh()->load(['assignedUser', 'convertedStudent']);
    }

    /**
     * Delete lead
     */
    public function deleteLead(int $id): void
    {
        $lead = Lead::findOrFail($id);
        $leadName = $lead->name;
        
        $lead->delete();

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'delete_lead',
            'description' => "Lead deleted: {$leadName}",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Get lead statistics
     */
    public function getLeadStats(): array
    {
        $total = Lead::count();
        $new = Lead::where('status', 'new')
            ->whereDate('created_at', '>=', now()->startOfWeek())
            ->count();
        $needsFollowUp = Lead::needsFollowUp()->count();
        $trialsScheduled = Lead::where('status', 'trial_scheduled')
            ->orWhere('status', 'trial_confirmed')
            ->count();
        $converted = Lead::where('status', 'converted')->count();
        
        $conversionRate = $total > 0 ? round(($converted / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'new' => $new,
            'needs_follow_up' => $needsFollowUp,
            'trials_scheduled' => $trialsScheduled,
            'converted' => $converted,
            'conversion_rate' => $conversionRate,
        ];
    }

    /**
     * Bulk update lead status
     */
    public function bulkUpdateStatus(array $leadIds, string $status): int
    {
        $updated = Lead::whereIn('id', $leadIds)
            ->update([
                'status' => $status,
                'last_contacted_at' => $status === 'contacted' ? now() : DB::raw('last_contacted_at'),
            ]);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'bulk_update_lead_status',
            'description' => "Bulk updated {$updated} leads to status: {$status}",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return $updated;
    }

    /**
     * Convert lead to student and optionally create trial
     */
    public function convertLead(int $leadId, array $studentData, ?array $trialData = null): array
    {
        $lead = Lead::findOrFail($leadId);

        // Create student
        $student = Student::create([
            'full_name' => $studentData['full_name'] ?? $lead->name,
            'email' => $studentData['email'] ?? null,
            'whatsapp' => $studentData['whatsapp'] ?? $lead->whatsapp,
            'country' => $studentData['country'] ?? $lead->country,
            'currency' => $studentData['currency'] ?? 'USD',
            'timezone' => $studentData['timezone'] ?? $lead->timezone,
            'status' => 'active',
            'type' => 'confirmed',
        ]);

        // Update lead
        $lead->status = 'converted';
        $lead->converted_to_student_id = $student->id;
        $lead->save();

        $trial = null;
        if ($trialData) {
            $trial = \App\Models\TrialClass::create([
                'student_id' => $student->id,
                'teacher_id' => $trialData['teacher_id'],
                'course_id' => $trialData['course_id'],
                'trial_date' => $trialData['trial_date'],
                'start_time' => $trialData['start_time'],
                'end_time' => $trialData['end_time'],
                'status' => 'pending',
                'notes' => $trialData['notes'] ?? null,
            ]);
        }

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'student_id' => $student->id,
            'action' => 'convert_lead',
            'description' => "Lead converted to student: {$lead->name} -> {$student->full_name}",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return [
            'lead' => $lead->fresh()->load(['assignedUser', 'convertedStudent']),
            'student' => $student,
            'trial' => $trial,
        ];
    }

    /**
     * Get timezone for country (simplified - you can enhance this)
     */
    private function getTimezoneForCountry(string $country): string
    {
        // Map of common countries to timezones
        $timezoneMap = [
            'SA' => 'Asia/Riyadh',
            'AE' => 'Asia/Dubai',
            'KW' => 'Asia/Kuwait',
            'QA' => 'Asia/Qatar',
            'EG' => 'Africa/Cairo',
            'US' => 'America/New_York',
            'GB' => 'Europe/London',
        ];

        return $timezoneMap[$country] ?? 'UTC';
    }
}
