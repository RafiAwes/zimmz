<?php

namespace App\Http\Requests\Api\Order;

use App\Traits\LocationTrait;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    use LocationTrait;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'details' => 'nullable|string',
            'time' => 'nullable|string',
            'total_cost' => 'nullable|numeric',
            'drop_location' => 'nullable|string',
            // Location fields
            'lat' => 'nullable|string|regex:/^-?\d+(\.\d+)?$/',
            'long' => 'nullable|string|regex:/^-?\d+(\.\d+)?$/',
            // Typically type shouldn't change for an existing order,
            // but we'll allow updating child fields if needed.

            // Food Delivery fields
            'food_cost' => 'nullable|numeric',
            'special_instructions' => 'nullable|string',
            'ready_now' => 'nullable|boolean',
            'minutes_until_ready' => 'nullable|integer',
            'files' => 'nullable|array',
            'files.*' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,webp,pdf,doc,docx|max:10240',
            'delivery_fee' => 'nullable|numeric',
            'service_fee' => 'nullable|numeric',

            // Ferry Drop fields
            'pickup_location' => 'nullable|string',
            'drop_fee' => 'nullable|numeric',
            'package_fee' => 'nullable|numeric',
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
