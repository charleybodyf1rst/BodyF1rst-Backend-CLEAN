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
        .invoice-details {
            background-color: #fff3f3;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #f44336;
        }
        .invoice-details table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoice-details td {
            padding: 8px 0;
            border-bottom: 1px solid #ffe0e0;
        }
        .invoice-details td:first-child {
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
        .alert-box {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
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
            <p>We were unable to process your payment for invoice #{{ $invoice->number }}.</p>

            <div class="amount">
                ${{ number_format($invoice->amount_due / 100, 2) }}
            </div>

            <div class="invoice-details">
                <table>
                    <tr>
                        <td>Invoice Number:</td>
                        <td>#{{ $invoice->number }}</td>
                    </tr>
                    <tr>
                        <td>Amount Due:</td>
                        <td>${{ number_format($invoice->amount_due / 100, 2) }} {{ strtoupper($invoice->currency) }}</td>
                    </tr>
                    <tr>
                        <td>Invoice Date:</td>
                        <td>{{ date('F j, Y', $invoice->created) }}</td>
                    </tr>
                    <tr>
                        <td>Status:</td>
                        <td style="color: #f44336; font-weight: bold;">FAILED</td>
                    </tr>
                </table>
            </div>

            <div class="alert-box">
                <strong>What should I do?</strong>
                <ol style="margin: 10px 0 0 0; padding-left: 20px;">
                    <li>Check that your payment method has sufficient funds</li>
                    <li>Verify your payment information is up to date</li>
                    <li>Update your payment method in your account settings</li>
                    <li>Contact your bank if the issue persists</li>
                </ol>
            </div>

            <div style="text-align: center;">
                <a href="{{ env('APP_URL') }}/billing" class="button">Update Payment Method</a>
            </div>

            <p style="margin-top: 20px; color: #f44336; font-weight: bold;">
                Please update your payment information to avoid service interruption.
            </p>

            <p>If you have any questions or need assistance, please contact our support team.</p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} BodyF1rst. All rights reserved.</p>
            <p>This is an automated email. Please do not reply to this message.</p>
        </div>
    </div>
</body>
</html>
