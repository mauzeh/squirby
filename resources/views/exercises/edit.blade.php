@extends('app')

@section('content')
    <div class="container">
        <h1>Edit Exercise</h1>

        @if ($errors->any())
            <div class="error-message">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        
        <x-exercise-form-component 
            :exercise="$exercise"
            :canCreateGlobal="$canCreateGlobal"
            :action="route('exercises.update', $exercise->id)"
            method="PUT" />
    </div>
@endsection
