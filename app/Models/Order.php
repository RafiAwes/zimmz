<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

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
