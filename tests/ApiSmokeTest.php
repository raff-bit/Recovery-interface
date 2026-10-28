<?php

/**
 * API Smoke Test — exercises the REAL running app over HTTP, like the browser does.
 * ------------------------------------------------------------
 * Requires Apache + MySQL running (XAMPP).
 * Run:   php tests/ApiSmokeTest.php
 * Other URL:  BASE_URL=http://localhost/other-folder php tests/ApiSmokeTest.php
 *
 * Creates a throwaway user, runs every main flow, then deletes
 * everything it created (user, workouts, throttle rows).
 */

require_once __DIR__ . '/../config/database.php';

$baseUrl = rtrim(getenv('BASE_URL') ?: 'http://localhost/Fit%20App', '/');

// ---------------- tiny HTTP client with a cookie jar ----------------

$cookies = [];

function http(string $method, string $path, ?array $body = null, array $headers = []): array
{
    global $baseUrl, $cookies;

    $headerLines = [];
    foreach ($headers as $k => $v) {
        $headerLines[] = "{$k}: {$v}";
    }
    if ($cookies) {
        $headerLines[] = 'Cookie: ' . implode('; ', array_map(fn($k, $v) => "{$k}={$v}", array_keys($cookies), $cookies));
    }
    if ($body !== null) {
        $headerLines[] = 'Content-Type: application/json';
    }

    $ctx = stream_context_create(['http' => [
        'method'        => $method,
        'header'        => implode("\r\n", $headerLines),
        'content'       => $body !== null ? json_encode($body) : '',
        'ignore_errors' => true, // give us the body on 4xx/5xx instead of failing
        'timeout'       => 10,
    ]]);

    $raw = @file_get_contents($baseUrl . '/' . ltrim($path, '/'), false, $ctx);
    if ($raw === false) {
        fwrite(STDERR, "\n✖ Cannot reach {$baseUrl} — is Apache running? Set BASE_URL if the folder name differs.\n");
        exit(2);
    }

    $responseHeaders = $http_response_header;
    preg_match('#HTTP/\S+ (\d{3})#', $responseHeaders[0] ?? '', $m);
    $status = (int) ($m[1] ?? 0);

    $headerMap = [];
    foreach ($responseHeaders as $line) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $line, $c)) {
            $cookies[$c[1]] = $c[2];
            if ($c[2] === 'deleted' || $c[2] === '') {
                unset($cookies[$c[1]]);
            }
        }
        if (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $headerMap[strtolower(trim($k))] = trim($v);
        }
    }

    return ['status' => $status, 'json' => json_decode($raw, true), 'headers' => $headerMap, 'lines' => $responseHeaders, 'raw' => $raw];
}

// ---------------- assertions ----------------

/** Pass/fail counters. A class (not globals) so static analysis can follow the changes. */
final class Results
{
    public static int $passed = 0;
    public static int $failed = 0;
}

function check(bool $condition, string $name, string $detail = ''): void
{
    if ($condition) {
        echo "  \033[32m✔ PASS\033[0m: {$name}\n";
        Results::$passed++;
    } else {
        echo "  \033[31m✖ FAIL\033[0m: {$name}" . ($detail !== '' ? "  → {$detail}" : '') . "\n";
        Results::$failed++;
    }
}

// ---------------- setup ----------------

$db        = Database::getConnection();
$testStart = $db->query('SELECT NOW()')->fetchColumn();
$email     = 'smoke_' . bin2hex(random_bytes(4)) . '@test.local';
$password  = 'correct-horse-battery';
$csrf      = '';

function post(string $path, array $body, bool $withCsrf = true): array
{
    global $csrf;
    return http('POST', $path, $body, $withCsrf ? ['X-CSRF-Token' => $csrf] : []);
}

