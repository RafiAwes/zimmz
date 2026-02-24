<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $fillable = [
        'banner',
    ];

    public function getBannerAttribute($value)
    {
        if ($value) {
            return asset('storage/' . $value);
        } 
    }
}
