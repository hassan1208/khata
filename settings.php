<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/mailer.php';

$user = current_user();
$tab = $_GET['tab'] ?? 'smtp';
$smtpLog = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post('action')) {
        case 'smtp':
            $enc = in_array(post('smtp_encryption'), ['tls', 'ssl', 'none'], true) ? post('smtp_encryption') : 'tls';
            $from = post('smtp_from_email');
            if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
                flash('From email sahi nahi.', 'danger');
                redirect('settings.php?tab=smtp');
            }
            set_setting('smtp_host', post('smtp_host'));
            set_setting('smtp_port', (int)post('smtp_port', 587));
            set_setting('smtp_encryption', $enc);
            set_setting('smtp_user', post('smtp_user'));
            set_setting('smtp_from_email', $from);
            set_setting('smtp_from_name', post('smtp_from_name') ?: APP_NAME);
            if (($_POST['smtp_pass'] ?? '') !== '') {
                set_setting('smtp_pass', encrypt_secret((string)$_POST['smtp_pass']));
            }
            flash('SMTP settings save ho gayin. Ab "Test email" bhej kar check karein.');
            redirect('settings.php?tab=smtp');

        case 'smtp_test':
            $mailer = SmtpMailer::fromSettings();
            try {
                $mailer->send($user['email'], APP_NAME . ' test email', '<p>Mubarak ho! SMTP theek kaam kar raha hai.</p><p>' . date('d M Y h:i A') . '</p>');
                flash('Test email ' . $user['email'] . ' par bhej di gayi.');
                redirect('settings.php?tab=smtp');
            } catch (RuntimeException $ex) {
                flash('Test fail: ' . $ex->getMessage(), 'danger');
                $smtpLog = $mailer->getLog();
                $tab = 'smtp';
            }
            break;

        case 'security':
            $otp = isset($_POST['otp_enabled']) ? '1' : '0';
            set_setting('otp_enabled', $otp);
            $email = post('email');
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                db_exec('UPDATE users SET email = ? WHERE id = ?', [$email, $user['id']]);
            }
            flash('Security settings save ho gayin.' . ($otp === '1' && !smtp_configured() ? ' (OTP tab chalega jab SMTP set hoga.)' : ''));
            redirect('settings.php?tab=security');

        case 'password':
            $row = db_one('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
            $new = (string)($_POST['new_password'] ?? '');
            if (!password_verify((string)($_POST['current_password'] ?? ''), $row['password_hash'])) {
                flash('Purana password ghalat hai.', 'danger');
            } elseif (strlen($new) < 8 || $new !== ($_POST['new_password2'] ?? '')) {
                flash('Naya password kam az kam 8 characters ka ho aur dono match karein.', 'danger');
            } else {
                db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
                session_regenerate_id(true);
                flash('Password badal diya gaya.');
            }
            redirect('settings.php?tab=security');

        case 'cash':
            set_setting('opening_cash', amount_in('opening_cash'));
            if ($d = valid_date(post('opening_cash_date'))) set_setting('opening_cash_date', $d);
            flash('Opening cash update ho gaya.');
            redirect('settings.php?tab=cash');

        case 'cat_add':
            if (post('name') !== '') {
                db_exec('INSERT IGNORE INTO expense_categories (name) VALUES (?)', [post('name')]);
                flash('Category add ho gayi.');
            }
            redirect('settings.php?tab=categories');

        case 'cat_rename':
            if (post('name') !== '') {
                try {
                    db_exec('UPDATE expense_categories SET name = ? WHERE id = ?', [post('name'), (int)post('id')]);
                } catch (mysqli_sql_exception $ex) {
                    flash('Is naam ki category pehle se hai.', 'danger');
                }
            }
            redirect('settings.php?tab=categories');

        case 'cat_delete':
            db_exec('DELETE FROM expense_categories WHERE id = ?', [(int)post('id')]);
            flash('Category delete ho gayi (us k kharche "Other" mein chale gaye).', 'warning');
            redirect('settings.php?tab=categories');
    }
}

$cats = db_all('SELECT c.*, COUNT(e.id) n FROM expense_categories c LEFT JOIN expenses e ON e.category_id = c.id GROUP BY c.id ORDER BY c.name');
$hasPass = setting('smtp_pass') !== '';

