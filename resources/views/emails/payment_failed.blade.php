<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed - BodyF1rst</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #FF6B35;
        }
        .header h1 {
            color: #FF6B35;
            margin: 0;
            font-size: 28px;
        }
        .content {
            margin-bottom: 30px;
        }
        .payment-details {
            background-color: #fff3f3;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #f44336;
        }
        .payment-details table {
            width: 100%;
            border-collapse: collapse;
        }
        .payment-details td {
            padding: 8px 0;
            border-bottom: 1px solid #ffe0e0;
        }
        .payment-details td:first-child {
            font-weight: bold;
            width: 40%;
        }
        .amount {
            font-size: 24px;
            color: #f44336;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #FF6B35;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #888;
            font-size: 14px;
        }
        .warning-icon {
            text-align: center;
            font-size: 48px;
            color: #f44336;
            margin-bottom: 20px;
        }
        .error-message {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>BodyF1rst</h1>
        </div>

        <div class="warning-icon">⚠</div>

        <div class="content">
            <h2 style="color: #333; text-align: center;">Payment Failed</h2>
            <p>Hi {{ $user->name }},</p>
            <p>We were unable to process your payment attempt.</p>

            <div class="amount">
                ${{ number_format($amount, 2) }}
            </div>

            <div class="payment-details">
                <table>
                    <tr>
                        <td>Amount:</td>
                        <td>${{ number_format($amount, 2) }} USD</td>
                    </tr>
                    <tr>
                        <td>Attempted:</td>
                        <td>{{ now()->format('F j, Y g:i A') }}</td>
                    </tr>
                    <tr>
                        <td>Status:</td>
                        <td style="color: #f44336; font-weight: bold;">FAILED</td>
                    </tr>
                </table>
            </div>

            @if(!empty($error))
            <div class="error-message">
                <strong>Error:</strong> {{ $error }}
            </div>
            @endif

            <div style="background-color: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
                <h3 style="margin-top: 0;">Common reasons for payment failures:</h3>
                <ul style="margin-bottom: 0;">
                    <li>Insufficient funds in your account</li>
                    <li>Card has expired or been declined</li>
                    <li>Billing address doesn't match card records</li>
                    <li>Card has been flagged for fraud prevention</li>
                    <li>Daily transaction limit exceeded</li>
                </ul>
            </div>

            <div style="text-align: center;">
                <a href="{{ env('APP_URL') }}/billing" class="button">Update Payment Method</a>
            </div>

            <p style="margin-top: 20px;">
                Please update your payment information or try a different payment method. If you continue to experience issues, contact your bank or our support team for assistance.
            </p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} BodyF1rst. All rights reserved.</p>
            <p>Need help? Contact us at support@bodyf1rst.com</p>
            <p>This is an automated email. Please do not reply to this message.</p>
        </div>
    </div>
</body>
</html>
