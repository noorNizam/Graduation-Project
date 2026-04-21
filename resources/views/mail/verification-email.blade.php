<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email Verification</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .content {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }

        .verification-code {
            background: #3c3b55;
            color: white;
            padding: 15px;
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            border-radius: 6px;
            margin: 20px 0;
            letter-spacing: 3px;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            padding: 20px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>

<body>


    <div class="content">
        <h2>Dear User,</h2>

        <p>Please use the verification code below to verify your email address:</p>

        <div class="verification-code">
            {{ $code }}
        </div>

        <p>This code will expire in 10 minutes.</p>

        <p>If you didn't create an account, please ignore this email.</p>

        <p>Best regards,<br>
            Banking System</p>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} Banking System. All rights reserved.</p>
    </div>
</body>

</html>