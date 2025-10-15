@props(['liftLogs', 'config'])

<tbody>
    @foreach ($liftLogs as $liftLog)
        <x-lift-log-row :liftLog="$liftLog" :config="$config" />
    @endforeach
</tbody>