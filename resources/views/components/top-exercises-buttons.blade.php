@props(['exercises', 'currentExerciseId' => null])

<div class="container">
    <div class="top-exercises-container">
        @foreach ($exercises as $exercise)
            <a href="{{ route('exercises.show-logs', ['exercise' => $exercise->id]) }}" 
               class="button {{ $currentExerciseId == $exercise->id ? 'active' : '' }}">
                {{ $exercise->title }}
            </a>
        @endforeach
        <a href="{{ route('exercises.index') }}" class="button demure">More...</a>
    </div>
</div>