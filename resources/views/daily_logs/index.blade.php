@extends('app')

@section('content')
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
            <h3>Add New Entry</h3>
            <form action="{{ route('daily-logs.store') }}" method="POST">
                @csrf
                <div class="form-row">
                    <label for="date">Date:</label>
                    <x-date-select name="date" id="date" :selectedDate="$selectedDate->format('Y-m-d')" required />
                </div>
                <div class="form-row">
                    <label for="logged_at">Time:</label>
                    <x-time-select name="logged_at" id="logged_at" required />
                </div>
                <div class="form-row">
                    <label for="ingredient_id">Ingredient:</label>
                    <x-ingredient-select name="ingredient_id" id="ingredient_id" :ingredients="$ingredients" :selected="old('ingredient_id')" required />
                </div>
                <div class="form-row">
                    <label for="quantity">Quantity:</label>
                    <x-quantity-input name="quantity" id="quantity" :value="old('quantity', 1)" required />
                    @error('quantity')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-row">
                    <label for="notes">Notes:</label>
                    <input type="text" name="notes" id="notes" value="{{ old('notes') }}">
                    @error('notes')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                <button type="submit" class="button">Add Log Entry</button>
            </form>
        </div>

        <div class="form-container">
            <h3>Add Meal to Log</h3>
            <form action="{{ route('daily-logs.add-meal') }}" method="POST">
                @csrf
                <div class="form-row">
                    <label for="meal_date">Date:</label>
                    <x-date-select name="meal_date" id="meal_date" :selectedDate="$selectedDate->format('Y-m-d')" required />
                </div>
                <div class="form-row">
                    <label for="logged_at_meal">Time:</label>
                    <x-time-select name="logged_at_meal" id="logged_at_meal" required />
                </div>
                <div class="form-row">
                    <label for="meal_id">Meal:</label>
                    <select name="meal_id" id="meal_id" required>
                        <option value="">Select a Meal</option>
                        @foreach ($meals as $meal)
                            <option value="{{ $meal->id }}">{{ $meal->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <label for="portion">Portion:</label>
                    <input type="text" name="portion" id="portion" step="0.05" min="0.05" value="1.0" required inputmode="decimal">
                </div>
                <div class="form-row">
                    <label for="notes_meal">Notes:</label>
                    <input type="text" name="notes" id="notes_meal" value="{{ old('notes') }}">
                </div>
                <button type="submit" class="button">Add Meal to Log</button>
            </form>
        </div>
    </div>

    <div class="container">
        <h2>Select Date</h2>
        <div class="date-navigation">
            @php
                $today = \Carbon\Carbon::today();
            @endphp
            @for ($i = -3; $i <= 1; $i++)
                @php
                    $date = $today->copy()->addDays($i);
                    $dateString = $date->toDateString();
                @endphp
                <a href="{{ route('daily-logs.index', ['date' => $dateString]) }}" class="date-link {{ $selectedDate->toDateString() == $dateString ? 'active' : '' }}">
                    {{ $date->format('D M d') }}
                </a>
            @endfor
            <div class="form-group" style="margin-left: 20px;">
                <label for="date_picker">Or Pick a Date:</label>
                <input type="date" id="date_picker" onchange="window.location.href = '{{ route('daily-logs.index') }}?date=' + this.value;" value="{{ $selectedDate->format('Y-m-d') }}">
            </div>
        </div>
    </div>

    <div class="container">
        <h2>Log Entries for {{ $selectedDate->format('M d, Y') }}</h2>
        @if ($dailyLogs->isEmpty())
            <p>No entries for this day.</p>
        @else
            <table class="log-entries-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-logs"></th>
                        <th>Time</th>
                        <th>Ingredient</th>
                        <th>Quantity</th>
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
                    @foreach ($dailyLogs as $log)
                        <tr>
                            <td><input type="checkbox" name="daily_log_ids[]" value="{{ $log->id }}" class="log-checkbox"></td>
                            <td>{{ $log->logged_at->format('H:i') }}</td>
                            <td>
                                {{ $log->ingredient->name }}
                                @if($log->notes)
                                    <br><small style="font-size: 0.8em; color: #aaa;">{{ $log->notes }}</small>
                                @endif
                            </td>
                            <td>{{ $log->quantity }} {{ $log->unit->abbreviation }}</td>
                            <td class="hide-on-mobile">{{ round($nutritionService->calculateTotalMacro($log->ingredient, 'calories', (float)$log->quantity)) }}</td>
                            <td class="hide-on-mobile">{{ round($nutritionService->calculateTotalMacro($log->ingredient, 'fats', (float)$log->quantity), 1) }}</td>
                            <td class="hide-on-mobile">{{ round($nutritionService->calculateTotalMacro($log->ingredient, 'carbs', (float)$log->quantity), 1) }}</td>
                            <td class="hide-on-mobile">{{ round($nutritionService->calculateTotalMacro($log->ingredient, 'protein', (float)$log->quantity), 1) }}</td>
                            <td class="hide-on-mobile">{{ round($nutritionService->calculateTotalMacro($log->ingredient, 'sodium', (float)$log->quantity), 1) }}</td>
                            <td class="hide-on-mobile">{{ number_format($nutritionService->calculateCostForQuantity($log->ingredient, (float)$log->quantity), 2) }}</td>
                            <td class="actions-column">
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('daily-logs.edit', $log->id) }}" class="button edit">Edit</a>
                                    <form action="{{ route('daily-logs.destroy', $log->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this log entry?');">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" style="text-align:left; font-weight:normal;">
                            <form action="{{ route('daily-logs.destroy-selected') }}" method="POST" id="delete-selected-form" onsubmit="return confirm('Are you sure you want to delete the selected log entries?');" style="display:inline;">
                                @csrf
                                <button type="submit" class="button delete">Delete Selected Logs</button>
                            </form>
                        </th>
                        <th style="text-align:right; font-weight:bold;">Total:</th>
                        <td style="font-weight:bold;">{{ round($dailyTotals['calories']) }}</td>
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

            <div class="form-container">
                <h3>TSV Export</h3>
                <textarea id="tsv-output" rows="10" style="width: 100%; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;">@foreach ($dailyLogs->reverse() as $log)
{{ $log->logged_at->format('m/d/Y') }}	{{ $log->logged_at->format('H:i') }}	{{ $log->ingredient->name }}	{{ $log->notes }}	{{ $log->quantity }}
@endforeach
                </textarea>
                <button id="copy-tsv-button" class="button">Copy to Clipboard</button>
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
                        alert('Please select at least one log entry to create a meal.');
                        return;
                    }

                    checkedLogs.forEach(function(checkbox) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'daily_log_ids[]';
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
                        alert('Please select at least one log entry to delete.');
                        return;
                    }

                    checkedLogs.forEach(function(checkbox) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'daily_log_ids[]';
                        input.value = checkbox.value;
                        form.appendChild(input);
                    });

                    form.submit();
                });

                document.getElementById('copy-tsv-button').addEventListener('click', function() {
                    var tsvOutput = document.getElementById('tsv-output');
                    tsvOutput.select();
                    document.execCommand('copy');
                    alert('TSV data copied to clipboard!');
                });
            </script>
        @endif
    </div>

    <div class="container">
        <div class="form-container">
            <h3>TSV Import</h3>
            <form action="{{ route('daily-logs.import-tsv') }}" method="POST">
                @csrf
                <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">
                <textarea name="tsv_data" rows="10" style="width: 100%; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;"></textarea>
                <button type="submit" class="button">Import TSV</button>
            </form>
        </div>
    </div>


    <div class="container meal-groups-container">
        @foreach($groupedLogs->sortKeys() as $time => $logs)
                <div class="meal-group">
                    @php
                        $mealTotals = $nutritionService->calculateDailyTotals($logs);
                    @endphp
                    <x-nutrition-facts-label :totals="$mealTotals" :title="\Carbon\Carbon::parse($time)->format('H:i')" />
                </div>
            @endforeach
    </div>

    <div class="container">
        <x-nutrition-facts-label :totals="$dailyTotals" title="Today's Totals:" class="main-totals" />
    </div>
@endsection
