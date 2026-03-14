<?php

namespace App\Http\Requests\Api\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class CreateSubscriptionCheckoutRequest extends FormRequest
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
            'price_id' => 'nullable|string',
            'success_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
            'allow_promotion_codes' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'success_url.url' => 'The success URL must be a valid URL.',
            'cancel_url.url' => 'The cancel URL must be a valid URL.',
        ];
    }
}
