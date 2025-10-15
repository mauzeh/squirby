@props(['liftLog'])

@if ($liftLog->exercise->band_type)
    <span style="font-weight: bold; font-size: 1.2em;">
        Band: {{ $liftLog->display_weight }}
    </span>
@elseif ($liftLog->exercise->is_bodyweight)
    <span style="font-weight: bold; font-size: 1.2em;">
        Bodyweight
        @if ($liftLog->display_weight > 0)
            +{{ $liftLog->display_weight }} lbs
        @endif
    </span>
@else
    <span style="font-weight: bold; font-size: 1.2em;">
        {{ $liftLog->display_weight }} lbs
    </span>
@endif
