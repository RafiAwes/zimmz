<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FerryDrop extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'pickup_location',
        'ferry_id',
        'island_id',
        'drop_fee',
        'package_fee',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function ferry()
    {
        return $this->belongsTo(Ferry::class);
    }

    public function island()
    {
        return $this->belongsTo(Island::class);
    }
}
