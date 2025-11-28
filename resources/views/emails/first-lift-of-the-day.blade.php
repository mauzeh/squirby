<x-mail::message>
# Great job on your first lift of the day!

You've kicked off your training for the day by logging **{{ $liftLog->exercise->getDisplayNameForUser($liftLog->user) }}**.

Keep up the great work!

<x-mail::button :url="route('exercises.show-logs', $liftLog->exercise)">
View Your Lift
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
