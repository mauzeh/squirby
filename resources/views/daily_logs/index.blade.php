<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Nutrition Log</title>
    <style>
        body {
            font-family: 'Figtree', sans-serif;
            background-color: #f0f2f5;
            color: #333;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 800px;
            margin-bottom: 20px;
        }
        h1, h2 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        select, input[type="number"] {
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #45a049;
        }
        .log-entry {
            border: 1px solid #eee;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            background-color: #f9f9f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .log-entry span {
            font-size: 1.1em;
        }
        .log-entry .quantity {
            font-weight: bold;
            color: #007bff;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .totals-table th, .totals-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .totals-table th {
            background-color: #f2f2f2;
        }
        .error-message {
            color: red;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
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
        <h2>Today's Log Entries</h2>
        @if ($todayLogs->isEmpty())
            <p>No entries for today yet. Add some above!</p>
        @else
            @foreach ($todayLogs as $log)
                <div class="log-entry">
                    <span>{{ $log->ingredient->name }}</span>
                    <span class="quantity">{{ $log->quantity }} {{ $log->unit->abbreviation }}</span>
                </div>
            @endforeach
        @endif
    </div>

    <div class="container">
        <h2>Daily Macro Totals</h2>
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
</body>
</html>
