<?php

namespace App\Http\Requests\Api\Faq;

use Illuminate\Foundation\Http\FormRequest;

class UpsertFaqRequest extends FormRequest
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
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'is_active' => 'sometimes|boolean',
            // 'sort_order' => 'sometimes|integer',
        ];
    }
}
