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
        $userId = Auth::id();
        
        $exercises = Exercise::availableToUser($userId)
            ->select('title')
            ->orderBy('title')
            ->get()
            ->pluck('title')
            ->unique()
            ->values();

        \Log::info('Exercise autocomplete', [
            'user_id' => $userId,
            'count' => $exercises->count(),
            'sample' => $exercises->take(5)->toArray()
        ]);

        return response()->json($exercises);
    }
}
