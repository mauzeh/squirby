@props(['liftLog'])

<td>
    <div class="hide-on-mobile">
        <x-lift-logs.exercise-summary-desktop :liftLog="$liftLog" />
    </div>
    <div class="show-on-mobile">
        <x-lift-logs.exercise-summary-mobile :liftLog="$liftLog['raw_lift_log']" />
    </div>
</td>