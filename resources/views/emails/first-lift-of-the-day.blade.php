<x-mail::message>
# Hello, {{ $liftLog->user->name }}!

@php
$firstSet = $liftLog->liftSets->first();
$weight = $firstSet->weight ?? 0;
$reps = $firstSet->reps ?? 0;
$bandColor = $firstSet->band_color ?? null;
$rounds = $liftLog->liftSets->count();
$strategy = $liftLog->exercise->getTypeStrategy();
$workoutDescription = $strategy->formatSuccessMessageDescription($weight, $reps, $rounds, $bandColor);
@endphp

You've kicked off your training for the day by logging:

# **{{ $liftLog->exercise->getDisplayNameForUser($liftLog->user) }}**
**{{ $workoutDescription }}**

Keep up the great work!

<x-mail::button :url="route('exercises.show-logs', $liftLog->exercise)">
View Your Lift
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}

<br>
<small>Environment file: {{ $environmentFile }}</small>
</x-mail::message>
