<?php
/*
 * Har page sab se pehle ye file include karta hai:
 * config, session, database, helpers, CSRF aur login check.
 */
require_once __DIR__ . '/../config.php';

date_default_timezone_set(APP_TIMEZONE);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('khata_sess');
    session_start();
}

/* ---------------- Database ---------------- */

function db(): mysqli
{
    static $db = null;
    if ($db === null) {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $db->set_charset('utf8mb4');
        $db->query("SET time_zone = '" . date('P') . "'");
    }
    return $db;
}

function db_run(string $sql, array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($params) {
        $params = array_values($params);
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    }
    $stmt->execute();
    return $stmt;
}

function db_all(string $sql, array $params = []): array
{
    $res = db_run($sql, $params)->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function db_one(string $sql, array $params = []): ?array
{
    $res = db_run($sql, $params)->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    return $row ?: null;
}

function db_val(string $sql, array $params = [])
{
    $row = db_one($sql, $params);
    return $row ? reset($row) : null;
}

function db_exec(string $sql, array $params = []): int
{
    $stmt = db_run($sql, $params);
    return $stmt->insert_id ?: $stmt->affected_rows;
}

/* ---------------- Settings ---------------- */

function setting(string $key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all('SELECT skey, svalue FROM settings') as $r) {
            $cache[$r['skey']] = $r['svalue'];
        }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function set_setting(string $key, $value): void
{
    db_exec('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$key, (string)$value]);
}

function encrypt_secret(string $plain): string
{
    if ($plain === '') return '';
    $iv = random_bytes(16);
    $enc = openssl_encrypt($plain, 'AES-256-CBC', hash('sha256', APP_KEY, true), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $enc);
}

function decrypt_secret(string $stored): string
{
    if ($stored === '') return '';
    $raw = base64_decode($stored, true);
    if ($raw === false || strlen($raw) < 17) return '';
    $dec = openssl_decrypt(substr($raw, 16), 'AES-256-CBC', hash('sha256', APP_KEY, true), OPENSSL_RAW_DATA, substr($raw, 0, 16));
    return $dec === false ? '' : $dec;
}

/* ---------------- Output helpers ---------------- */

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function money($amount, bool $withCurrency = true): string
{
    $amount = (float)$amount;
    $dec = (abs($amount - round($amount)) < 0.005) ? 0 : 2;
    $s = number_format($amount, $dec);
    return $withCurrency ? CURRENCY . ' ' . $s : $s;
}

function fdate($d): string
{
    return $d ? date('d M Y', strtotime($d)) : '-';
}

function fmonth($d): string
{
    return $d ? date('M Y', strtotime($d)) : '-';
}

/** 'YYYY-MM' ya 'YYYY-MM-DD' ko us maheenay ki 1 tareekh bana deta hai */
function month_start(?string $v): ?string
{
    if (!$v) return null;
    $t = strtotime(strlen($v) === 7 ? $v . '-01' : $v);
    return $t ? date('Y-m-01', $t) : null;
}

function valid_date(?string $v): ?string
{
    if (!$v) return null;
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}

function post(string $key, $default = '')
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function amount_in(string $key): float
{
    return round((float)str_replace(',', '', (string)post($key, '0')), 2);
}

function flash(string $msg, string $type = 'success'): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/* ---------------- CSRF ---------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $t = $_POST['csrf'] ?? '';
        if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
            http_response_code(419);
            exit('Session expire ho gaya. Page refresh kar k dobara koshish karein.');
        }
    }
}

/* ---------------- Auth ---------------- */

function is_installed(): bool
{
    try {
        return (int)db_val('SELECT COUNT(*) FROM users') > 0;
    } catch (mysqli_sql_exception $ex) {
        return false;
    }
}

function current_user(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = empty($_SESSION['user_id']) ? null : db_one('SELECT id, username, email FROM users WHERE id = ?', [$_SESSION['user_id']]);
    }
    return $u;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('login.php');
    }
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    unset($_SESSION['pending_user'], $_SESSION['otp_sent_at']);
    $_SESSION['user_id'] = $userId;
}

function smtp_configured(): bool
{
    return setting('smtp_host') !== '' && setting('smtp_from_email') !== '';
}

/* ---------------- Page guard ---------------- */

if (!defined('PUBLIC_PAGE')) {
    if (!is_installed()) {
        redirect('setup.php');
    }
    require_login();
}
csrf_check();