$pageTitle = 'Settings';
require __DIR__ . '/includes/header.php';
$tabs = ['smtp' => ['bi-envelope', 'Email (SMTP)'], 'security' => ['bi-shield-lock', 'Security / OTP'], 'cash' => ['bi-wallet2', 'Opening Cash'], 'categories' => ['bi-tags', 'Expense Categories'], 'backup' => ['bi-download', 'Backup']];
?>
<div class="page-head"><h1><i class="bi bi-gear"></i> Settings</h1></div>
<ul class="nav nav-tabs mb-3">
  <?php foreach ($tabs as $k => [$icon, $label]): ?>
    <li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?>"><i class="bi <?= $icon ?>"></i> <?= $label ?></a></li>
  <?php endforeach; ?>
</ul>

<?php if ($tab === 'smtp'): ?>
<div class="row g-3">
  <div class="col-lg-7"><form method="post" class="card"><div class="card-body"><?= csrf_field() ?><input type="hidden" name="action" value="smtp">
    <div class="row g-3">
      <div class="col-md-8"><label class="form-label">SMTP Host</label><input name="smtp_host" class="form-control" value="<?= e(setting('smtp_host')) ?>" placeholder="smtp.gmail.com"></div>
      <div class="col-md-4"><label class="form-label">Port</label><input type="number" name="smtp_port" class="form-control" value="<?= e(setting('smtp_port', 587)) ?>"></div>
      <div class="col-md-6"><label class="form-label">Encryption</label><select name="smtp_encryption" class="form-select">
        <?php foreach (['tls' => 'TLS / STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None (25)'] as $k => $lbl): ?><option value="<?= $k ?>" <?= setting('smtp_encryption') === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?></select></div>
      <div class="col-md-6"><label class="form-label">Username</label><input name="smtp_user" class="form-control" value="<?= e(setting('smtp_user')) ?>" autocomplete="off"></div>
      <div class="col-md-6"><label class="form-label">Password</label><input type="password" name="smtp_pass" class="form-control" autocomplete="new-password" placeholder="<?= $hasPass ? '•••••••• (khali chhorein to wohi rahega)' : '' ?>"></div>
      <div class="col-md-6"><label class="form-label">From Email</label><input type="email" name="smtp_from_email" class="form-control" value="<?= e(setting('smtp_from_email')) ?>"></div>
      <div class="col-md-6"><label class="form-label">From Name</label><input name="smtp_from_name" class="form-control" value="<?= e(setting('smtp_from_name', APP_NAME)) ?>"></div>
    </div>
    <button class="btn btn-primary mt-3">Save</button>
  </div></form>
  <form method="post" class="mt-3"><?= csrf_field() ?><input type="hidden" name="action" value="smtp_test">
    <button class="btn btn-outline-success" <?= smtp_configured() ? '' : 'disabled' ?>><i class="bi bi-send"></i> Test email bhejein (<?= e($user['email']) ?>)</button></form>
  <?php if ($smtpLog): ?><pre class="bg-dark text-light p-2 mt-3 small rounded" style="max-height:300px"><?= e($smtpLog) ?></pre><?php endif; ?>
  </div>
  <div class="col-lg-5"><div class="card"><div class="card-body small">
    <h6>Gmail k liye</h6>
    <ol class="mb-2">
      <li>Google account mein 2-Step Verification on karein.</li>
      <li><em>myaccount.google.com → Security → App passwords</em> se 16 character ka App Password banayein.</li>
      <li>Host <code>smtp.gmail.com</code>, Port <code>587</code>, TLS, Username = aap ka Gmail, Password = App Password.</li>
    </ol>
    <h6>Hosting (cPanel) email</h6>
    <p class="mb-0">Host <code>mail.yourdomain.com</code>, Port <code>465</code> SSL, username poora email address.</p>
    <hr><p class="mb-0 text-muted">Password database mein encrypted save hota hai (config.php ki APP_KEY se).</p>
  </div></div></div>
</div>

