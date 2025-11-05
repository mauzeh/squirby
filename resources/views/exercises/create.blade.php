@extends('app')

@section('content')
    <div class="container">
        <h1>Add Exercise</h1>

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
            :canCreateGlobal="$canCreateGlobal"
            :action="route('exercises.store')"
            method="POST" />
    </div>
@endsection
