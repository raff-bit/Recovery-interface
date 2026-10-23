<?php
/**
 * bootstrap.php — runs FIRST on every API request.
 * ------------------------------------------------------------
 * One place for everything that must be true for every endpoint:
 *   1. Config loaded
 *   2. Errors never reach the user as raw PHP output
 *   3. Security headers on every response
 *   4. Hardened session cookie
 *   5. CSRF protection on every state-changing request
 *
 * Endpoints get all of this just by requiring config/auth.php
 * (which requires this file) — no endpoint can "forget" it.
 */

require_once __DIR__ . '/env.php';
loadEnv(__DIR__ . '/../.env');

// ------------------------------------------------------------
// 1. Error handling — log everything, show the user nothing internal
// ------------------------------------------------------------
$appDebug = envBool('APP_DEBUG') && env('APP_ENV', 'production') !== 'production';

error_reporting(E_ALL);
ini_set('display_errors', $appDebug ? '1' : '0'); // overrides XAMPP's display_errors=On
ini_set('log_errors', '1');

// Warnings/notices become exceptions: code that reads an undefined key
// fails loudly instead of silently continuing with null.
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false; // silenced with @ — respect it
    }
    if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
        error_log("Deprecated: {$message} in {$file}:{$line}");
        return true;  // log deprecations, don't crash on them
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Last line of defence: any uncaught exception → generic JSON 500
set_exception_handler(function (Throwable $e) use ($appDebug): void {
    error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode($appDebug
        ? ['error' => 'Internal error', 'debug' => $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine()]
        : ['error' => 'Internal error. Please try again later.']);
});

// ------------------------------------------------------------
// 2. Security headers (API responses)
// ------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');               // browser must not guess content types
header('X-Frame-Options: DENY');                         // no clickjacking via iframes
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'"); // JSON never needs to load anything
header('Cache-Control: no-store');                       // personal data must not sit in shared caches

// ------------------------------------------------------------
// 3. Session hardening — must run BEFORE session_start()
// ------------------------------------------------------------
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');   // reject session IDs the server didn't create (anti-fixation)
    ini_set('session.use_only_cookies', '1');  // never accept session IDs from the URL
    session_name('RI_SESSID');                 // don't advertise "PHPSESSID"
    session_set_cookie_params([
        'lifetime' => 0,          // dies when the browser closes
        'path'     => '/',
        'secure'   => $isHttps,   // HTTPS-only once deployed; localhost stays usable
        'httponly' => true,       // JavaScript can't read it → XSS can't steal the session
        'samesite' => 'Lax',      // other sites can't make the browser send it on POSTs
    ]);
    session_start();
}

// ------------------------------------------------------------
// 4. CSRF protection (synchronizer token pattern)
// ------------------------------------------------------------
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
    $sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    // hash_equals = constant-time comparison (no timing leak of the token)
    if (!is_string($sentToken) || $sentToken === '' || !hash_equals(csrfToken(), $sentToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Security token missing or expired. Please retry.', 'code' => 'csrf_invalid']);
        exit;
    }
}

// ------------------------------------------------------------
// 5. Small helpers every endpoint can use
// ------------------------------------------------------------

/** Sends a JSON response and stops. */
function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/** Rejects anything but the expected HTTP method. */
function requireMethod(string $expected): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $expected) {
        header('Allow: ' . $expected);
        jsonResponse(['error' => "Only {$expected} is supported."], 405);
    }
}

/** Decodes the JSON body; 400 if it isn't a JSON object. Caps size at 64 KB. */
function readJsonBody(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 65536 + 1);
    if ($raw === false || strlen($raw) > 65536) {
        jsonResponse(['error' => 'Request body too large.'], 413);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        jsonResponse(['error' => 'Request body must be valid JSON.'], 400);
    }
    return $data;
}

/** Client IP — REMOTE_ADDR only; X-Forwarded-For is trivially spoofable. */
function clientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}