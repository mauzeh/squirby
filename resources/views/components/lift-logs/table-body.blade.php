@props(['liftLogs', 'config'])

<tbody>
    @foreach ($liftLogs as $liftLog)
        <x-lift-logs.row :liftLog="$liftLog" :config="$config" />
    @endforeach
</tbody>