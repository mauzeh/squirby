<x-mail::message>
# Great job on your first lift of the day!

You've kicked off your training for the day by logging **{{ $liftLog->exercise->name }}**.

Keep up the great work!

<x-mail::button :url="route('lift-logs.show', $liftLog)">
View Your Lift
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
