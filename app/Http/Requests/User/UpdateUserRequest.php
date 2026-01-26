<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user') ?? $this->route('id');
        
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['sometimes', 'required', 'string', 'exists:roles,name'],
            'whatsapp' => ['nullable', 'string', 'max:20', 'regex:/^\+[1-9]\d{6,14}$/'],
            'timezone' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'whatsapp.regex' => 'The WhatsApp number must be in E.164 format (e.g., +966501234567) with country code.',
            'role.exists' => 'The selected role does not exist.',
        ];
    }
}
