<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Colombo');
if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/']);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' https:; style-src 'self'; script-src 'self'; font-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('Cache-Control: no-store');
}
set_exception_handler(function (Throwable $error): void {
    error_log((string)$error);
    if (PHP_SAPI === 'cli') { fwrite(STDERR, "Operation failed. Check the error log and database configuration.\n"); exit(1); }
    http_response_code(500);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>GlobeTrek Adventures</title><h1>We could not complete your request</h1><p>Please try again shortly. If this is a new installation, follow the database setup in the README.</p></html>';
});

function db(): PDO {
    static $connection;
    if (!$connection) {
        $file = dirname(__DIR__) . '/config.local.php';
        $config = is_file($file) ? require $file : [];
        $connection = new PDO(getenv('DB_DSN') ?: ($config['dsn'] ?? 'mysql:host=127.0.0.1;dbname=globetrek;charset=utf8mb4'), getenv('DB_USER') ?: ($config['username'] ?? 'globetrek'), getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ($config['password'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    }
    return $connection;
}
function sql(string $query, array $params = []): PDOStatement { $s = db()->prepare($query); $s->execute($params); return $s; }
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money(int|string $cents): string { return 'LKR ' . number_format((int)$cents / 100, 0); }
function go(string $url): never { header('Location: ' . $url, true, 303); exit; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function verify_csrf(): void { if (!is_string($_POST['csrf'] ?? null) || !hash_equals(csrf(), $_POST['csrf'])) { http_response_code(419); exit('Your form has expired. Go back, reload the page and try again.'); } }
function flash(string $message): void { $_SESSION['flash'] = $message; }
function user(): ?array {
    static $loaded = false, $current;
    if (!$loaded) { $loaded = true; $current = isset($_SESSION['user_id']) ? sql('SELECT id,name,email,role,active FROM users WHERE id=? AND active=1', [$_SESSION['user_id']])->fetch() ?: null : null; }
    return $current;
}
function require_role(array $roles): array {
    $u = user();
    if (!$u) go('/login.php');
    if (!in_array($u['role'], $roles, true)) { http_response_code(403); exit('You do not have access to this page.'); }
    return $u;
}
function text_input(string $key, int $min = 1, int $max = 200): string {
    $value = trim(is_string($_POST[$key] ?? null) ? $_POST[$key] : '');
    if (strlen($value) < $min || strlen($value) > $max) throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $key)) . " must be between $min and $max characters.");
    return $value;
}
function integer_input(string $key, int $min, int $max): int {
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);
    if ($value === false || $value < $min || $value > $max) throw new InvalidArgumentException('Please enter a valid ' . str_replace('_', ' ', $key) . '.');
    return $value;
}
function travel_date(string $value): string {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || $value < date('Y-m-d') || $value > date('Y-m-d', strtotime('+2 years'))) throw new InvalidArgumentException('Choose a valid travel date within the next two years.');
    return $value;
}
function image_url(string $value): string {
    if (!filter_var($value, FILTER_VALIDATE_URL) || parse_url($value, PHP_URL_SCHEME) !== 'https') throw new InvalidArgumentException('The image must be a valid HTTPS URL.');
    return $value;
}
function field(string $label, string $name, string $type = 'text', mixed $value = '', string $extra = ''): void {
    echo '<div class="mb-3"><label class="form-label" for="' . e($name) . '">' . e($label) . '</label><input class="form-control" id="' . e($name) . '" name="' . e($name) . '" type="' . e($type) . '" value="' . e($value) . '" ' . $extra . '></div>';
}
function area(string $label, string $name, mixed $value = '', int $max = 5000): void {
    echo '<div class="mb-3"><label class="form-label" for="' . e($name) . '">' . e($label) . '</label><textarea class="form-control" id="' . e($name) . '" name="' . e($name) . '" rows="4" required maxlength="' . $max . '">' . e($value) . '</textarea></div>';
}
function error_box(?string $error): void { if ($error) echo '<div class="alert alert-danger" role="alert">' . e($error) . '</div>'; }
function status_badge(string $status): string { return '<span class="status status-' . e($status) . '">' . e(ucfirst($status)) . '</span>'; }
