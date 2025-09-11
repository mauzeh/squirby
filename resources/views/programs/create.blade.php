@extends('app')

@section('content')
    <h1>Create Program</h1>

    <form action="{{ route('programs.store') }}" method="POST">
        @csrf
        @include('programs._form')
        <button type="submit" class="btn btn-primary">Create</button>
    </form>
@endsection
