@props(['liftLog', 'config'])

<tr>
    <x-lift-logs.checkbox-cell :liftLog="$liftLog" />
    <x-lift-logs.date-cell :liftLog="$liftLog" :config="$config" />
    @unless($config['hideExerciseColumn'])
        <x-lift-logs.exercise-cell :liftLog="$liftLog" />
    @endunless
    <x-lift-logs.weight-cell :liftLog="$liftLog" />
    <x-lift-logs.1rm-cell :liftLog="$liftLog" />
    <x-lift-logs.comments-cell :liftLog="$liftLog" />
    <x-lift-logs.actions-cell :liftLog="$liftLog" />
</tr>