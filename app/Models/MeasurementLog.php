<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeasurementLog extends Model
{
    use HasFactory;

    protected $table = 'measurement_logs';

    protected $fillable = [
        'measurement_type_id',
        'value',
        'logged_at',
        'comments',
        'user_id',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    public function measurementType()
    {
        return $this->belongsTo(MeasurementType::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
