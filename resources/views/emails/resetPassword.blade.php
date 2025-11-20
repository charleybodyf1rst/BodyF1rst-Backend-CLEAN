<!doctype html>
<html lang="en-US">

<head>
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
    <title>BodyF1RST | Reset Password</title>
    <meta name="description" content="Reset Password Email Template.">
    <style type="text/css" media="screen">
        /* Linked Styles */
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
            color: #4e54cb;
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

        .mcnPreviewText {
            display: none !important;
        }

        .text-footer a {
            color: #7e7e7e !important;
        }

        .text-footer2 a {
            color: #c3c3c3 !important;
        }

        .table-striped>tbody>tr:nth-of-type(even) {
            background-color: #f2f2f2;
            --bs-table-accent-bg: #f2f2f2;
        }

        .table>:not(caption)>*>* {
            padding: 0.5rem 0.5rem;
        }

        /* Mobile styles */
        @media only screen and (max-device-width: 480px),
        only screen and (max-width: 480px) {
            .mobile-shell {
                width: 100% !important;
                max-width: 100% !important;
            }

            .m-center {
                text-align: center !important;
            }

            .m-left {
                margin-right: auto !important;
            }

            .center {
                margin: 0 auto !important;
            }

            .td {
                width: 100% !important;
                min-width: 100% !important;
            }

            .fluid-img img {
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
            }

            .text-white {
                font-size: 12px !important;
                line-height: 20px !important;
            }

            .text-white-two {
                font-size: 12px !important;
                line-height: 18px !important;
            }

            .h2-white {
                font-size: 14px !important;
                line-height: 40px !important;
            }

            .h3-white {
                font-size: 13px !important;
            }

            .content {
                padding: 20px 15px 10px !important;
            }

            .content-two {
                padding: 10px 15px !important;
            }

            .section-inner {
                padding: 0px !important;
            }

            .main {
                padding: 0px !important;
            }

            .column {
                float: left !important;
                width: 100% !important;
                display: block !important;
            }

        }
    </style>
</head>

<body class="body"
    style="padding:0 !important; margin:0 !important; display:block !important; min-width:100% !important; width:100% !important; background:#eeeeee; -webkit-text-size-adjust:none;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#eeeeee">
        <tr>
            <td align="center" valign="top">
                <!-- Main -->
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
                                                    <table width="100%" border="0" cellspacing="0"
                                                        cellpadding="0">
                                                        <tr>
                                                            <td class="m-center"
                                                                style="font-size:0pt; line-height:0pt; text-align:center;">
                                                                <img src="{{ asset('emails/logo.png') }}" width="155"
                                                                    style="max-width:155px;" border="0"
                                                                    alt="" />
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="padding: 30px 0px 10px 0px;">
                                                    <table width="100%" border="0" cellspacing="0"
                                                        cellpadding="0">
                                                        <tr>
                                                            <td class="m-center"
                                                                style="font-size:0pt; line-height:0pt; text-align:center;">
                                                                <img src="{{ asset('emails/reset-pass-image.png') }}"
                                                                    width="155" style="max-width:155px;"
                                                                    border="0" alt="" />
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fluid-img"
                                                    style="color:#343434; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size:20px; font-weight: 600; line-height:34px; text-align:center; padding:30px 0 10px;">
                                                    Password Reset Request
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="content" style="padding:0px 30px 20px;">
                                                    <table width="100%" border="0" cellspacing="0"
                                                        cellpadding="0">
                                                        <tr>
                                                            <td align="center" class="text-white center "
                                                                style="color:#8A97AA; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size:14px; line-height:20px; text-align:center; padding-bottom:10px;">
                                                                <p><strong>Dear {{ $user['name'] }},</strong></p>
                                                                <p>We received a request to reset your account password.
                                                                    If
                                                                    you made this request, please click the link below
                                                                    to reset your password:</p>

                                                                <div class="text-button white-button"
                                                                    style="margin: 20px 0; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;font-size:14px;line-height:22px;">
                                                                    <a href="{{ route('getResetPassword.' . $type, ['token' => $user['reset_token']]) }}"
                                                                        target="_blank" class="link"
                                                                        style="display: inline-block; width: 200px; text-align:center;padding: 10px 15px;border-radius:30px; background:#fc4d1f; color:#ffffff; font-weight:bold;text-decoration:none;"><span
                                                                            class="link"
                                                                            style="color:#ffffff;text-decoration:none;">
                                                                            Reset Password</span></a>
                                                                </div>

                                                                <p>If you didn’t request a password reset, please
                                                                    ignore this email or contact our support team with
                                                                    any concerns. <a style="color: #fc4d1f;"
                                                                        href="mailto:support@bodyf1rst.com">
                                                                        support@bodyf1rst.com</a></p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        <!-- END Header -->
                                        <!-- Footer -->
                                        <table width="100%" border="0" cellspacing="0" cellpadding="0"
                                            bgcolor="#fc4d1f">
                                            <tr>
                                                <td class="footer" style="padding:20px 30px;">
                                                    <table width="100%" border="0" cellspacing="0"
                                                        cellpadding="0">
                                                        <tr>
                                                            <td
                                                                style="color:#ffffff; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size:16px; font-weight: 500; line-height:22px; text-align:center; text-transform:uppercase; padding-bottom:15px;">
                                                                Follow Us
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td class="pb30" align="center"
                                                                style="padding-bottom:15px;">
                                                                <table border="0" cellspacing="0" cellpadding="0">
                                                                    <tr>
                                                                        <td class="img" width="50"
                                                                            style="font-size:0pt; line-height:0pt; text-align:left;">
                                                                            <a href="https://www.facebook.com/bodyf1rst/"
                                                                                target="_blank"><img
                                                                                    src="{{ asset('emails/fb-icon.png') }}"
                                                                                    width="35" height="35"
                                                                                    style="max-width:40px;"
                                                                                    border="0"
                                                                                    alt="" /></a>
                                                                        </td>
                                                                        <td class="img" width="50"
                                                                            style="font-size:0pt; line-height:0pt; text-align:left;">
                                                                            <a href="https://www.instagram.com/bodyf1rstapp/"
                                                                                target="_blank"><img
                                                                                    src="{{ asset('emails/insta-icon.png') }}"
                                                                                    width="35" height="35"
                                                                                    style="max-width:40px;"
                                                                                    border="0"
                                                                                    alt="" /></a>
                                                                        </td>
                                                                        <td class="img" width="50"
                                                                            style="font-size:0pt; line-height:0pt; text-align:left;">
                                                                            <a href="https://www.youtube.com/c/BodyF1RST"
                                                                                target="_blank"><img
                                                                                    src="{{ asset('emails/youtube-icon.png') }}"
                                                                                    width="35" height="35"
                                                                                    style="max-width:40px;"
                                                                                    border="0"
                                                                                    alt="" /></a>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>

                                                        <tr>
                                                            <td
                                                                style="color:#ffffff; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size:14px; line-height:20px; text-align:center; padding-bottom:5px; ">
                                                                <a href="https://bodyf1rst.net/" target="_blank"
                                                                    style="color:#ffffff; text-decoration:none;"><span
                                                                        style="color:#ffffff; text-decoration:none; padding-right:15px;">
                                                                        @bodyf1rst </span></a>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td
                                                                style="color:#ffffff; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size:14px; line-height:20px; text-align:center; ">
                                                                {{ date('Y') }} BodyF1RST . All Right Reserved.
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
                <!-- END Main -->
            </td>
        </tr>
    </table>
</body>

</html>
