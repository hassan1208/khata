<?php
define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/mailer.php';
require __DIR__ . '/includes/otp_send.php';

if (current_user()) redirect('index.php');
if (empty($_SESSION['pending_user'])) redirect('login.php');

$user = db_one('SELECT * FROM users WHERE id = ?', [$_SESSION['pending_user']]);
if (!$user) { unset($_SESSION['pending_user']); redirect('login.php'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend'])) {
        $wait = OTP_RESEND_SECONDS - (time() - (int)($_SESSION['otp_sent_at'] ?? 0));
        if ($wait > 0) {
            $error = "Naya code $wait second baad mangwa sakte hain.";
        } else {
            try {
                send_login_otp($user);
                flash('Naya code email kar diya gaya hai.');
                redirect('otp.php');
            } catch (RuntimeException $ex) {
                $error = 'Email nahi ja saki: ' . $ex->getMessage();
            }
        }
    } elseif (isset($_POST['cancel'])) {
        unset($_SESSION['pending_user']);
        redirect('login.php');
    } else {
        $code = preg_replace('/\D/', '', (string)post('code'));
        $otp = db_one('SELECT * FROM login_otps WHERE user_id = ? AND used = 0 ORDER BY id DESC LIMIT 1', [$user['id']]);
        if (!$otp || strtotime($otp['expires_at']) < time()) {
            $error = 'Code expire ho gaya. "Naya code bhejein" dabayein.';
        } elseif ($otp['attempts'] >= OTP_MAX_ATTEMPTS) {
            db_exec('UPDATE login_otps SET used = 1 WHERE id = ?', [$otp['id']]);
            $error = 'Bohat ghalat koshishein. Naya code mangwayein.';
        } elseif (password_verify($code, $otp['code_hash'])) {
            db_exec('UPDATE login_otps SET used = 1 WHERE id = ?', [$otp['id']]);
            login_user((int)$user['id']);
            redirect('index.php');
        } else {
            db_exec('UPDATE login_otps SET attempts = attempts + 1 WHERE id = ?', [$otp['id']]);
            $left = OTP_MAX_ATTEMPTS - $otp['attempts'] - 1;
            $error = "Code ghalat hai. $left koshishein baqi.";
        }
    }
}

$masked = preg_replace('/(?<=^.{2}).*(?=@)/', '****', $user['email']);
$bare = true; $pageTitle = 'OTP';
require __DIR__ . '/includes/header.php';
?>
<div class="card auth-card p-4">
  <h4 class="mb-1 text-center"><i class="bi bi-shield-lock text-success"></i> Verification</h4>
  <p class="text-muted small text-center">6-digit code <strong><?= e($masked) ?></strong> par bheja gaya hai.</p>
  <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <input name="code" class="form-control otp-input mb-3" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" required autofocus>
    <button class="btn btn-success w-100">Verify</button>
  </form>
  <div class="d-flex justify-content-between mt-3">
    <form method="post"><?= csrf_field() ?><button name="resend" value="1" class="btn btn-link btn-sm p-0">Naya code bhejein</button></form>
    <form method="post"><?= csrf_field() ?><button name="cancel" value="1" class="btn btn-link btn-sm p-0 text-secondary">Wapas</button></form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
