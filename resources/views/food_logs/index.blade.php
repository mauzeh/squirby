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


        <div class="form-container">
            <h3>Export Options</h3>
            <h4>Export All Food Logs</h4>
            <form action="{{ route('food-logs.export-all') }}" method="POST" id="export-all-form">
                @csrf
                <button type="submit" class="button">Export All Food Logs</button>
            </form>

            <h4>Export Date Range</h4>
            <form action="{{ route('food-logs.export') }}" method="POST" id="export-form">
                @csrf
                <div class="form-group">
                    <label for="start_date">Start Date:</label>
                    <input type="date" name="start_date" id="start_date" value="{{ old('start_date', $selectedDate->format('Y-m-d')) }}" required>
                </div>
                <div class="form-group">
                    <label for="end_date">End Date:</label>
                    <input type="date" name="end_date" id="end_date" value="{{ old('end_date', $selectedDate->format('Y-m-d')) }}" required>
                </div>
                <button type="submit" class="button">Export</button>
            </form>
        </div>
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
                        <th class="hide-on-mobile">Calories</th>
                        <th class="hide-on-mobile">Fats (g)</th>
                        <th class="hide-on-mobile">Carbs (g)</th>
                        <th class="hide-on-mobile">Protein (g)</th>
                        <th class="hide-on-mobile">Sodium (mg)</th>
                        <th class="hide-on-mobile">Cost</th>
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
                            <td class="hide-on-mobile">{{ round($nutritionService->calculateTotalMacro($log->ingredient, 'calories', (float)$log->quantity)) }}</td>
                            <td class="hide-on-mobile">{{ round($nutritionService->calculateTotalMacro($log->ingredient, 'fats', (float)$log->quantity), 1) }}</td>
                            <td class="hide-on-mobile">{{ round($nutritionService->calculateTotalMacro($log->ingredient, 'carbs', (float)$log->quantity), 1) }}</td>
                            <td class="hide-on-mobile">{{ round($nutritionService->calculateTotalMacro($log->ingredient, 'protein', (float)$log->quantity), 1) }}</td>
                            <td class="hide-on-mobile">{{ round($nutritionService->calculateTotalMacro($log->ingredient, 'sodium', (float)$log->quantity), 1) }}</td>
                            <td class="hide-on-mobile">{{ number_format($nutritionService->calculateCostForQuantity($log->ingredient, (float)$log->quantity), 2) }}</td>
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
                <tfoot>
                    <tr>
                        <th colspan="2" style="text-align:left; font-weight:normal;">
                            <form action="{{ route('food-logs.destroy-selected') }}" method="POST" id="delete-selected-form" onsubmit="return confirm('Are you sure you want to delete the selected food log entries?');" style="display:inline;">
                                @csrf
                                <button type="submit" class="button delete"><i class="fa-solid fa-trash"></i> Delete Selected</button>
                            </form>
                        </th>
                        <th class="hide-on-mobile"></th> {{-- Empty header for the hidden Quantity column --}}
                        <th colspan="2" style="text-align:right; font-weight:bold;">Total: {{ round($dailyTotals['calories']) }}</th>
                        <td class="hide-on-mobile" style="font-weight:bold;">{{ round($dailyTotals['fats']) }}</td>
                        <td class="hide-on-mobile" style="font-weight:bold;">{{ round($dailyTotals['carbs']) }}</td>
                        <td class="hide-on-mobile" style="font-weight:bold;">{{ round($dailyTotals['protein']) }}</td>
                        <td class="hide-on-mobile" style="font-weight:bold;">{{ round($dailyTotals['sodium']) }}</td>
                        <td class="hide-on-mobile" style-="font-weight:bold;">{{ number_format($dailyTotals['cost'], 2) }}</td>
                        <td class="hide-on-mobile"></td>
                    </tr>
                </tfoot>
            </table>

            <div class="form-container">
                <h3>Create Meal from Selection</h3>
                <form action="{{ route('meals.create-from-logs') }}" method="POST" id="create-meal-form">
                    @csrf
                    <div class="form-group">
                        <label for="meal_name">Meal Name:</label>
                        <input type="text" name="meal_name" id="meal_name" placeholder="Enter meal name" required>
                    </div>
                    <button type="submit" class="button">Create Meal</button>
                </form>
            </div>

            <script>
                document.getElementById('select-all-logs').addEventListener('change', function(e) {
                    document.querySelectorAll('.log-checkbox').forEach(function(checkbox) {
                        checkbox.checked = e.target.checked;
                    });
                });

                document.getElementById('create-meal-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    var form = e.target;
                    var checkedLogs = document.querySelectorAll('.log-checkbox:checked');

                    if (checkedLogs.length === 0) {
                        alert('Please select at least one food log entry to create a meal.');
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
                        a.download = 'food_log_all.csv';
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                    });
                });

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
                        a.download = 'food_log_' + formData.get('start_date') + '_to_' + formData.get('end_date') + '.csv';
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                    });
                });
            </script>
        @endif
    </div>




    <div class="container meal-groups-container">
        @foreach($groupedLogs->sortKeys() as $time => $logs)
                <div class="meal-group">
                    @php
                        $mealTotals = $nutritionService->calculateFoodLogTotals($logs);
                    @endphp
                    <x-nutrition-facts-label :totals="$mealTotals" :title="\Carbon\Carbon::parse($time)->format('H:i')" />
                </div>
            @endforeach
    </div>

    <div class="container">
        <x-nutrition-facts-label :totals="$dailyTotals" title="Today's Totals:" class="main-totals" />
    </div>


@endsection