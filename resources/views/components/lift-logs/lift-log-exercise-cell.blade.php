@props(['liftLog'])

<td>
    <a href="{{ $liftLog['exercise_url'] }}">{{ $liftLog['exercise_title'] }}</a>
    <div class="show-on-mobile mobile-summary">
        {!! $liftLog['mobile_summary'] !!}
    </div>
</td>