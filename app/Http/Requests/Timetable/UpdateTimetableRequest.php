<?php

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimetableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'student_id' => 'sometimes|exists:students,id',
            'teacher_id' => 'sometimes|exists:teachers,id',
            'course_id' => 'sometimes|exists:courses,id',
            'days_of_week' => 'sometimes|array|min:1',
            'days_of_week.*' => 'integer|min:1|max:7',
            'time_slots' => 'sometimes|array|min:1',
            'time_slots.*.day' => 'required|integer|min:1|max:7',
            'time_slots.*.start' => 'required|date_format:H:i',
            'time_slots.*.end' => 'required|date_format:H:i',
            'student_timezone' => 'sometimes|string|max:255',
            'teacher_timezone' => 'sometimes|string|max:255',
            'time_difference_minutes' => 'sometimes|integer',
            'status' => 'sometimes|in:active,paused,stopped',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $timeSlots = $this->input('time_slots', []);
            
            foreach ($timeSlots as $index => $slot) {
                if (!isset($slot['start']) || !isset($slot['end'])) {
                    continue;
                }
                
                $start = $slot['start'];
                $end = $slot['end'];
                
                // Parse times to minutes since midnight
                list($startHour, $startMin) = explode(':', $start);
                list($endHour, $endMin) = explode(':', $end);
                
                $startMinutes = (int)$startHour * 60 + (int)$startMin;
                $endMinutes = (int)$endHour * 60 + (int)$endMin;
                
                // Allow times that span midnight (end < start means it goes to next day)
                // Only reject if end equals start (zero duration)
                if ($endMinutes === $startMinutes) {
                    $validator->errors()->add(
                        "time_slots.{$index}.end",
                        'End time must be different from start time'
                    );
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'days_of_week.required' => 'At least one day of week must be selected',
            'time_slots.required' => 'At least one time slot must be configured',
            'time_slots.*.start.date_format' => 'Start time must be in HH:mm format',
            'time_slots.*.end.date_format' => 'End time must be in HH:mm format',
        ];
    }
}
