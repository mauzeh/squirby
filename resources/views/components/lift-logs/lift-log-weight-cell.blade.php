@props(['liftLog'])

<td class="hide-on-mobile">
    <span style="font-size: 1.3em; font-weight: bold;">{{ $liftLog['formatted_weight'] }}</span><br>
    {{ $liftLog['formatted_reps_sets'] }}
</td>