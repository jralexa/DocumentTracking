<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $organizationName }} Credentials</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background:#dc2626;color:#ffffff;padding:20px 24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="width:64px;vertical-align:middle;">
                                        <img src="{{ $logoUrl }}" alt="DepEd Southern Leyte Division Logo" width="56" height="56" style="display:block;border-radius:50%;background:#ffffff;">
                                    </td>
                                    <td style="vertical-align:middle;padding-left:12px;">
                                        <div style="font-size:16px;font-weight:700;line-height:1.3;">{{ $organizationName }}</div>
                                        <div style="font-size:12px;opacity:0.92;line-height:1.3;">Document Tracking System</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <p style="margin:0 0 12px;font-size:15px;">Hello {{ $recipientName }},</p>
                            <p style="margin:0 0 12px;font-size:14px;line-height:1.6;">
                                {{ $isReset ? 'Your account password was reset by an administrator.' : 'An administrator created your account in the Document Tracking System.' }}
                            </p>
                            <p style="margin:0 0 12px;font-size:14px;line-height:1.6;">
                                Use these temporary credentials to sign in:
                            </p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 18px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;">
                                <tr>
                                    <td style="padding:14px 16px;">
                                        <p style="margin:0 0 8px;font-size:13px;"><strong>Email:</strong> {{ $recipientEmail }}</p>
                                        <p style="margin:0;font-size:13px;"><strong>Temporary Password:</strong> {{ $temporaryPassword }}</p>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 14px;font-size:14px;line-height:1.6;">
                                For account security, you are required to change your password immediately after login.
                            </p>
                            <p style="margin:0 0 20px;">
                                <a href="{{ $loginUrl }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:8px;font-size:14px;font-weight:600;">Sign In</a>
                            </p>
                            <p style="margin:0 0 6px;font-size:12px;color:#4b5563;">Created by: {{ $createdByName }}</p>
                            <p style="margin:0;font-size:12px;color:#6b7280;">If you did not expect this email, contact your system administrator.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
