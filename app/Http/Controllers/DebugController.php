<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LiftLog;
use App\Mail\FirstLiftOfTheDay;

class DebugController extends Controller
{
    public function previewFirstLiftEmail()
    {
        $liftLog = LiftLog::with('exercise')->latest()->first();
        if (!$liftLog) {
            return "No lift logs found to render the email.";
        }
        return new FirstLiftOfTheDay($liftLog);
    }
}
