<?php

namespace App\Http\Controllers;

use App\Models\MagicLoginToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MagicLoginController extends Controller
{
    public function login(Request $request, $token)
    {
        $magicToken = MagicLoginToken::where('token', $token)->first();

        if (!$magicToken) {
            return response('Magic link not found.', 404);
        }

        if ($magicToken->expires_at < now()) {
            return response('Magic link has expired.', 403);
        }

        if ($magicToken->uses_remaining <= 0) {
            return response('Magic link has no uses remaining.', 403);
        }

        $magicToken->decrement('uses_remaining');

        Auth::login($magicToken->user);

        $request->session()->regenerate();

        return redirect()->intended('/');
    }
}