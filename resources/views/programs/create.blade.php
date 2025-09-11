@extends('app')

@section('content')
    <h1>Create Program</h1>

    @if ($errors->any())
        <div class="error-message">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('programs.store') }}" method="POST">
        @csrf
        @include('programs._form')
        <button type="submit" class="btn btn-primary">Create</button>
    </form>
@endsection
