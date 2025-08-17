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

        <h2>Add New Entry</h2>
        <form action="{{ route('daily_logs.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="ingredient_id">Ingredient:</label>
                <select name="ingredient_id" id="ingredient_id" required>
                    <option value="">Select an Ingredient</option>
                    @foreach ($ingredients as $ingredient)
                        <option value="{{ $ingredient->id }}">{{ $ingredient->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="unit_id">Unit:</label>
                <select name="unit_id" id="unit_id" required>
                    <option value="">Select a Unit</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->abbreviation }})</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="quantity">Quantity:</label>
                <input type="number" name="quantity" id="quantity" step="0.01" min="0.01" required>
            </div>
            <button type="submit">Add Log Entry</button>
        </form>
    </div>

    <div class="container">
        <h2>Select Date</h2>
        <div class="date-navigation">
            @foreach ($availableDates as $date)
                <a href="{{ route('daily_logs.index', ['date' => $date]) }}" class="date-link {{ $selectedDate->toDateString() == $date ? 'active' : '' }}">{{ \Carbon\Carbon::parse($date)->format('M d') }}</a>
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
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dailyLogs as $log)
                        <tr>
                            <td>{{ $log->created_at->format('H:i') }}</td>
                            <td>{{ $log->ingredient->name }}</td>
                            <td>{{ $log->quantity }}</td>
                            <td>{{ $log->unit->abbreviation }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="container">
        <h2>Daily Macro Totals for {{ $selectedDate->format('M d, Y') }}</h2>
        <table class="totals-table">
            <thead>
                <tr>
                    <th>Calories</th>
                    <th>Protein (g)</th>
                    <th>Carbs (g)</th>
                    <th>Added Sugars (g)</th>
                    <th>Fats (g)</th>
                    <th>Sodium (mg)</th>
                    <th>Iron (mg)</th>
                    <th>Potassium (mg)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ round($dailyTotals['calories']) }}</td>
                    <td>{{ round($dailyTotals['protein']) }}</td>
                    <td>{{ round($dailyTotals['carbs']) }}</td>
                    <td>{{ round($dailyTotals['added_sugars']) }}</td>
                    <td>{{ round($dailyTotals['fats']) }}</td>
                    <td>{{ round($dailyTotals['sodium']) }}</td>
                    <td>{{ round($dailyTotals['iron']) }}</td>
                    <td>{{ round($dailyTotals['potassium']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
@endsection
