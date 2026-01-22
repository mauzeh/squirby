<?php

namespace App\Events;

use App\Models\LiftLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiftLogCompleted
{
    use Dispatchable, SerializesModels;
    
    public function __construct(
        public LiftLog $liftLog,
        public bool $isUpdate = false
    ) {}
}
