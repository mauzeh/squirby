<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use Illuminate\Support\Facades\Auth;

class ApiController extends Controller
{
    /**
     * Get exercise names for autocomplete
     */
    public function exerciseAutocomplete()
    {
        $exercises = Exercise::availableToUser(Auth::id())
            ->select('title')
            ->orderBy('title')
            ->get()
            ->pluck('title')
            ->unique()
            ->values();

        return response()->json($exercises);
    }
}
