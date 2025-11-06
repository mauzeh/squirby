@props(['liftLog'])

@php
    // Format weight display
    $weight = '';
    $showWeight = true;
    
    if ($liftLog->exercise->exercise_type === 'cardio') {
        // Cardio exercises don't show weight information
        $showWeight = false;
    } elseif (str_contains($liftLog->exercise->exercise_type, 'banded')) {
        $bandColor = $liftLog->liftSets->first()->band_color ?? null;
        if ($bandColor) {
            $weight = 'Band: ' . $bandColor;
        } else {
            $bandType = str_replace('banded_', '', $liftLog->exercise->exercise_type);
            $weight = 'Band: ' . $bandType;
        }
    } elseif ($liftLog->exercise->exercise_type === 'bodyweight') {
        $weight = 'Bodyweight';
        if ($liftLog->display_weight > 0) {
            $weight .= ' +' . $liftLog->display_weight . ' lbs';
        }
    } else {
        $weight = $liftLog->display_weight . ' lbs';
    }

    // Format reps and sets
    if ($liftLog->exercise->exercise_type === 'cardio') {
        // Use cardio-specific formatting
        try {
            $strategy = $liftLog->exercise->getTypeStrategy();
            if (method_exists($strategy, 'formatCompleteDisplay')) {
                $repsSets = $strategy->formatCompleteDisplay($liftLog);
            } else {
                // Fallback cardio formatting
                $distance = $liftLog->display_reps;
                $rounds = $liftLog->display_rounds;
                $roundsText = $rounds == 1 ? 'round' : 'rounds';
                $repsSets = "{$distance}m × {$rounds} {$roundsText}";
            }
        } catch (\Exception $e) {
            // Fallback to default formatting
            $repsSets = $liftLog->display_rounds . ' x ' . $liftLog->display_reps;
        }
    } else {
        $repsSets = $liftLog->display_rounds . ' x ' . $liftLog->display_reps;
    }

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
@if($showWeight && $liftLog->exercise->exercise_type !== 'bodyweight')
<div style="margin-bottom: 8px;">
    <span style="background-color: #2d3748; color: white; padding: 8px 12px; border-radius: 16px; font-weight: bold; font-size: 1.1em;">{{ $weight }}</span>
</div>
@endif