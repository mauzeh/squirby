@props(['liftLog'])

@php
    // Format weight display
    $weight = '';
    if (!empty($liftLog->exercise->band_type)) {
        $bandColor = $liftLog->liftSets->first()->band_color ?? null;
        if ($bandColor) {
            $weight = 'Band: ' . $bandColor;
        } else {
            $weight = 'Band: ' . $liftLog->exercise->band_type;
        }
    } elseif ($liftLog->exercise->is_bodyweight) {
        $weight = 'Bodyweight';
        if ($liftLog->display_weight > 0) {
            $weight .= ' +' . $liftLog->display_weight . ' lbs';
        }
    } else {
        $weight = $liftLog->display_weight . ' lbs';
    }

    // Format reps and sets
    $repsSets = $liftLog->display_rounds . ' x ' . $liftLog->display_reps;
@endphp

<div style="margin-bottom: 5px; font-weight: bold;">{{ $liftLog->exercise->title }}</div>
<div style="margin-top: 7px; margin-bottom: 15px;">
    <span style="background-color: #4a5568; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.85em; margin-right: 8px;">{{ $liftLog->logged_at->format('n/j') }}</span><span style="background-color: #4a5568; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.85em;">{{ $repsSets }}</span>
</div>
<div style="margin-bottom: 8px;">
    <span style="background-color: #2d3748; color: white; padding: 8px 12px; border-radius: 16px; font-weight: bold; font-size: 1.1em;">{{ $weight }}</span>
</div>