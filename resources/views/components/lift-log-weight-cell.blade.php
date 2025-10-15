@props(['liftLog'])

<td class="hide-on-mobile">
    <x-lift-weight-display :liftLog="$liftLog" /><br>
    <x-lift-reps-sets-display :reps="$liftLog->display_reps" :sets="$liftLog->display_rounds" />
</td>