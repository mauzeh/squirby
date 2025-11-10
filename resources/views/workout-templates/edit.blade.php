@extends('app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h2>Edit Workout Template</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('workout-templates.update', $workoutTemplate) }}">
                        @csrf
                        @method('PUT')
                        @include('workout-templates._form', ['submitButtonText' => 'Update Template'])
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
