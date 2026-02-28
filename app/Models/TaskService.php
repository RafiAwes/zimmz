<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskService extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'runner_id',
        'task',
        'price',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function runner()
    {
        return $this->belongsTo(User::class);
    }
}
