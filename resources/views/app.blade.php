<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon/favicon-16x16.png') }}">
        <link rel="manifest" href="{{ asset('favicon/site.webmanifest') }}">
        <link rel="shortcut icon" href="{{ asset('favicon/favicon.ico') }}">

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
                padding: 20px; /* Add horizontal padding */
            }
            .container {
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
            .macro-totals-table {
                float: left;
                width: auto;
                margin-left: auto;
                margin-right: auto;
            }
            .form-container {
                margin: 20px 0; /* Left-align the form and add vertical margin */
                padding: 20px;
                background-color: #2a2a2a; /* Slightly lighter background for the form */
                border-radius: 8px;
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
            .form-group {
                display: flex;
                align-items: center;
                margin-bottom: 10px;
            }
            .form-group label {
                flex: 0 0 120px; /* Fixed width for labels */
                margin-right: 10px;
                text-align: right;
                color: #f2f2f2;
            }
            .form-group input[type="text"],
            .form-group input[type="number"],
            .form-group select {
                flex: 1; /* Take remaining space */
                padding: 8px;
                border-radius: 5px;
                background-color: #3a3a3a;
                color: #f2f2f2;
                border: 1px solid #555;
            }
            .forms-container-wrapper {
                display: flex;
                justify-content: space-around;
                gap: 20px;
                flex-wrap: wrap;
            }
            .form-container {
                flex: 1;
                min-width: 300px; /* Adjust as needed */
            }
            .form-container h3 {
                margin-top: 0; /* Remove top margin */
                margin-bottom: 15px; /* Add some bottom margin for spacing */
                color: #f2f2f2; /* Ensure consistent heading color */
            }
            .form-row {
                display: flex;
                flex-wrap: wrap; /* Allow items to wrap */
                align-items: flex-start; /* Align items to the top */
                margin-bottom: 10px;
            }
            .form-row label {
                flex: 0; /* Adjust label width */
                margin-right: 10px;
                text-align: right;
            }
            .form-row input[type="text"],
            .form-row input[type="number"],
            .form-row select {
                width: 100%; /* Take full width of parent */
                box-sizing: border-box; /* Include padding and border in width */
                padding: 8px;
                border-radius: 5px;
                background-color: #3a3a3a;
                color: #f2f2f2;
                border: 1px solid #555;
            }
            .success-message-box {
                background-color: #28a745; /* Darker green for success */
                color: white; /* White text */
                border: 1px solid #218838; /* Slightly darker green border */
                padding: 15px; /* Add padding */
                margin-bottom: 20px;
                border-radius: 5px; /* Slightly rounded corners */
            }

            .error-message-box {
                background-color: #dc3545; /* Red for error */
                color: white; /* White text */
                border: 1px solid #c82333; /* Slightly darker red border */
                padding: 15px; /* Add padding */
                margin-bottom: 20px;
                border-radius: 5px; /* Slightly rounded corners */
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

            @media (max-width: 768px) {
                .hide-on-mobile {
                    display: none;
                }
            }

            .nutrition-facts-label {
                border: 1px solid #555;
                padding: 10px;
                width: 300px;
                background-color: #2a2a2a;
                color: #f2f2f2;
                font-family: sans-serif;
            }
            .nutrition-facts-label h2 {
                font-size: 24px;
                margin: 0 0 5px 0;
                font-weight: bold;
                color: #f2f2f2;
            }
            .nutrition-facts-label .header {
                border-bottom: 10px solid #f2f2f2;
                padding-bottom: 5px;
            }
            .nutrition-facts-label .nutrient {
                display: flex;
                justify-content: space-between;
                padding: 2px 0;
                border-top: 1px solid #ccc;
            }
            .nutrition-facts-label .nutrient.main {
                font-weight: bold;
            }
            .nutrition-facts-label .nutrient.indented {
                margin-left: 20px;
            }
            .nutrition-facts-label .nutrient .label {
                font-weight: normal;
            }
            .nutrition-facts-label .nutrient .value {
                font-weight: bold;
            }

            .calories-label,
            .calories-value {
                font-size: 1.5em;
                font-weight: bold;
            }
            .cost-nutrient {
                background-color: #3a3a3a;
            }

            .meal-group {
                margin-bottom: 20px;
            }

            .meal-groups-container {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
            }

            .nutrition-facts-label.main-totals {
                border: 2px solid #00ff00;
                box-shadow: 0 0 15px rgba(0, 255, 0, 0.2);
                background-color: #1a1a1a;
            }

            .nutrition-facts-label.main-totals h2,
            .nutrition-facts-label.main-totals .nutrient {
                color: #00ff00;
            }

            .nutrition-facts-label.main-totals .header {
                border-bottom: 10px solid #00ff00;
            }

            .nutrition-facts-label.main-totals .nutrient {
                border-top-color: #00ff00;
            }

            footer {
                background-color: #333;
                color: #ccc;
                padding: 20px 0;
                margin-top: 40px;
            }
            .git-log {
                white-space: pre-wrap;
                margin: 0 20px;
                font-size: 0.8em;
            }
        </style>
    </head>
    <body>
        @if(app()->environment('production') || app()->environment('staging'))
            <div style="background-color: red; color: white; text-align: center; padding: 10px; font-size: 20px; font-weight: bold;">
                PRODUCTION / STAGING
            </div>
        @else
            <div style="background-color: green; color: white; text-align: center; padding: 10px; font-size: 20px; font-weight: bold;">
                LOCAL DEV ENVIRONMENT
            </div>
        @endif
        <div class="navbar">
            <a href="{{ route('daily-logs.index') }}" class="{{ Request::routeIs('daily-logs.*') ? 'active' : '' }}">Daily Log</a>
            <a href="{{ route('meals.index') }}" class="{{ Request::routeIs('meals.*') ? 'active' : '' }}">Meals</a>
            <a href="{{ route('ingredients.index') }}" class="{{ Request::routeIs('ingredients.*') ? 'active' : '' }}">Ingredients</a>
            <a href="{{ route('export-form') }}" class="{{ Request::routeIs('export-form') ? 'active' : '' }}">Export</a>
            <a href="{{ route('exercises.index') }}" class="{{ Request::routeIs('exercises.*') ? 'active' : '' }}">Exercises</a>
        </div>
        <div class="content">
            @yield('content')
        </div>
        <footer>
            <div class="container">
                @if(isset($gitLog))
                    <pre class="git-log">{{ $gitLog }}</pre>
                @endif
            </div>
        </footer>
    </body>
</html>
