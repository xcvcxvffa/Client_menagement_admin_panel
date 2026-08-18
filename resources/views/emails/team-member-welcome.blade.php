<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome to the Team</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9fb;
            color: #333333;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            max-width: 600px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .header {
            background-color: #ea580c;
            padding: 20px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px;
        }
        .content p {
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .credentials-box {
            background-color: #fffaf5;
            border: 1px solid #ffedd5;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .credentials-box h3 {
            margin-top: 0;
            color: #ea580c;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .credential-item {
            margin-bottom: 10px;
        }
        .credential-label {
            font-weight: bold;
            display: inline-block;
            width: 100px;
            color: #555555;
        }
        .credential-value {
            font-family: monospace;
            background: #f1f1f1;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 14px;
        }
        .button-container {
            text-align: center;
            margin-top: 30px;
            margin-bottom: 10px;
        }
        .button {
            display: inline-block;
            background-color: #ea580c;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: bold;
        }
        .footer {
            background-color: #f1f1f1;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            color: #777777;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to the Team!</h1>
        </div>
        
        <div class="content">
            <p>Hi {{ $user->name }},</p>
            
            <p><strong>{{ $inviterName }}</strong> has invited you to join their workspace on {{ config('app.name') }}.</p>
            
            <p>Your account has been successfully created. You can use the credentials below to log in to the system. We highly recommend changing your password after your first login.</p>
            
            <div class="credentials-box">
                <h3>Your Login Credentials</h3>
                <div class="credential-item">
                    <span class="credential-label">User ID:</span>
                    <span class="credential-value">{{ $user->email }}</span>
                </div>
                <div class="credential-item">
                    <span class="credential-label">Password:</span>
                    <span class="credential-value">{{ $password }}</span>
                </div>
            </div>
            
            <div class="button-container">
                <a href="{{ route('login') }}" class="button">Log In Now</a>
            </div>
        </div>
        
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </div>
    </div>
</body>
</html>
