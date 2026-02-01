<!-- resources/views/emails/verify_agent.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
        }
        .email-container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
        }
        p {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 18px;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .footer {
            margin-top: 30px;
            font-size: 14px;
            color: #777;
        }
        .footer a {
            color: #777;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <h1>Verify Your Email Address</h1>
        <p>Hello, {{ $name }}!</p>
        <p>
            Thank you for joining us at Convosoft! To complete your account setup and begin using our services, we need to verify your email address. Please click the button below to verify your email:
        </p>

        <p style="text-align: center;">
            <a href="{{ $verificationUrl }}" 
               style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-size: 18px;">
               Verify Email
            </a>
        </p>


        <p>
            If you did not create an account with Convosoft, please disregard this email. If you have any issues, feel free to <a href="mailto:support@convosoft.com">contact our support team</a>.
        </p>

        <p>We look forward to working with you.</p>

        <p>Best regards, <br> Convosoft Team</p>

        <!-- Footer -->
        <div class="footer">
            <p>
                © {{ date('Y') }} Convosoft, All rights reserved.<br>
                Our mailing address is: 123 Convosoft Lane, Tech City, 56789<br>
                <a href="#">Privacy Policy</a> | <a href="#">Terms of Service</a>
            </p>
        </div>
    </div>
</body>
</html>
