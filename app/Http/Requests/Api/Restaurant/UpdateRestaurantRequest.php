<?php

namespace App\Http\Requests\Api\Restaurant;

use App\Traits\LocationTrait;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRestaurantRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'lat' => 'sometimes|string|regex:/^-?\d+(\.\d+)?$/',
            'long' => 'sometimes|string|regex:/^-?\d+(\.\d+)?$/',
        ];
    }

    public function messages(): array
    {
        return [
            'lat.regex' => 'Latitude must be a valid coordinate.',
            'long.regex' => 'Longitude must be a valid coordinate.',
        ];
    }
}
