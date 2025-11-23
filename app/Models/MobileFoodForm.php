<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class MobileFoodForm extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'date', 'type', 'item_id', 'item_name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    
    protected $fillable = [
        'user_id',
        'date',
        'type',
        'item_id',
        'item_name'
    ];
    
    protected $casts = [
        'date' => 'date'
    ];
    
    /**
     * Get the user that owns the mobile food form
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the ingredient if this is an ingredient form
     */
    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class, 'item_id')->where('type', 'ingredient');
    }
    
    /**
     * Get the meal if this is a meal form
     */
    public function meal()
    {
        return $this->belongsTo(Meal::class, 'item_id')->where('type', 'meal');
    }
    
    /**
     * Scope to get forms for a specific user and date
     */
    public function scopeForUserAndDate($query, $userId, Carbon $date)
    {
        return $query->where('user_id', $userId)
                    ->whereDate('date', $date->toDateString());
    }
    
    /**
     * Clean up old forms (older than 7 days)
     */
    public static function cleanupOldForms()
    {
        static::where('date', '<', Carbon::now()->subDays(7))->delete();
    }
}
