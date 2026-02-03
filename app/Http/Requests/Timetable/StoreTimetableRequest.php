<?php

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimetableRequest extends FormRequest
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
            'student_id' => 'required|exists:students,id',
            'teacher_id' => 'required|exists:teachers,id',
            'course_id' => 'required|exists:courses,id',
            'days_of_week' => 'required|array|min:1',
            'days_of_week.*' => 'integer|min:1|max:7',
            'time_slots' => 'required|array|min:1',
            'time_slots.*.day' => 'required|integer|min:1|max:7',
            'time_slots.*.start' => 'required|date_format:H:i',
            'time_slots.*.end' => 'required|date_format:H:i|after:time_slots.*.start',
            'student_timezone' => 'required|string|max:255',
            'teacher_timezone' => 'required|string|max:255',
            'time_difference_minutes' => 'sometimes|integer',
            'status' => 'sometimes|in:active,paused,stopped',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'Student is required',
            'teacher_id.required' => 'Teacher is required',
            'course_id.required' => 'Course is required',
            'days_of_week.required' => 'At least one day of week must be selected',
            'time_slots.required' => 'At least one time slot must be configured',
            'time_slots.*.start.date_format' => 'Start time must be in HH:mm format',
            'time_slots.*.end.date_format' => 'End time must be in HH:mm format',
            'time_slots.*.end.after' => 'End time must be after start time',
        ];
    }
}
