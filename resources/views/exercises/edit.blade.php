@extends('app')

@section('content')
    <div class="container">
        <h1>Edit Exercise</h1>
        
        <x-exercise-form-component 
            :exercise="$exercise"
            :canCreateGlobal="$canCreateGlobal"
            :action="route('exercises.update', $exercise->id)"
            method="PUT" />
    </div>
@endsection