try {
    // Pre-flight: if the app itself isn't reachable, every other result is meaningless
    $probe = http('GET', 'pages/api_me.php');
    if ($probe['status'] !== 200 || !isset($probe['json']['csrf_token'])) {
        fwrite(STDERR, "\n✖ {$baseUrl} is not the app (HTTP {$probe['status']}). Wrong BASE_URL?\n"
            . "  Folder 'Fit App' → BASE_URL=\"http://localhost/Fit%20App\" php tests/ApiSmokeTest.php\n");
        exit(2);
    }
    $cookies = []; // <- New: start the real testts with a brand-new session
    echo "\nSecurity — file exposure (\033[2m{$baseUrl}\033[0m)\n";

    foreach (
        [
            '.git/HEAD',
            '.git/config',
            '.env',
            '.htaccess',
            'config/database.php',
            'dal/UserRepository.php',
            'tests/ApiSmokeTest.php',
            'database/schema.sql',
            'README.md',
            'LICENSE'
        ] as $path
    ) {
        $r = http('GET', $path);
        check(in_array($r['status'], [403, 404], true), "/{$path} is not publicly reachable", "got HTTP {$r['status']}");
    }

    $page = http('GET', 'index.html');
    check($page['status'] === 200, 'index.html is served');
    check(str_contains($page['headers']['content-security-policy'] ?? '', "script-src 'self'"), 'index.html has a Content-Security-Policy');
    check(($page['headers']['x-frame-options'] ?? '') === 'DENY', 'index.html cannot be framed (clickjacking)');

    echo "\nSecurity — session & CSRF\n";

    $me = http('GET', 'pages/api_me.php');
    $csrf = $me['json']['csrf_token'] ?? '';
    check($me['status'] === 200 && $me['json']['authenticated'] === false, 'Anonymous /me returns unauthenticated');
    check(strlen($csrf) === 64, 'CSRF token is issued (64 hex chars)');
    check(str_contains($me['headers']['cache-control'] ?? '', 'no-store'), 'API responses are marked no-store');

    $sessionCookie = array_filter($me['lines'], fn($h) => stripos($h, 'Set-Cookie: RI_SESSID') === 0);
    $cookieLine = strtolower(implode(' ', $sessionCookie));
    check(str_contains($cookieLine, 'httponly') && str_contains($cookieLine, 'samesite=lax'), 'Session cookie is HttpOnly + SameSite=Lax');

    $r = post('pages/api_login.php', ['email' => $email, 'password' => $password], false);
    check($r['status'] === 403 && ($r['json']['code'] ?? '') === 'csrf_invalid', 'POST without CSRF token is rejected (403)');

    $r = http('POST', 'pages/api_login.php', ['email' => $email, 'password' => $password], ['X-CSRF-Token' => str_repeat('a', 64)]);
    check($r['status'] === 403, 'POST with a forged CSRF token is rejected (403)');

    echo "\nAuth — registration\n";

    $r = post('pages/api_register.php', ['name' => 'Smoke', 'email' => $email, 'password' => 'short']);
    check($r['status'] === 400, 'Weak password is rejected', "got HTTP {$r['status']}");

    $r = post('pages/api_register.php', ['name' => 'Smoke', 'email' => $email, 'password' => '1234567890']);
    check($r['status'] === 400, 'Common password is rejected');

    $r = post('pages/api_register.php', ['name' => 'Smoke', 'email' => $email, 'password' => $password]);
    check($r['status'] === 200 && ($r['json']['success'] ?? false), 'Strong password registers successfully', $r['raw']);
    $csrf = $r['json']['csrf_token'] ?? $csrf;

    $r = post('pages/api_register.php', ['name' => 'Smoke', 'email' => strtoupper($email), 'password' => $password]);
    check($r['status'] === 409, 'Duplicate email (different case) is rejected');

    echo "\nWorkouts — full lifecycle\n";

    $ex = http('GET', 'pages/api_exercises.php');
    $exerciseId = $ex['json']['exercises'][0]['id'] ?? null;
    check($ex['status'] === 200 && $exerciseId !== null, 'Exercise library loads');

    // Regression test for the Step 0 bug: empty RPE on the LAST set
    $r = post('pages/api_log_workout.php', ['label' => 'Smoke push', 'sets' => [
        ['exercise_id' => $exerciseId, 'reps' => 8, 'weight_kg' => 60, 'rpe' => 8],
        ['exercise_id' => $exerciseId, 'reps' => 8, 'weight_kg' => 60, 'rpe' => null],
    ]]);
    $workoutId = $r['json']['workout_id'] ?? null;
    check($r['status'] === 200 && ($r['json']['sets_logged'] ?? 0) === 2, 'Workout with empty last-set RPE is saved', $r['raw']);

    $r = post('pages/api_log_workout.php', ['sets' => [['exercise_id' => $exerciseId, 'reps' => 'abc']]]);
    check($r['status'] === 400, 'Invalid reps are rejected with 400 (not 500)');

    $r = post('pages/api_log_workout.php', ['sets' => [['exercise_id' => 999999, 'reps' => 5]]]);
    check($r['status'] >= 400 && !str_contains($r['raw'], 'SQLSTATE'), 'Unknown exercise fails without leaking SQL errors');

    // Wrong JSON types trigger a PHP TypeError internally — the user must only see a generic message
    $r = post('pages/api_preview_workout.php', ['target_date' => ['not', 'a', 'string'], 'sets' => [['exercise_id' => $exerciseId, 'reps' => 5]]]);
    check($r['status'] >= 400 && !preg_match('/\.php|Stack trace|Fatal error|Warning:/i', $r['raw']), 'Internal errors never leak file paths or stack traces', substr($r['raw'], 0, 120));

    $r = http('GET', 'pages/api_workout_history.php');
    check(($r['json']['workouts'][0]['id'] ?? null) == $workoutId, 'History lists the new workout');

    // UTC end-to-end: the date we get back must be explicit UTC ("…Z") and match the real current instant
    $returned = $r['json']['workouts'][0]['workout_date'] ?? '';
    $driftSeconds = abs(strtotime($returned) - time());
    check(str_ends_with($returned, 'Z') && $driftSeconds < 120, 'Workout date is stored and returned as UTC', "got '{$returned}', drift {$driftSeconds}s");

    $r = post('pages/api_update_workout.php', ['workout_id' => $workoutId, 'label' => 'Edited', 'sets' => [
        ['exercise_id' => $exerciseId, 'reps' => 10, 'weight_kg' => 50, 'rpe' => 7],
    ]]);
    check($r['status'] === 200, 'Workout can be updated');

    $r = http('GET', "pages/api_get_workout.php?id={$workoutId}");
    check(($r['json']['workout']['label'] ?? '') === 'Edited' && count($r['json']['workout']['sets'] ?? []) === 1, 'Update is persisted');

    $r = post('pages/api_save_profile.php', ['sex' => 'male', 'body_weight_kg' => 80, 'age' => 25, 'activity_level' => 'very_active']);
    check($r['status'] === 200, 'Profile can be saved');

    $r = http('GET', 'pages/api_muscles.php');
    $muscles = $r['json']['muscles'] ?? [];
    $fatigued = array_filter($muscles, fn($m) => $m['readiness'] < 100);
    check(count($muscles) === 17 && count($fatigued) > 0 && ($muscles[0]['personalized'] ?? false), 'Readiness reflects the logged workout (personalized)');

    $r = post('pages/api_preview_workout.php', ['sets' => [['exercise_id' => $exerciseId, 'reps' => 10, 'rpe' => 8]]]);
    check($r['status'] === 200 && count($r['json']['muscles'] ?? []) === 17, 'Planner preview works');

    $r = http('GET', 'pages/api_analytics.php');
    check($r['status'] === 200 && isset($r['json']['weekly_volume']), 'Analytics load');

    $r = post('pages/api_delete_workout.php', ['workout_id' => $workoutId]);
    check($r['status'] === 200, 'Workout can be deleted');

    echo "\nAuth — logout & brute force\n";

    $r = http('GET', 'pages/api_logout.php');
    check($r['status'] === 405, 'Logout via GET is refused (no drive-by logouts)');

    $r = post('pages/api_logout.php', []);
    check($r['status'] === 200, 'Logout via POST works');

    $r = http('GET', 'pages/api_me.php');
    $csrf = $r['json']['csrf_token'] ?? '';
    check(($r['json']['authenticated'] ?? true) === false, 'Session is gone after logout');

    $r = http('GET', 'pages/api_workout_history.php');
    check(($r['json']['workouts'] ?? null) === [], 'Logged-out user sees no workouts');

    $r = post('pages/api_login.php', ['email' => 'nobody_' . bin2hex(random_bytes(4)) . '@test.local', 'password' => 'whatever-123']);
    $r2 = post('pages/api_login.php', ['email' => $email, 'password' => 'wrong-password-1']);
    check(
        $r['status'] === 401 && $r2['status'] === 401 && $r['json']['error'] === $r2['json']['error'],
        'Unknown email and wrong password give identical answers'
    );

    for ($i = 0; $i < 4; $i++) {
        post('pages/api_login.php', ['email' => $email, 'password' => "wrong-password-{$i}x"]);
    }
    $r = post('pages/api_login.php', ['email' => $email, 'password' => $password]);
    check($r['status'] === 429 && isset($r['headers']['retry-after']), 'After 5 failures the account is throttled (429) — even with the right password');
} finally {
    // ---------------- teardown: leave the database exactly as we found it ----------------
    $db->prepare('DELETE FROM users WHERE email = ?')->execute([strtolower($email)]); // cascades to workouts/profile
    $db->prepare("DELETE FROM auth_attempts WHERE created_at >= ? AND ip IN ('127.0.0.1', '::1')")->execute([$testStart]);
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "Results: " . Results::$passed . " passed, " . Results::$failed . " failed.\n";
echo str_repeat('=', 50) . "\n";

exit(Results::$failed > 0 ? 1 : 0);