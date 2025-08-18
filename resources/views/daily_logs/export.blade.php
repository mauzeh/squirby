@extends('app')

@section('content')
    <div class="container">
        <h1>Export Daily Logs</h1>

        @if ($errors->any())
            <div class="error-message">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="form-container">
            <form action="{{ route('export') }}" method="POST" id="export-form">
                @csrf
                <div class="form-group">
                    <label for="start_date">Start Date:</label>
                    <input type="date" name="start_date" id="start_date" value="{{ old('start_date') }}" required>
                </div>
                <div class="form-group">
                    <label for="end_date">End Date:</label>
                    <input type="date" name="end_date" id="end_date" value="{{ old('end_date') }}" required>
                </div>
                <button type="submit" class="button">Export</button>
            </form>
        </div>

        <div class="form-container">
            <form action="{{ route('export-all') }}" method="POST" id="export-all-form">
                @csrf
                <button type="submit" class="button">Export All Logs</button>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('export-form').addEventListener('submit', function(e) {
            e.preventDefault();

            var form = e.target;
            var formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.blob())
            .then(blob => {
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'daily_log_' + formData.get('start_date') + '_to_' + formData.get('end_date') + '.csv';
                document.body.appendChild(a);
                a.click();
                a.remove();
            });
        });

        document.getElementById('export-all-form').addEventListener('submit', function(e) {
            e.preventDefault();

            var form = e.target;
            var formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.blob())
            .then(blob => {
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'daily_log_all.csv';
                document.body.appendChild(a);
                a.click();
                a.remove();
            });
        });
    </script>
    @endpush
@endsection
