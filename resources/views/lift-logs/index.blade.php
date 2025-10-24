@extends('app')

@section('content')

<x-top-exercises-buttons :exercises="$displayExercises" :allExercises="$exercises" /> 


    @if (session('success'))
        <div class="container success-message-box">
            {!! session('success') !!}
        </div>
    @endif
    @if (session('error'))
        <div class="container error-message-box">
            {!! session('error') !!}
        </div>
    @endif
    <div class="container">

        @if (collect($liftLogs)->isEmpty())
            <p>No lift logs found. Add one to get started!</p>
        @else
        <x-lift-logs.table :liftLogs="collect($liftLogs)->reverse()" :config="$config" />

        @endif

    </div>
@endsection
@endsection