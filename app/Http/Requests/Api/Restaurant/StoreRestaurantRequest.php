<?php

namespace App\Http\Requests\Api\Restaurant;

use App\Traits\LocationTrait;
use Illuminate\Foundation\Http\FormRequest;

class StoreRestaurantRequest extends FormRequest
{
    use LocationTrait;

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
            'name' => 'required|string|max:255',
            ...$this->getLocationValidationRules(),
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->getLocationValidationMessages(),
        ];
    }
}
