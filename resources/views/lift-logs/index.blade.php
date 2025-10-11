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

        @if ($liftLogs->isEmpty())
            <p>No lift logs found. Add one to get started!</p>
        @else
        <x-lift-logs-table :liftLogs="$liftLogs->reverse()" />

        <div class="form-container">
            <h3>TSV Export</h3>
            <textarea id="tsv-output" rows="10" style="width: 100%; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;">@foreach ($liftLogs as $liftLog)
{{ $liftLog->logged_at->format('m/d/Y') }} 	 {{ $liftLog->logged_at->format('H:i') }} 	 {{ $liftLog->exercise->title }} 	 {{ $liftLog->display_weight }} 	 {{ $liftLog->display_reps }} 	 {{ $liftLog->display_rounds }} 	 {{ preg_replace('/(
|
)+/', ' ', $liftLog->comments) }}
@endforeach
            </textarea>
            <button id="copy-tsv-button" class="button">Copy to Clipboard</button>
        </div>

        @endif

        @if (!app()->environment('production'))
        <div class="form-container">
            <h3>TSV Import</h3>
            <form action="{{ route('lift-logs.import-tsv') }}" method="POST">
                @csrf
                <input type="hidden" name="date" value="{{ now()->format('Y-m-d') }}">
                <textarea name="tsv_data" rows="10" style="width: 100%; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;"></textarea>
                <button type="submit" class="button">Import TSV</button>
            </form>
        </div>
        @endif

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            const copyTsvButton = document.getElementById('copy-tsv-button');
            if (copyTsvButton) {
                copyTsvButton.addEventListener('click', function() {
                    var tsvOutput = document.getElementById('tsv-output');
                    tsvOutput.select();
                    document.execCommand('copy');
                    alert('TSV data copied to clipboard!');
                });
            }
        });
    </script>
@endsection