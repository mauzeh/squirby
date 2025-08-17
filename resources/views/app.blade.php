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
            .content {
                padding: 20px;
            }
        </style>
    </head>
    <body>
        <div class="navbar">
            <a href="{{ route('daily_logs.index') }}">Daily Log</a>
            <a href="{{ route('ingredients.index') }}">Ingredient Admin</a>
        </div>
        <div class="content">
            @yield('content')
        </div>
    </body>
</html>
