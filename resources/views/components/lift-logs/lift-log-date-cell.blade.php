@props(['liftLog', 'config'])

@if($config['hideExerciseColumn'])
    <td>
        {{ $liftLog['formatted_date'] }}
        <div class="show-on-mobile mobile-summary">
            <x-lift-logs.mobile-summary :liftLog="$liftLog['raw_lift_log']" />
        </div>
    </td>
@else
    <td class="hide-on-mobile">{{ $liftLog['formatted_date'] }}</td>
@endif