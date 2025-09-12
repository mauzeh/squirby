@extends('app')

@section('content')
    <h1>Add Program Entry</h1>

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
        <button type="submit" class="button create">Add Program Entry</button>
    </form>
@endsection
