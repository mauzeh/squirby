@props(['liftLog', 'config'])

@if($config['hideExerciseColumn'])
    <td>
        {{ $liftLog->logged_at->format('m/d') }}
        <div class="show-on-mobile mobile-summary">
            <x-lift-weight-display :liftLog="$liftLog" /> (<x-lift-reps-sets-display :reps="$liftLog->display_reps" :sets="$liftLog->display_rounds" />)
            @if ($liftLog->one_rep_max)
                <br><i>1RM: {{ round($liftLog->one_rep_max) }} lbs</i>
            @endif
        </div>
    </td>
@else
    <td class="hide-on-mobile">{{ $liftLog->logged_at->format('m/d') }}</td>
@endif