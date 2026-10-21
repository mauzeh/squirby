<?php

namespace App\Sync\Models;

use Illuminate\Database\Eloquent\Model;

class Gym extends Model
{
    protected $table = 'gyms';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
    ];
}
