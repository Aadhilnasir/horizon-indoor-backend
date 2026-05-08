<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Your Password</title>
  <style>
    body { font-family: 'DM Sans', Arial, sans-serif; background:#f0f7f0; margin:0; padding:40px 20px; }
    .container { max-width:520px; margin:0 auto; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.08); }
    .header { background:#14532d; padding:32px 40px; text-align:center; }
    .header-icon { font-size:40px; margin-bottom:8px; }
    .header-title { font-size:22px; font-weight:700; color:#ffffff; letter-spacing:2px; margin:0; }
    .header-sub { font-size:13px; color:#86efac; margin:4px 0 0; }
    .body { padding:36px 40px; }
    .greeting { font-size:16px; font-weight:600; color:#14532d; margin-bottom:12px; }
    .message { font-size:14px; color:#6b7280; line-height:1.7; margin-bottom:28px; }
    .btn { display:block; text-align:center; background:#16a34a; color:#ffffff; text-decoration:none; font-size:15px; font-weight:700; padding:16px 32px; border-radius:10px; letter-spacing:1px; }
    .note { font-size:12px; color:#9ca3af; margin-top:24px; line-height:1.6; }
    .link-box { background:#f0f7f0; border:1px solid #d1e7d1; border-radius:8px; padding:12px 16px; margin-top:16px; word-break:break-all; font-size:11px; color:#6b7280; }
    .footer { background:#f0f7f0; padding:20px 40px; text-align:center; border-top:1px solid #d1e7d1; }
    .footer-text { font-size:12px; color:#9ca3af; }
    .footer-brand { font-size:13px; font-weight:700; color:#16a34a; margin-bottom:4px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="header-icon">🏟️</div>
      <div class="header-title">HORIZON · INDOOR</div>
      <div class="header-sub">Indoor Sports Complex</div>
    </div>

    <div class="body">
      <div class="greeting">Hi, {{ $user->first_name }}! 👋</div>
      <div class="message">
        We received a request to reset your password for your Horizon Indoor account.
        Click the button below to reset it. This link will expire in <strong>60 minutes</strong>.
      </div>

      <a href="{{ $resetUrl }}" class="btn">RESET MY PASSWORD</a>

      <div class="note">
        If the button doesn't work, copy and paste this link in your browser:
        <div class="link-box">{{ $resetUrl }}</div>
      </div>

      <div class="note">
        If you didn't request a password reset, you can safely ignore this email.
        Your password will not be changed.
      </div>
    </div>

    <div class="footer">
      <div class="footer-brand">Horizon Indoor Sports</div>
      <div class="footer-text">Madawala Bazzar, Kandy · +94 750405050</div>
      <div class="footer-text" style="margin-top:8px;">© 2026 Horizon Indoor. All rights reserved.</div>
    </div>
  </div>
</body>
</html>