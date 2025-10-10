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
        'is_bodyweight',
        'user_id',
    ];

    protected $casts = [
        'is_bodyweight' => 'boolean',
    ];

    public function liftLogs()
    {
        return $this->hasMany(LiftLog::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}