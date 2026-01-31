<?php

namespace App\Http\Requests\Trial;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Permission check handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'teacher_id' => ['required', 'exists:teachers,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'trial_date' => ['nullable', 'date', 'after_or_equal:today'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'student_date' => ['required', 'date', 'after_or_equal:today'],
            'student_start_time' => ['required', 'date_format:H:i'],
            'student_end_time' => ['required', 'date_format:H:i', 'after:student_start_time'],
            'teacher_date' => ['required', 'date', 'after_or_equal:today'],
            'teacher_start_time' => ['required', 'date_format:H:i'],
            'teacher_end_time' => ['required', 'date_format:H:i', 'after:teacher_start_time'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'Please select a student',
            'student_id.exists' => 'Selected student does not exist',
            'teacher_id.required' => 'Teacher is required',
            'teacher_id.exists' => 'Selected teacher does not exist',
            'course_id.required' => 'Course is required',
            'course_id.exists' => 'Selected course does not exist',
            'trial_date.required' => 'Trial date is required',
            'trial_date.after_or_equal' => 'Trial date must be today or in the future',
            'start_time.required' => 'Start time is required',
            'start_time.date_format' => 'Start time must be in HH:mm format',
            'end_time.required' => 'End time is required',
            'end_time.date_format' => 'End time must be in HH:mm format',
            'end_time.after' => 'End time must be after start time',
        ];
    }
}
