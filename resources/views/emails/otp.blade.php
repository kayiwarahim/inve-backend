<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset OTP</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7fa;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background-color: rgb(245, 158, 11);
            padding: 30px 20px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 30px 20px;
            text-align: center;
        }
        .otp {
            font-size: 36px;
            font-weight: bold;
            color: rgb(245, 158, 11);
            letter-spacing: 4px;
            margin: 20px 0;
            padding: 15px;
            background-color: #e9f5ff;
            border-radius: 8px;
            display: inline-block;
        }
        .expiration {
            font-size: 16px;
            color: #6c757d;
            margin: 10px 0;
        }
        .footer {
            background-color: rgb(245, 158, 11);
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #6c757d;
            border-top: 1px solid #e9ecef;
        }
        .footer a {
            color: rgb(255, 255, 255);
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Password Reset</h1>
        </div>
        <div class="content">
            <p>Hello,</p>
            <p>You requested a password reset. Use the OTP below to proceed:</p>
            <div class="otp">{{ $otp }}</div>
            <p class="expiration">This OTP expires in 5 minutes.</p>
            <p>If you didn't request this, please ignore this email.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} <a href="{{ url('https://trackflow.alrahimnation.software') }}">Track Flow</a>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>