<?php elseif ($tab === 'security'): ?>
<div class="row g-3">
  <div class="col-lg-6"><form method="post" class="card"><div class="card-body"><?= csrf_field() ?><input type="hidden" name="action" value="security">
    <h6>Login OTP</h6>
    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="otp_enabled" id="otp" <?= setting('otp_enabled', '1') === '1' ? 'checked' : '' ?>>
      <label class="form-check-label" for="otp">Login k baad email par OTP maango</label></div>
    <?php if (!smtp_configured()): ?><div class="alert alert-warning small py-2">SMTP set nahi hai, is liye abhi OTP nahi jayega. Pehle Email (SMTP) tab set karein.</div><?php endif; ?>
    <label class="form-label">OTP is email par aayega</label>
    <input type="email" name="email" class="form-control mb-3" value="<?= e($user['email']) ?>" required>
    <button class="btn btn-primary">Save</button>
  </div></form></div>
  <div class="col-lg-6"><form method="post" class="card"><div class="card-body"><?= csrf_field() ?><input type="hidden" name="action" value="password">
    <h6>Password badlein</h6>
    <input type="password" name="current_password" class="form-control mb-2" placeholder="Purana password" required>
    <input type="password" name="new_password" class="form-control mb-2" placeholder="Naya password (8+ characters)" minlength="8" required>
    <input type="password" name="new_password2" class="form-control mb-3" placeholder="Naya password dobara" minlength="8" required>
    <button class="btn btn-primary">Password badlein</button>
  </div></form></div>
</div>

<?php elseif ($tab === 'cash'): ?>
<form method="post" class="card" style="max-width:520px"><div class="card-body"><?= csrf_field() ?><input type="hidden" name="action" value="cash">
  <p class="small text-muted">Opening cash wo raqam hai jo system use karne se pehle aap k paas thi. Is k baad saari income, kharche, investment aur udhar khud jama/minus hote hain.</p>
  <div class="row g-2">
    <div class="col-7"><label class="form-label">Opening cash</label><input type="number" step="0.01" name="opening_cash" class="form-control" value="<?= e(setting('opening_cash', 0)) ?>"></div>
    <div class="col-5"><label class="form-label">Tareekh</label><input type="date" name="opening_cash_date" class="form-control" value="<?= e(setting('opening_cash_date')) ?>"></div>
  </div>
  <button class="btn btn-primary mt-3">Save</button>
</div></form>

<?php elseif ($tab === 'backup'): ?>
<div class="card" style="max-width:640px"><div class="card-body">
  <p>Apna data waqtan fawaqtan download kar k mehfooz jagah (Google Drive, USB) rakhein.</p>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <form method="post" action="backup.php"><?= csrf_field() ?><input type="hidden" name="what" value="db"><button class="btn btn-primary"><i class="bi bi-database-down"></i> Database backup (.sql)</button></form>
    <form method="post" action="backup.php"><?= csrf_field() ?><input type="hidden" name="what" value="files"><button class="btn btn-outline-primary"><i class="bi bi-file-zip"></i> Documents (.zip)</button></form>
  </div>
  <p class="small text-muted mb-0">Wapas lana ho to: phpMyAdmin → database chunein → Import → .sql file. Documents ki ZIP ko extract kar k <code>uploads/tenancy/</code> mein rakh dein.</p>
</div></div>

<?php else: ?>
<div class="card" style="max-width:640px" id="categories"><div class="card-body">
  <form method="post" class="d-flex gap-2 mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="cat_add">
    <input name="name" class="form-control" placeholder="Nayi category" required maxlength="80"><button class="btn btn-success text-nowrap"><i class="bi bi-plus"></i> Add</button></form>
  <table class="table table-sm mb-0">
  <?php foreach ($cats as $c): ?>
    <tr><td><form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="cat_rename"><input type="hidden" name="id" value="<?= $c['id'] ?>">
        <input name="name" class="form-control form-control-sm" value="<?= e($c['name']) ?>" maxlength="80"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-check"></i></button></form></td>
      <td class="small text-muted text-nowrap"><?= (int)$c['n'] ?> entries</td>
      <td class="text-end"><form method="post" data-confirm="Category delete karein?"><?= csrf_field() ?><input type="hidden" name="action" value="cat_delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form></td></tr>
  <?php endforeach; ?>
  </table>
</div></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
