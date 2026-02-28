<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FoodDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'restaurant_id',
        'food_cost',
        'special_instructions',
        'ready_now',
        'minutes_until_ready',
        'delivery_fee',
        'service_fee',
    ];

    protected $casts = [
        'ready_now' => 'boolean',
        'files' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
