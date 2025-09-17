<?php

namespace App\Services\ProgressionModels;

use Carbon\Carbon;

interface ProgressionModel
{
    public function suggest(int $userId, int $exerciseId, Carbon $forDate = null): ?object;
}