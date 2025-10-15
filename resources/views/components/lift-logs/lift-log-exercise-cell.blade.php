@props(['liftLog'])

<td>
    <a href="{{ $liftLog['exercise_url'] }}">{{ $liftLog['exercise_title'] }}</a>
    <div class="show-on-mobile mobile-summary">
        <x-lift-logs.mobile-summary :liftLog="$liftLog['raw_lift_log']" />
    </div>
</td>