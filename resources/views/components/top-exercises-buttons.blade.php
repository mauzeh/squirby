@props(['exercises', 'allExercises', 'currentExerciseId' => null])

<div class="container">
    <div class="top-exercises-container">
        @foreach ($exercises as $exercise)
            <a href="{{ route('exercises.show-logs', ['exercise' => $exercise->id]) }}" 
               class="button {{ $currentExerciseId == $exercise->id ? 'active' : '' }}">
                {{ $exercise->title }}
            </a>
        @endforeach

        <div class="dropdown">
            <button class="button demure dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                More...
            </button>
            <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                @php
                    $topExerciseIds = $exercises->pluck('id')->toArray();
                    $remainingExercises = $allExercises->reject(function ($exercise) use ($topExerciseIds) {
                        return in_array($exercise->id, $topExerciseIds);
                    });
                @endphp
                @foreach ($remainingExercises as $exercise)
                    <a class="dropdown-item" href="{{ route('exercises.show-logs', ['exercise' => $exercise->id]) }}">{{ $exercise->title }}</a>
                @endforeach
            </div>
        </div>
    </div>
</div>

<style>
    .dropdown {
        position: relative;
        display: inline-block;
    }

    .dropdown-menu {
        display: none;
        position: absolute;
        background-color: #2a2a2a;
        min-width: 160px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        z-index: 1;
        border-radius: 5px;
        padding: 5px 0;
    }

    .dropdown-menu a {
        color: #f2f2f2;
        padding: 8px 15px;
        text-decoration: none;
        display: block;
        text-align: left;
    }

    .dropdown-menu a:hover {
        background-color: #555;
    }

    .dropdown:hover .dropdown-menu {
        display: block;
    }
</style>