<?php

namespace App\Http\Requests\Api\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
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
            // Order base fields
            'name' => 'nullable|string|max:255',
            'details' => 'nullable|string',
            'time' => 'nullable|string',
            'total_cost' => 'required|numeric',
            'drop_location' => 'required|string',
            'type' => 'required|in:food_delivery,ferry_drops',

            // Food Delivery fields
            'restaurant_id' => 'required_if:type,food_delivery|exists:restaurants,id',
            'food_cost' => 'required_if:type,food_delivery|numeric',
            'special_instructions' => 'nullable|string',
            'ready_now' => 'nullable|boolean',
            'minutes_until_ready' => 'nullable|integer',
            'files' => 'nullable|array',
            'files.*' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,webp,pdf,doc,docx|max:10240',
            'delivery_fee' => 'required_if:type,food_delivery|numeric',
            'service_fee' => 'required_if:type,food_delivery|numeric',

            // Ferry Drop fields
            'pickup_location' => 'required_if:type,ferry_drops|string',
            'ferry_id' => 'required_if:type,ferry_drops|exists:ferries,id',
            'island_id' => 'required_if:type,ferry_drops|exists:islands,id',
            'drop_fee' => 'required_if:type,ferry_drops|numeric',
            'package_fee' => 'required_if:type,ferry_drops|numeric',
        ];
    }
}
