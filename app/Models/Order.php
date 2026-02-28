<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'status',
        'details',
        'time',
        'total_cost',
        'drop_location',
        'type',
        'files',
    ];

    protected function casts(): array
    {
        return [
            'files' => 'array',
        ];
    }

    protected function files(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                // 1. Decode the raw JSON string from the database into a real PHP array
                // If $value is null (no files), it defaults to an empty array []
                $filesArray = json_decode($value, true) ?? [];

                // 2. Now loop through the ACTUAL array of file paths
                return collect($filesArray)
                    ->map(fn($file) => str_starts_with($file, 'http') ? $file : asset('storage/' . $file))
                    ->toArray();
            },
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function foodDelivery()
    {
        return $this->hasOne(FoodDelivery::class);
    }

    public function ferryDrop()
    {
        return $this->hasOne(FerryDrop::class);
    }
}
