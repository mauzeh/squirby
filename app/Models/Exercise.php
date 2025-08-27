<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
    ];

    public function workouts()
    {
        return $this->hasMany(Workout::class);
    }
}