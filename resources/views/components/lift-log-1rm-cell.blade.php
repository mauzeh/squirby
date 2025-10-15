@props(['liftLog'])

<td class="hide-on-mobile">
    @if ($liftLog->exercise->is_bodyweight)
        {{ round($liftLog->one_rep_max) }} lbs (est. incl. BW)
    @else
        {{ round($liftLog->one_rep_max) }} lbs
    @endif
</td>