<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Measurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'value',
        'unit',
        'logged_at',
        'comments',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];
}
