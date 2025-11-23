<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class FoodLog extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['ingredient_id', 'unit_id', 'quantity', 'logged_at', 'notes', 'user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    
    protected $table = 'food_logs';
    
    protected $fillable = [
        'ingredient_id',
        'unit_id',
        'quantity',
        'logged_at',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}