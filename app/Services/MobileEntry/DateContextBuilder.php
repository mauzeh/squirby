<?php

namespace App\Services\MobileEntry;

use App\Services\DateTitleService;
use Carbon\Carbon;

/**
 * Builds date context for mobile entry pages
 * Handles date parsing, navigation, and title generation
 */
class DateContextBuilder
{
    public function __construct(
        private DateTitleService $dateTitleService
    ) {}
    
    /**
     * Build date context from request data
     * 
     * @param array $requestData Request data containing optional 'date' parameter
     * @return array Date context with selectedDate, navigation dates, and title
     */
    public function build(array $requestData): array
    {
        $selectedDate = isset($requestData['date'])
            ? Carbon::parse($requestData['date'])
            : Carbon::today();
        
        $today = Carbon::today();
        
        return [
            'selectedDate' => $selectedDate,
            'prevDay' => $selectedDate->copy()->subDay(),
            'nextDay' => $selectedDate->copy()->addDay(),
            'today' => $today,
            'title' => $this->dateTitleService->generateDateTitle($selectedDate, $today),
        ];
    }
}
