<?php

namespace App\Http\Requests\Api\SupportMessage;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupportMessageRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'subject.required' => 'Please provide a subject for your message.',
            'message.required' => 'Please write your message before sending.',
        ];
    }
}
