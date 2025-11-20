<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Reminder - BodyF1rst</title>
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
        .reminder-details {
            background-color: #fff8f0;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #ff9800;
        }
        .reminder-details table {
            width: 100%;
            border-collapse: collapse;
        }
        .reminder-details td {
            padding: 8px 0;
            border-bottom: 1px solid #ffe8d0;
        }
        .reminder-details td:first-child {
            font-weight: bold;
            width: 40%;
        }
        .amount {
            font-size: 24px;
            color: #ff9800;
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
        .reminder-icon {
            text-align: center;
            font-size: 48px;
            color: #ff9800;
            margin-bottom: 20px;
        }
        .urgent-notice {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>BodyF1rst</h1>
        </div>

        <div class="reminder-icon">🔔</div>

        <div class="content">
            <h2 style="color: #333; text-align: center;">Payment Reminder</h2>
            <p>Hi {{ $organizationName }} Team,</p>
            <p>This is a friendly reminder that your organization has an outstanding payment.</p>

            <div class="amount">
                ${{ number_format($amountDue, 2) }}
            </div>

            <div class="reminder-details">
                <table>
                    <tr>
                        <td>Organization:</td>
                        <td>{{ $organizationName }}</td>
                    </tr>
                    <tr>
                        <td>Amount Due:</td>
                        <td>${{ number_format($amountDue, 2) }} USD</td>
                    </tr>
                    <tr>
                        <td>Due Date:</td>
                        <td>{{ $dueDate }}</td>
                    </tr>
                    @if($isOverdue)
                    <tr>
                        <td>Status:</td>
                        <td style="color: #f44336; font-weight: bold;">OVERDUE</td>
                    </tr>
                    <tr>
                        <td>Days Overdue:</td>
                        <td style="color: #f44336; font-weight: bold;">{{ $daysOverdue }}</td>
                    </tr>
                    @else
                    <tr>
                        <td>Status:</td>
                        <td style="color: #ff9800; font-weight: bold;">PENDING</td>
                    </tr>
                    @endif
                </table>
            </div>

            @if($isOverdue)
            <div class="urgent-notice">
                <strong style="color: #f44336;">⚠ URGENT: Your payment is {{ $daysOverdue }} days overdue</strong><br>
                <span style="font-size: 14px;">Service may be interrupted if payment is not received soon.</span>
            </div>
            @endif

            <div style="text-align: center;">
                <a href="{{ env('APP_URL') }}/billing" class="button">Make Payment Now</a>
            </div>

            <div style="background-color: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
                <h3 style="margin-top: 0;">Payment Options:</h3>
                <ul style="margin-bottom: 0;">
                    <li>Credit/Debit Card (Instant processing)</li>
                    <li>ACH Bank Transfer (No processing fees)</li>
                    <li>Contact us for alternative payment arrangements</li>
                </ul>
            </div>

            <p style="margin-top: 20px;">
                To avoid service interruption, please process your payment as soon as possible. If you have already paid, please disregard this reminder.
            </p>

            <p>
                If you have any questions about this payment or need to discuss payment arrangements, please contact our billing department.
            </p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} BodyF1rst. All rights reserved.</p>
            <p>Questions? Contact billing@bodyf1rst.com</p>
            <p>This is an automated email. Please do not reply to this message.</p>
        </div>
    </div>
</body>
</html>
