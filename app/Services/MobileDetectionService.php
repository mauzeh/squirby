<?php

namespace App\Services;

use Illuminate\Http\Request;

class MobileDetectionService
{
    public function isMobile(Request $request): bool
    {
        $userAgent = $request->header('User-Agent');

        $mobileKeywords = ['mobile', 'android', 'iphone', 'ipod', 'blackberry', 'windows phone'];
        foreach ($mobileKeywords as $keyword) {
            if (stripos($userAgent, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}
