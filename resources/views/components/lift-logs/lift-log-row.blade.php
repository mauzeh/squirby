@props(['liftLog', 'config'])

<tr>
    <x-lift-logs.lift-log-checkbox-cell :liftLog="$liftLog" />
    <x-lift-logs.lift-log-date-cell :liftLog="$liftLog" :config="$config" />
    @unless($config['hideExerciseColumn'])
        <x-lift-logs.lift-log-exercise-cell :liftLog="$liftLog" />
    @endunless
    <x-lift-logs.lift-log-weight-cell :liftLog="$liftLog" />
    <x-lift-logs.lift-log-1rm-cell :liftLog="$liftLog" />
    <x-lift-logs.lift-log-comments-cell :liftLog="$liftLog" />
    <x-lift-logs.lift-log-actions-cell :liftLog="$liftLog" />
</tr>