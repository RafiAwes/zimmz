<?php

namespace App\Traits;

trait LocationTrait
{
    /**
     * Get validation rules for location coordinates (lat and long).
     */
    public function getLocationValidationRules(): array
    {
        return [
            'lat' => 'required|string|regex:/^-?\d+(\.\d+)?$/',
            'long' => 'required|string|regex:/^-?\d+(\.\d+)?$/',
        ];
    }

    /**
     * Get error messages for location validation.
     */
    public function getLocationValidationMessages(): array
    {
        return [
            'lat.required' => 'Latitude is required.',
            'lat.string' => 'Latitude must be a string.',
            'lat.regex' => 'Latitude must be a valid coordinate.',
            'long.required' => 'Longitude is required.',
            'long.string' => 'Longitude must be a string.',
            'long.regex' => 'Longitude must be a valid coordinate.',
        ];
    }

    /**
     * Get the location data (lat and long) from the request.
     */
    public function getLocationData(array $data): array
    {
        return [
            'lat' => $data['lat'] ?? null,
            'long' => $data['long'] ?? null,
        ];
    }
}
