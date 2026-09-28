<?php
const OTP_VALID_MINUTES = 5;
const OTP_MAX_ATTEMPTS = 5;
const OTP_RESEND_SECONDS = 60;

/** Naya 6-digit OTP bana kar email karta hai. Purane OTP khatam ho jate hain. */
function send_login_otp(array $user): void
{
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    db_exec('UPDATE login_otps SET used = 1 WHERE user_id = ? AND used = 0', [$user['id']]);
    db_exec('INSERT INTO login_otps (user_id, code_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL ' . OTP_VALID_MINUTES . ' MINUTE)',
        [$user['id'], password_hash($code, PASSWORD_DEFAULT)]);

    $html = '<div style="font-family:Arial,sans-serif;font-size:15px">'
        . '<p>Assalam o Alaikum ' . e($user['username']) . ',</p>'
        . '<p>Aap ka ' . e(APP_NAME) . ' login code:</p>'
        . '<p style="font-size:28px;font-weight:bold;letter-spacing:6px">' . $code . '</p>'
        . '<p>Ye code ' . OTP_VALID_MINUTES . ' minute tak valid hai. Agar aap ne login nahi kiya to apna password foran badal lein.</p>'
        . '<p style="color:#888;font-size:12px">IP: ' . e(client_ip()) . ' · ' . date('d M Y h:i A') . '</p></div>';
    send_mail($user['email'], APP_NAME . ' login code: ' . $code, $html);
    $_SESSION['otp_sent_at'] = time();
}
