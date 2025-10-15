@props(['liftLogs', 'config'])

<tbody>
    @foreach ($liftLogs as $liftLog)
        <x-lift-logs.lift-log-row :liftLog="$liftLog" :config="$config" />
    @endforeach
</tbody>