@extends('app')

@section('content')
<div class="container">
    <h1>Create Workout Template</h1>
    
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
        <form method="POST" action="{{ route('workout-templates.store') }}">
            @csrf
            @include('workout-templates._form', [
                'submitButtonText' => 'Create Template',
                'template' => null,
                'selectedExercises' => session('template_exercises', [])
            ])
        </form>
    </div>
</div>
@endsection
