@props(['liftLog', 'config'])

<tr>
    <x-lift-log-checkbox-cell :liftLog="$liftLog" />
    <x-lift-log-date-cell :liftLog="$liftLog" :config="$config" />
    @unless($config['hideExerciseColumn'])
        <x-lift-log-exercise-cell :liftLog="$liftLog" />
    @endunless
    <x-lift-log-weight-cell :liftLog="$liftLog" />
    <x-lift-log-1rm-cell :liftLog="$liftLog" />
    <x-lift-log-comments-cell :liftLog="$liftLog" />
    <x-lift-log-actions-cell :liftLog="$liftLog" />
</tr>