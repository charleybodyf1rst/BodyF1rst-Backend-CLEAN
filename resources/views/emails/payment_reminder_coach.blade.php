<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connect Stripe Account - BodyF1rst</title>
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
        .info-box {
            background-color: #f0f8ff;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #2196F3;
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
        .info-icon {
            text-align: center;
            font-size: 48px;
            color: #2196F3;
            margin-bottom: 20px;
        }
        .benefits-list {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .benefits-list ul {
            margin: 0;
            padding-left: 20px;
        }
        .benefits-list li {
            margin: 10px 0;
            color: #4CAF50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>BodyF1rst</h1>
        </div>

        <div class="info-icon">💳</div>

        <div class="content">
            <h2 style="color: #333; text-align: center;">Connect Your Stripe Account</h2>
            <p>Hi {{ $coachName }},</p>
            <p>You're almost ready to start earning! To receive payments from your clients, please connect your Stripe account.</p>

            <div class="info-box">
                <strong>Why Stripe?</strong>
                <p style="margin-bottom: 0;">Stripe is a secure, industry-leading payment processor trusted by millions of businesses worldwide. It ensures fast, reliable, and secure payments directly to your bank account.</p>
            </div>

            <div class="benefits-list">
                <h3 style="margin-top: 0;">Benefits of connecting Stripe:</h3>
                <ul>
                    <li>Receive payments directly from your clients</li>
                    <li>Fast transfers to your bank account (2-3 business days)</li>
                    <li>Secure and PCI-compliant payment processing</li>
                    <li>Accept credit cards, debit cards, and ACH transfers</li>
                    <li>Detailed payment reports and analytics</li>
                    <li>24/7 fraud protection</li>
                </ul>
            </div>

            <div style="text-align: center;">
                <a href="{{ $stripeConnectUrl }}" class="button">Connect Stripe Account</a>
            </div>

            <div style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; padding: 15px; margin: 20px 0;">
                <strong>Setup is quick and easy:</strong>
                <ol style="margin: 10px 0 0 0; padding-left: 20px;">
                    <li>Click the button above</li>
                    <li>Create or connect your Stripe account</li>
                    <li>Provide your banking information</li>
                    <li>Verify your identity (required by law)</li>
                    <li>Start receiving payments!</li>
                </ol>
            </div>

            <p style="margin-top: 20px;">
                <strong>No upfront costs:</strong> Stripe only charges a small fee per transaction (2.9% + $0.30 for cards). There are no monthly fees or setup costs.
            </p>

            <p>
                If you have any questions about connecting your Stripe account or need assistance, our support team is here to help!
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
