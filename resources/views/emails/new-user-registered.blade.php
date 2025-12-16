<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New User Registration</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #007bff;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .content {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 8px 8px;
            border: 1px solid #dee2e6;
            border-top: none;
        }
        .user-info {
            background-color: white;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #007bff;
        }
        .info-row {
            margin: 10px 0;
        }
        .label {
            font-weight: bold;
            color: #495057;
        }
        .value {
            color: #212529;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 14px;
            color: #6c757d;
            text-align: center;
        }
        .timestamp {
            font-size: 12px;
            color: #868e96;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>New User Registration</h1>
    </div>
    
    <div class="content">
        <p>A new user has registered on {{ config('app.name', 'Quantified Athletics') }}.</p>
        
        <div class="user-info">
            <div class="info-row">
                <span class="label">Name:</span>
                <span class="value">{{ $newUser->name }}</span>
            </div>
            <div class="info-row">
                <span class="label">Email:</span>
                <span class="value">{{ $newUser->email }}</span>
            </div>
            <div class="info-row">
                <span class="label">Registration Date:</span>
                <span class="value">{{ $newUser->created_at->format('F j, Y \a\t g:i A T') }}</span>
            </div>
            <div class="info-row">
                <span class="label">User ID:</span>
                <span class="value">#{{ $newUser->id }}</span>
            </div>
        </div>

        <p>The user has been automatically set up with:</p>
        <ul>
            <li>Athlete role assigned</li>
            <li>Default exercise preferences (most features enabled)</li>
            <li>Default measurement types (Bodyweight, Waist)</li>
            <li>Sample ingredients and meal</li>
        </ul>

        <p>You can view and manage this user in the admin panel.</p>
    </div>

    <div class="footer">
        <p class="timestamp">
            This notification was sent automatically on {{ now()->format('F j, Y \a\t g:i A T') }}
        </p>
    </div>
</body>
</html>