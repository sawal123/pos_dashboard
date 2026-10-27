<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>POS Cloud email change verification</title>
</head>
<body>
    <p>Use this code to confirm the new email address for your POS Cloud account:</p>
    <p style="font-size: 24px; font-weight: 700; letter-spacing: 0.25em;">
        {{ $code }}
    </p>
    <p>This six-digit code expires in 10 minutes, at {{ $expiresAt->toIso8601String() }}.</p>
    <p>This code is only for confirming an email address change. Never share it with anyone.</p>
    <p>If you did not request this change, ignore this email and secure your account.</p>
</body>
</html>
