<?php

namespace App\Http\Requests\Trial;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTrialRequest extends FormRequest
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
            'student_id' => ['sometimes', 'exists:students,id'],
            'teacher_id' => ['sometimes', 'exists:teachers,id'],
            'course_id' => ['sometimes', 'exists:courses,id'],
            'trial_date' => ['sometimes', 'date', 'after_or_equal:today'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i', 'after:start_time'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'student_id.exists' => 'Selected student does not exist',
            'teacher_id.exists' => 'Selected teacher does not exist',
            'course_id.exists' => 'Selected course does not exist',
            'trial_date.after_or_equal' => 'Trial date must be today or in the future',
            'start_time.date_format' => 'Start time must be in HH:mm format',
            'end_time.date_format' => 'End time must be in HH:mm format',
            'end_time.after' => 'End time must be after start time',
        ];
    }
}
