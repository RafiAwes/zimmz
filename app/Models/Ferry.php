<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ferry extends Model
{
    use HasFactory;
    protected $fillable = [
        'island_id',
        'name',
        'days',
        'times',
    ];

    protected $casts = [
        'days' => 'array',
        'times' => 'array',
    ];

    public function island()
    {
        return $this->belongsTo(Island::class);
    }
}
