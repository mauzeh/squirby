@props(['liftLog'])

@php
    // Use strategy pattern for display formatting
    $strategy = $liftLog->exercise->getTypeStrategy();
    $displayData = $strategy->formatMobileSummaryDisplay($liftLog);
    
    $weight = $displayData['weight'];
    $repsSets = $displayData['repsSets'];
    $showWeight = $displayData['showWeight'];

    // Format relative date
    $now = now();
    $loggedDate = $liftLog->logged_at;
    $daysDiff = abs($now->diffInDays($loggedDate));
    
    if ($loggedDate->isToday()) {
        $dateText = 'Today';
        $dateBgColor = '#28a745'; // Green for today
    } elseif ($loggedDate->isYesterday()) {
        $dateText = 'Yesterday';
        $dateBgColor = '#ffc107'; // Yellow for yesterday
    } elseif ($daysDiff <= 7) {
        $dateText = (int) $daysDiff . ' days ago';
        $dateBgColor = '#007bff'; // Blue for within 7 days
    } else {
        $dateText = $loggedDate->format('n/j');
        $dateBgColor = '#6c757d'; // Gray for older dates
    }
@endphp

<div style="margin-bottom: 5px; font-weight: bold;">{{ $liftLog->exercise->title }}</div>
<div style="margin-top: 7px; margin-bottom: 15px;">
    <span style="background-color: {{ $dateBgColor }}; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.85em; margin-right: 8px;">{{ $dateText }}</span><span style="background-color: #4a5568; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.85em;">{{ $repsSets }}</span>
</div>
@if($showWeight)
<div style="margin-bottom: 8px;">
    <span style="background-color: #2d3748; color: white; padding: 8px 12px; border-radius: 16px; font-weight: bold; font-size: 1.1em;">{{ $weight }}</span>
</div>
@endif