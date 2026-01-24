<?php

namespace App\Http\Requests\Trial;

use Illuminate\Foundation\Http\FormRequest;

class ConvertTrialRequest extends FormRequest
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
            // Package fields
            'package.total_classes' => ['required', 'integer', 'min:1'],
            'package.hour_price' => ['required', 'numeric', 'min:0'],
            'package.currency' => ['required', 'string', 'max:3'],
            'package.start_date' => ['required', 'date', 'after_or_equal:today'],
            
            // Timetable fields
            'timetable.days_of_week' => ['required', 'array', 'min:1'],
            'timetable.days_of_week.*' => ['integer', 'min:1', 'max:7'],
            'timetable.time_slots' => ['required', 'array', 'min:1'],
            'timetable.time_slots.*.day' => ['required', 'integer', 'min:1', 'max:7'],
            'timetable.time_slots.*.start' => ['required', 'date_format:H:i'],
            'timetable.time_slots.*.end' => ['required', 'date_format:H:i', 'after:timetable.time_slots.*.start'],
            'timetable.student_timezone' => ['required', 'string', 'max:255'],
            'timetable.teacher_timezone' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'package.total_classes.required' => 'Total classes is required',
            'package.total_classes.min' => 'Total classes must be at least 1',
            'package.hour_price.required' => 'Hour price is required',
            'package.hour_price.min' => 'Hour price must be greater than or equal to 0',
            'package.currency.required' => 'Currency is required',
            'package.start_date.required' => 'Package start date is required',
            'package.start_date.after_or_equal' => 'Package start date must be today or in the future',
            'timetable.days_of_week.required' => 'At least one day of week must be selected',
            'timetable.time_slots.required' => 'At least one time slot must be configured',
            'timetable.time_slots.*.start.date_format' => 'Start time must be in HH:mm format',
            'timetable.time_slots.*.end.date_format' => 'End time must be in HH:mm format',
            'timetable.time_slots.*.end.after' => 'End time must be after start time',
            'timetable.student_timezone.required' => 'Student timezone is required',
            'timetable.teacher_timezone.required' => 'Teacher timezone is required',
        ];
    }
}
