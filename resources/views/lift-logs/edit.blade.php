@extends('app')

@section('content')
    <div class="container">
        <h1>Edit Lift Log - {{ $liftLog->exercise->title }}</h1>
        
        <x-lift-log-form-component 
            :liftLog="$liftLog"
            :exercises="$exercises"
            :action="route('lift-logs.update', $liftLog->id)"
            method="PUT" />
    </div>

@endsection
