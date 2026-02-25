<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Runner extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'category',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
