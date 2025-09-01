<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeasurementType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'default_unit',
    ];
}
