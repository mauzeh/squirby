@props(['liftLog'])

<td>
    <a href="{{ route('exercises.show-logs', $liftLog->exercise) }}">{{ $liftLog->exercise->title }}</a>
    <div class="show-on-mobile mobile-summary">
        {{ $liftLog->logged_at->format('m/d') }} -
        <x-lift-weight-display :liftLog="$liftLog" /> (<x-lift-reps-sets-display :reps="$liftLog->display_reps" :sets="$liftLog->display_rounds" />)
        @if ($liftLog->one_rep_max)
            <br><i>1RM: {{ round($liftLog->one_rep_max) }} lbs</i>
        @endif
    </div>
</td>