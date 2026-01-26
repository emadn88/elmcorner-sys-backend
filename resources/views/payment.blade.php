<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - ElmCorner</title>
    <script>
        // Redirect to frontend payment page
        const token = '{{ $token }}';
        const frontendUrl = '{{ env("FRONTEND_URL", "http://localhost:3000") }}';
        window.location.href = `${frontendUrl}/payment/${token}`;
    </script>
</head>
<body>
    <div style="display: flex; justify-content: center; align-items: center; height: 100vh; font-family: Arial, sans-serif;">
        <div style="text-align: center;">
            <p>Redirecting to payment page...</p>
            <p><a href="{{ env('FRONTEND_URL', 'http://localhost:3000') }}/payment/{{ $token }}">Click here if you are not redirected</a></p>
        </div>
    </div>
</body>
</html>
