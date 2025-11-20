<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Reminder</title>
    <style type="text/css" media="screen">
        body {
            padding: 0 !important;
            margin: 0 !important;
            display: block !important;
            min-width: 100% !important;
            width: 100% !important;
            background: #eeeeee !important;
            -webkit-text-size-adjust: none
        }

        a {
            color: #fc4d1f;
            text-decoration: none
        }

        p {
            padding: 0 !important;
            margin: 0 !important;
            margin-bottom: 10px !important;
        }

        img {
            -ms-interpolation-mode: bicubic;
        }

        .reminder-box {
            background: linear-gradient(135deg, #fc4d1f 0%, #ff6b45 100%);
            color: white;
            padding: 25px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }

        .appointment-details {
            background: #f8f9fa;
            padding: 20px;
            border-left: 4px solid #fc4d1f;
            margin: 20px 0;
        }

        .detail-row {
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #343434;
            display: inline-block;
            width: 120px;
        }

        .detail-value {
            color: #8A97AA;
        }

        @media only screen and (max-device-width: 480px),
        only screen and (max-width: 480px) {
            .mobile-shell {
                width: 100% !important;
                max-width: 100% !important;
            }

            .content {
                padding: 20px 15px 10px !important;
            }
        }
    </style>
</head>
<body class="body"
    style="padding:0 !important; margin:0 !important; display:block !important; min-width:100% !important; width:100% !important; background:#eeeeee; -webkit-text-size-adjust:none;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#eeeeee">
        <tr>
            <td align="center" valign="top">
                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td align="center">
                            <table style="max-width: 600px;" border="0" cellspacing="0" cellpadding="0"
                                class="mobile-shell" bgcolor="#ffffff">
                                <tr>
                                    <td class="td"
                                        style="font-size:0pt; line-height:0pt; padding:0; margin:0; font-weight:normal;">

                                        <!-- Header -->
                                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="padding: 30px 0px 10px 0px;">
                                                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                                        <tr>
                                                            <td class="m-center"
                                                                style="font-size:0pt; line-height:0pt; text-align:center;">
                                                                <img src="{{ asset('emails/logo.png') }}" width="155"
                                                                    style="max-width:155px;" border="0" alt="" />
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Main Content -->
                                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td class="fluid-img"
                                                    style="color:#343434; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size:24px; font-weight: 600; line-height:34px; text-align:center; padding:30px 0 10px;">
                                                    ⏰ Appointment Reminder
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="content" style="padding:0px 30px 20px;">
                                                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                                        <tr>
                                                            <td align="center" class="text-white center"
                                                                style="color:#8A97AA; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size:14px; line-height:20px; text-align:center; padding-bottom:10px;">
                                                                <p><strong>Hi {{ $data['client_name'] }},</strong></p>
                                                                <p>This is a friendly reminder about your upcoming appointment!</p>

                                                                <div class="reminder-box" style="background: linear-gradient(135deg, #fc4d1f 0%, #ff6b45 100%); color: white; padding: 25px; border-radius: 8px; margin: 20px 0; text-align: center;">
                                                                    <h2 style="margin: 0 0 10px 0; font-size: 28px; font-weight: 700;">{{ $data['hours_until'] }} Hours</h2>
                                                                    <p style="margin: 0; font-size: 16px; opacity: 0.95;">Until your appointment</p>
                                                                </div>

                                                                <div class="appointment-details" style="background: #f8f9fa; padding: 20px; border-left: 4px solid #fc4d1f; margin: 20px 0; text-align: left;">
                                                                    <h3 style="margin: 0 0 15px 0; color: #343434; font-size: 18px;">Appointment Details</h3>

                                                                    <div class="detail-row" style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                                                        <span class="detail-label" style="font-weight: 600; color: #343434; display: inline-block; width: 120px;">Type:</span>
                                                                        <span class="detail-value" style="color: #8A97AA;">{{ $data['appointment_type'] }}</span>
                                                                    </div>

                                                                    <div class="detail-row" style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                                                        <span class="detail-label" style="font-weight: 600; color: #343434; display: inline-block; width: 120px;">Date:</span>
                                                                        <span class="detail-value" style="color: #8A97AA;">{{ $data['appointment_date'] }}</span>
                                                                    </div>

                                                                    <div class="detail-row" style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                                                        <span class="detail-label" style="font-weight: 600; color: #343434; display: inline-block; width: 120px;">Time:</span>
                                                                        <span class="detail-value" style="color: #8A97AA;">{{ $data['appointment_time'] }}</span>
                                                                    </div>

                                                                    <div class="detail-row" style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                                                        <span class="detail-label" style="font-weight: 600; color: #343434; display: inline-block; width: 120px;">Duration:</span>
                                                                        <span class="detail-value" style="color: #8A97AA;">{{ $data['duration'] }} minutes</span>
                                                                    </div>

                                                                    <div class="detail-row" style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                                                        <span class="detail-label" style="font-weight: 600; color: #343434; display: inline-block; width: 120px;">Coach:</span>
                                                                        <span class="detail-value" style="color: #8A97AA;">{{ $data['coach_name'] }}</span>
                                                                    </div>

                                                                    <div class="detail-row" style="padding: 8px 0;">
                                                                        <span class="detail-label" style="font-weight: 600; color: #343434; display: inline-block; width: 120px;">Location:</span>
                                                                        <span class="detail-value" style="color: #8A97AA;">{{ $data['location'] }}</span>
                                                                    </div>
                                                                </div>

                                                                <p style="margin-top: 20px;"><strong>What to bring:</strong></p>
                                                                <ul style="text-align: left; color: #8A97AA; padding-left: 20px;">
                                                                    <li>Comfortable workout clothes</li>
                                                                    <li>Water bottle</li>
                                                                    <li>Towel</li>
                                                                    <li>Positive attitude!</li>
                                                                </ul>

                                                                <p style="margin-top: 20px;">Please arrive 5-10 minutes early. If you need to cancel or reschedule, please contact us as soon as possible.</p>

                                                                <p>Questions? Contact us at <a style="color: #fc4d1f;" href="mailto:support@bodyf1rst.com">support@bodyf1rst.com</a></p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        <!-- END Main Content -->

                                        <!-- Footer -->
                                        <table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#fc4d1f">
                                            <tr>
                                                <td class="footer" style="padding:20px 30px;">
                                                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                                        <tr>
                                                            <td style="color:#ffffff; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size:16px; font-weight: 500; line-height:22px; text-align:center; text-transform:uppercase; padding-bottom:15px;">
                                                                Follow Us
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td class="pb30" align="center" style="padding-bottom:15px;">
                                                                <table border="0" cellspacing="0" cellpadding="0">
                                                                    <tr>
                                                                        <td class="img" width="50" style="font-size:0pt; line-height:0pt; text-align:left;">
                                                                            <a href="https://www.facebook.com/bodyf1rst/" target="_blank">
                                                                                <img src="{{ asset('emails/fb-icon.png') }}" width="35" height="35" style="max-width:40px;" border="0" alt="" />
                                                                            </a>
                                                                        </td>
                                                                        <td class="img" width="50" style="font-size:0pt; line-height:0pt; text-align:left;">
                                                                            <a href="https://www.instagram.com/bodyf1rstapp/" target="_blank">
                                                                                <img src="{{ asset('emails/insta-icon.png') }}" width="35" height="35" style="max-width:40px;" border="0" alt="" />
                                                                            </a>
                                                                        </td>
                                                                        <td class="img" width="50" style="font-size:0pt; line-height:0pt; text-align:left;">
                                                                            <a href="https://www.youtube.com/c/BodyF1RST" target="_blank">
                                                                                <img src="{{ asset('emails/youtube-icon.png') }}" width="35" height="35" style="max-width:40px;" border="0" alt="" />
                                                                            </a>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="color:#ffffff; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size:14px; line-height:20px; text-align:center; padding-bottom:5px;">
                                                                <a href="https://bodyf1rst.net/" target="_blank" style="color:#ffffff; text-decoration:none;">
                                                                    <span style="color:#ffffff; text-decoration:none; padding-right:15px;">@bodyf1rst</span>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="color:#ffffff; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size:14px; line-height:20px; text-align:center;">
                                                                {{ date('Y') }} BodyF1RST. All Rights Reserved.
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        <!-- END Footer -->
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
