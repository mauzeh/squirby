@extends('app')

@section('content')
    @if (session('success'))
        <div class="container" style="background-color: #d4edda; color: #155724; border-color: #c3e6cb; margin-bottom: 20px;">
            {{ session('success') }}
        </div>
    @endif
    <div class="container">
        <h1>Daily Nutrition Log</h1>

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
        <h2>Add New Entry</h2>
        <form action="{{ route('daily-logs.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="ingredient_id">Ingredient:</label>
                <select name="ingredient_id" id="ingredient_id" required>
                    <option value="">Select an Ingredient</option>
                    @foreach ($ingredients as $ingredient)
                        <option value="{{ $ingredient->id }}">{{ $ingredient->name }} ({{ $ingredient->baseUnit->abbreviation }})</option>
                    @endforeach
                </select>
            </div>
            
            <div class="form-group">
                <label for="logged_at">Time:</label>
                <input type="time" name="logged_at" id="logged_at" value="{{ now()->timezone(config('app.timezone'))->format('H:i') }}" required>
                &nbsp;
                <i>({{ config('app.timezone') }})</i>
            </div>
            <div class="form-group">
                <label for="quantity">Quantity:</label>
                <input type="number" name="quantity" id="quantity" step="0.01" min="0.01" required>
            </div>
            <button type="submit" class="button">Add Log Entry</button>
        </form>
        </div>
    </div>

    <div class="container">
        <h2>Select Date</h2>
        <div class="date-navigation">
            @foreach ($availableDates as $date)
                <a href="{{ route('daily-logs.index', ['date' => $date]) }}" class="date-link {{ $selectedDate->toDateString() == $date ? 'active' : '' }}">{{ \Carbon\Carbon::parse($date)->format('M d') }}</a>
            @endforeach
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
                        <th>Time</th>
                        <th>Ingredient</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Calories</th>
                        <th>Protein (g)</th>
                        <th>Carbs (g)</th>
                        <th>Fats (g)</th>
                        <th>Cost</th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dailyLogs as $log)
                        <tr>
                            <td>{{ $log->logged_at->format('H:i') }}</td>
                            <td>{{ $log->ingredient->name }}</td>
                            <td>{{ $log->quantity }}</td>
                            <td>{{ $log->unit->abbreviation }}</td>
                            <td>{{ round($nutritionService->calculateTotalMacro($log->ingredient, 'calories', $log->quantity)) }}</td>
                            <td>{{ round($nutritionService->calculateTotalMacro($log->ingredient, 'protein', $log->quantity), 1) }}</td>
                            <td>{{ round($nutritionService->calculateTotalMacro($log->ingredient, 'carbs', $log->quantity), 1) }}</td>
                            <td>{{ round($nutritionService->calculateTotalMacro($log->ingredient, 'fats', $log->quantity), 1) }}</td>
                            <td>{{ number_format($nutritionService->calculateCostForQuantity($log->ingredient, $log->quantity), 2) }}</td>
                            <td class="actions-column">
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('daily-logs.edit', $log->id) }}" class="button edit">Edit</a>
                                    <form action="{{ route('daily-logs.destroy', $log->id) }}" method="POST">
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
                        <th colspan="4" style="text-align:right; font-weight:bold;">Total:</th>
                        <td style="font-weight:bold;">{{ round($dailyTotals['calories']) }}</td>
                        <td style="font-weight:bold;">{{ round($dailyTotals['protein']) }}</td>
                        <td style="font-weight:bold;">{{ round($dailyTotals['carbs']) }}</td>
                        <td style="font-weight:bold;">{{ round($dailyTotals['fats']) }}</td>
                        <td style="font-weight:bold;">{{ number_format($dailyTotals['cost'], 2) }}</td>
                        <td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>

    <div class="container">
        <h2>Daily Macro Totals for {{ $selectedDate->format('M d, Y') }}</h2>
        <table class="log-entries-table macro-totals-table">
            <tbody>
                <tr>
                    <th>Calories</th>
                    <td>{{ round($dailyTotals['calories']) }}</td>
                </tr>
                <tr>
                    <th>Protein (g)</th>
                    <td>{{ round($dailyTotals['protein']) }}</td>
                </tr>
                <tr>
                    <th>Carbs (g)</th>
                    <td>{{ round($dailyTotals['carbs']) }}</td>
                </tr>
                <tr>
                    <th>Added Sugars (g)</th>
                    <td>{{ round($dailyTotals['added_sugars']) }}</td>
                </tr>
                <tr>
                    <th>Fats (g)</th>
                    <td>{{ round($dailyTotals['fats']) }}</td>
                </tr>
                <tr>
                    <th>Sodium (mg)</th>
                    <td>{{ round($dailyTotals['sodium']) }}</td>
                </tr>
                <tr>
                    <th>Iron (mg)</th>
                    <td>{{ round($dailyTotals['iron']) }}</td>
                </tr>
                <tr>
                    <th>Potassium (mg)</th>
                    <td>{{ round($dailyTotals['potassium']) }}</td>
                </tr>
                <tr>
                    <th>Total Cost</th>
                    <td>{{ number_format($dailyTotals['cost'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
@endsection
