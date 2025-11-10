@extends('app')

@section('content')
<div class="container">
    <h1>Edit Workout Template</h1>
    
    @if ($errors->any())
        <div class="error-message">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    
    <div class="form-container">
        <form method="POST" action="{{ route('workout-templates.update', $workoutTemplate) }}">
            @csrf
            @method('PUT')
            @include('workout-templates._form', [
                'submitButtonText' => 'Update Template',
                'template' => $workoutTemplate,
                'selectedExercises' => session('template_exercises', $workoutTemplate->exercises->pluck('id')->toArray())
            ])
        </form>
    </div>
</div>
@endsection
