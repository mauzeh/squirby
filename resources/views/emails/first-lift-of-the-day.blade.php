<x-mail::message>
# Hello, {{ $liftLog->user->name }}!

You've kicked off your training for the day by logging **{{ $liftLog->exercise->getDisplayNameForUser($liftLog->user) }}**.

Keep up the great work!

<x-mail::button :url="route('exercises.show-logs', $liftLog->exercise)">
View Your Lift
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}

<br>
<small>Environment file: {{ $environmentFile }}</small>
</x-mail::message>
