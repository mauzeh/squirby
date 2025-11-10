<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class MobileLiftForm extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'date',
        'exercise_id'
    ];
    
    protected $casts = [
        'date' => 'date'
    ];
    
    /**
     * Get the user that owns the mobile lift form
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the exercise for this form
     */
    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }
    
    /**
     * Scope to get forms for a specific user and date
     */
    public function scopeForUserAndDate($query, $userId, Carbon $date)
    {
        return $query->where('user_id', $userId)
                    ->whereDate('date', $date->toDateString());
    }
}
