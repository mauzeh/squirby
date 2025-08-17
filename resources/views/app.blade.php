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
                background-color: #1a1a1a; /* Dark background */
                color: #f2f2f2; /* Light text */
                margin: 0;
                padding: 0;
            }
            .navbar {
                background-color: #333; /* Darker navbar */
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
                background-color: #555; /* Lighter hover for dark theme */
                color: white;
            }
            .navbar a.active {
                background-color: #007bff; /* A blue for active, stands out on dark */
                color: white;
            }
            .content {
                padding: 20px;
            }
            .container {
                background-color: #2c2c2c; /* Darker container background */
                color: #f2f2f2; /* Light text */
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); /* Darker shadow */
                width: 100%;
                max-width: 800px; /* Adjusted max-width for better layout */
                margin-bottom: 20px;
            }
            h1, h2 {
                color: #f2f2f2; /* Light headings */
            }
            p {
                color: #ccc; /* Slightly darker light text for paragraphs */
            }
            .log-entries-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            .log-entries-table th,
            .log-entries-table td {
                border: 1px solid #444; /* Darker borders */
                padding: 8px;
                text-align: left;
            }
            .log-entries-table th {
                background-color: #3a3a3a; /* Darker table header */
                color: #f2f2f2; /* Light text */
            }
            .button {
                display: inline-block;
                background-color: #007bff; /* Blue for primary actions */
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
                background-color: #ffc107; /* Yellow for edit */
                color: #333; /* Dark text for yellow button */
            }
            .button.delete {
                background-color: #dc3545; /* Red for delete */
            }
            .button:hover {
                background-color: #0056b3; /* Darker blue on hover */
            }
            .button.edit:hover {
                background-color: #e0a800; /* Darker yellow on hover */
            }
            .button.delete:hover {
                background-color: #c82333; /* Darker red on hover */
            }
            .actions-column {
                width: 150px;
            }
            /* Form specific styles for dark theme */
            .form-group label {
                color: #f2f2f2;
            }
            input[type="text"],
            input[type="number"],
            select {
                background-color: #3a3a3a;
                color: #f2f2f2;
                border: 1px solid #555;
            }
            input[type="text"]:focus,
            input[type="number"]:focus,
            select:focus {
                border-color: #007bff;
                outline: none;
            }
            .error-message {
                color: #ff4d4d; /* Lighter red for errors */
            }
            .back-button {
                background-color: #6c757d;
                color: white;
            }
            .back-button:hover {
                background-color: #5a6268;
            }
            .date-navigation {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-top: 10px;
                justify-content: center;
            }
            .date-link {
                background-color: #3a3a3a;
                color: #f2f2f2;
                padding: 8px 12px;
                border-radius: 5px;
                text-decoration: none;
                transition: background-color 0.3s ease;
            }
            .date-link:hover {
                background-color: #555;
            }
            .date-link.active {
                background-color: #007bff;
                color: white;
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
