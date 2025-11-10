@extends('app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h2>Create Workout Template</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('workout-templates.store') }}">
                        @csrf
                        @include('workout-templates._form', ['submitButtonText' => 'Create Template'])
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
