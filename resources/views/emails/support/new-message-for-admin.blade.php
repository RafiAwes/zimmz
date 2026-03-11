<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Support Message</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f6fb;font-family:Segoe UI,Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f6fb;padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
                <tr>
                    <td style="background:linear-gradient(135deg,#0f172a,#1d4ed8);padding:24px 28px;color:#ffffff;">
                        <h1 style="margin:0;font-size:22px;font-weight:700;">New Support Message Received</h1>
                        <p style="margin:8px 0 0;font-size:14px;opacity:0.9;">{{ config('app.name') }} Support Desk</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">Hello {{ $admin->name ?? 'Admin' }},</p>
                        <p style="margin:0 0 20px;font-size:15px;line-height:1.7;">A user has submitted a new support message and is waiting for your reply.</p>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
                            <tr>
                                <td style="background:#f8fafc;width:170px;padding:12px 14px;font-size:13px;font-weight:600;border-bottom:1px solid #e5e7eb;">User</td>
                                <td style="padding:12px 14px;font-size:13px;border-bottom:1px solid #e5e7eb;">{{ $supportMessage->user->name }}</td>
                            </tr>
                            <tr>
                                <td style="background:#f8fafc;padding:12px 14px;font-size:13px;font-weight:600;border-bottom:1px solid #e5e7eb;">Email</td>
                                <td style="padding:12px 14px;font-size:13px;border-bottom:1px solid #e5e7eb;">{{ $supportMessage->user->email }}</td>
                            </tr>
                            <tr>
                                <td style="background:#f8fafc;padding:12px 14px;font-size:13px;font-weight:600;border-bottom:1px solid #e5e7eb;">Subject</td>
                                <td style="padding:12px 14px;font-size:13px;border-bottom:1px solid #e5e7eb;">{{ $supportMessage->subject }}</td>
                            </tr>
                            <tr>
                                <td style="background:#f8fafc;padding:12px 14px;font-size:13px;font-weight:600;vertical-align:top;">Message</td>
                                <td style="padding:12px 14px;font-size:13px;line-height:1.7;white-space:pre-wrap;">{{ $supportMessage->message }}</td>
                            </tr>
                        </table>

                        <p style="margin:22px 0 0;font-size:13px;color:#6b7280;line-height:1.7;">Tip: You can reply directly from the admin portal using the support message reply endpoint.</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 28px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;">
                        This is an automated email from {{ config('app.name') }}.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
