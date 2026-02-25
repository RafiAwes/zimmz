<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Island extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function ferries()
    {
        return $this->hasMany(Ferry::class);
    }

    public function ferryDrops()
    {
        return $this->hasMany(FerryDrop::class);
    }
}
