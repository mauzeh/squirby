@extends('app')

@section('content')
    <div class="container">
        <h1>Edit Program Entry</h1>

        <div class="form-container">
            <form action="{{ route('programs.update', $program->id) }}" method="POST">
                @csrf
                @method('PUT')
                @include('programs._form')
                <button type="submit" class="button create">Update Program Entry</button>
            </form>
        </div>
    </div>
@endsection
