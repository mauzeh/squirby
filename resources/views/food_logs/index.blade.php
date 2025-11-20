@extends('app')

@section('content')
    <x-date-navigation :navigationData="$navigationData" />
    @if (session('success'))
        <div class="container success-message-box">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="container error-message-box">
            {{ session('error') }}
        </div>
    @endif
    <div class="container forms-container-wrapper">



    </div>

    <div class="container">
        <h2>Food Log Entries for {{ $selectedDate->format('M d, Y') }}</h2>
        @if ($foodLogs->isEmpty())
            <p>No entries for this day.</p>
        @else
            <table class="log-entries-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-logs"></th>
                        <th class="hide-on-mobile">Time</th>
                        <th>Ingredient</th>
                        <th class="hide-on-mobile">Quantity</th>

                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($foodLogs as $log)
                        <tr>
                            <td><input type="checkbox" name="food_log_ids[]" value="{{ $log->id }}" class="log-checkbox"></td>
                            <td class="hide-on-mobile">{{ $log->logged_at->format('H:i') }}</td>
                            <td>
                                {{ $log->ingredient->name }}
                                @if($log->notes)
                                    <br><small style="font-size: 0.8em; color: #aaa;">{{ $log->notes }}</small>
                                @endif
                                <div class="show-on-mobile" style="font-size: 0.9em; color: #ccc;">
                                    {{ $log->logged_at->format('H:i') }} - {{ $log->quantity }} {{ $log->unit->abbreviation }}
                                </div>
                            </td>
                            <td class="hide-on-mobile">{{ $log->quantity }} {{ $log->unit->abbreviation }}</td>

                            <td class="actions-column">
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('food-logs.edit', ['food_log' => $log, 'redirect_to' => 'food-logs.index']) }}" class="button edit"><i class="fa-solid fa-pencil"></i></a>
                                    <form action="{{ route('food-logs.destroy', $log->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this food log entry?');"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>

            </table>



            <script>
                document.getElementById('select-all-logs').addEventListener('change', function(e) {
                    document.querySelectorAll('.log-checkbox').forEach(function(checkbox) {
                        checkbox.checked = e.target.checked;
                    });
                });



                document.getElementById('delete-selected-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    var form = e.target;
                    var checkedLogs = document.querySelectorAll('.log-checkbox:checked');

                    if (checkedLogs.length === 0) {
                        alert('Please select at least one food log entry to delete.');
                        return;
                    }

                    checkedLogs.forEach(function(checkbox) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'food_log_ids[]';
                        input.value = checkbox.value;
                        form.appendChild(input);
                    });

                    form.submit();
                });


            </script>
        @endif
    </div>









@endsection