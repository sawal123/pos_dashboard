<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>POS Cloud email verification</title>
</head>
<body>
    <p>Use this code to verify your POS Cloud email address:</p>
    <p style="font-size: 24px; font-weight: 700; letter-spacing: 0.25em;">
        {{ $code }}
    </p>
    <p>This six-digit code expires in 10 minutes, at {{ $expiresAt->toIso8601String() }}.</p>
    <p>The code is only for account email verification. Never share it with anyone.</p>
    <p>If you did not request this code, you can ignore this email.</p>
</body>
</html>
