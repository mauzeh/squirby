@props(['liftLog', 'config'])

@if($config['hideExerciseColumn'])
    <td>
        {{ $liftLog['formatted_date'] }}
        <div class="show-on-mobile mobile-summary">
            {!! $liftLog['mobile_summary'] !!}
        </div>
    </td>
@else
    <td class="hide-on-mobile">{{ $liftLog['formatted_date'] }}</td>
@endif