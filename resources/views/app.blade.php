<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Nutrition Tracker</title>

        <!-- Styles -->
        <style>
            body {
                font-family: sans-serif;
                background-color: #f0f2f5;
                margin: 0;
                padding: 0;
            }
            .navbar {
                background-color: #333;
                overflow: hidden;
                width: 100%;
            }
            .navbar a {
                float: left;
                display: block;
                color: #f2f2f2;
                text-align: center;
                padding: 14px 16px;
                text-decoration: none;
                font-size: 17px;
            }
            .navbar a:hover {
                background-color: #ddd;
                color: black;
            }
            .navbar a.active {
                background-color: #04AA6D;
                color: white;
            }
            .content {
                padding: 20px;
            }
            .log-entries-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            .log-entries-table th,
            .log-entries-table td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            .log-entries-table th {
                background-color: #f2f2f2;
            }
            .button {
                display: inline-block;
                background-color: #4CAF50;
                color: white;
                padding: 8px 15px;
                border-radius: 5px;
                text-decoration: none;
                margin-top: 10px;
                margin-right: 5px;
                transition: background-color 0.3s ease;
                border: none;
                cursor: pointer;
                font-size: 16px;
            }
            .button.edit {
                background-color: #007bff;
            }
            .button.delete {
                background-color: #dc3545;
            }
            .button:hover {
                background-color: #45a049;
            }
            .button.edit:hover {
                background-color: #0056b3;
            }
            .button.delete:hover {
                background-color: #c82333;
            }
            .actions-column {
                width: 150px;
            }
        </style>
    </head>
    <body>
        <div class="navbar">
            <a href="{{ route('daily_logs.index') }}" class="{{ Request::routeIs('daily_logs.index') ? 'active' : '' }}">Daily Log</a>
            <a href="{{ route('ingredients.index') }}" class="{{ Request::routeIs('ingredients.*') ? 'active' : '' }}">Ingredient Admin</a>
        </div>
        <div class="content">
            @yield('content')
        </div>
    </body>
</html>
