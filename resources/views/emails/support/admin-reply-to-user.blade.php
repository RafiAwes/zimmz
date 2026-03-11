<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $replySubject }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f6fb;font-family:Segoe UI,Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f6fb;padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
                <tr>
                    <td style="background:linear-gradient(135deg,#0b3b2e,#047857);padding:24px 28px;color:#ffffff;">
                        <h1 style="margin:0;font-size:22px;font-weight:700;">Support Team Reply</h1>
                        <p style="margin:8px 0 0;font-size:14px;opacity:0.9;">{{ config('app.name') }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">Hello {{ $supportMessage->user->name }},</p>
                        <p style="margin:0 0 18px;font-size:15px;line-height:1.7;">Our admin team has replied to your support request:</p>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 16px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
                            <tr>
                                <td style="background:#f8fafc;width:170px;padding:12px 14px;font-size:13px;font-weight:600;border-bottom:1px solid #e5e7eb;">Reply Subject</td>
                                <td style="padding:12px 14px;font-size:13px;border-bottom:1px solid #e5e7eb;">{{ $replySubject }}</td>
                            </tr>
                            <tr>
                                <td style="background:#f8fafc;padding:12px 14px;font-size:13px;font-weight:600;vertical-align:top;">Reply Message</td>
                                <td style="padding:12px 14px;font-size:13px;line-height:1.7;white-space:pre-wrap;">{{ $replyMessage }}</td>
                            </tr>
                        </table>

                        <div style="margin-top:18px;padding:14px;border:1px dashed #d1d5db;border-radius:10px;background:#fcfcfd;">
                            <p style="margin:0 0 8px;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Your Original Message</p>
                            <p style="margin:0 0 6px;font-size:13px;"><strong>Subject:</strong> {{ $supportMessage->subject }}</p>
                            <p style="margin:0;font-size:13px;line-height:1.7;white-space:pre-wrap;">{{ $supportMessage->message }}</p>
                        </div>

                        <p style="margin:20px 0 0;font-size:13px;color:#6b7280;line-height:1.7;">If you need more help, you can submit another message from your account.</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 28px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;">
                        Sent by {{ $admin->name }} from {{ config('app.name') }} support.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
