<?php

namespace App\Http\Requests\Class;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassStatusRequest extends FormRequest
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
            'status' => [
                'required',
                Rule::in(['pending', 'attended', 'cancelled_by_student', 'cancelled_by_teacher', 'absent_student']),
            ],
            'cancellation_reason' => [
                'required_if:status,cancelled_by_student',
                'required_if:status,cancelled_by_teacher',
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status is required',
            'status.in' => 'Invalid status value',
            'cancellation_reason.required_if' => 'Cancellation reason is required when status is cancelled',
        ];
    }
}
