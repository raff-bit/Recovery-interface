<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/UserRepository.php';
require_once __DIR__ . '/../bll/AuthThrottle.php';

requireMethod('POST');
$input = readJsonBody();

$email    = is_string($input['email'] ?? null) ? trim($input['email']) : '';
$password = is_string($input['password'] ?? null) ? $input['password'] : '';

if ($email === '' || $password === '' || strlen($email) > 191 || strlen($password) > 1024) {
    jsonResponse(['error' => 'Email and password are required.'], 400);
}

$ip       = clientIp();
$throttle = new AuthThrottle(new AuthAttemptRepository());

// 1. Check the throttle BEFORE verifying — a blocked attacker gets no answer at all
$retryAfter = $throttle->loginBlockedFor($ip, $email);
if ($retryAfter > 0) {
    header('Retry-After: ' . $retryAfter);
    jsonResponse(['error' => 'Too many failed attempts. Please wait a few minutes and try again.'], 429);
}

$userRepo = new UserRepository();
$user = $userRepo->authenticate($email, $password);

if (!$user) {
    $throttle->recordLoginFailure($ip, $email);
    // Same message for "no account" and "wrong password" — don't reveal which emails exist
    jsonResponse(['error' => 'Invalid email or password.'], 401);
}

$throttle->recordLoginSuccess($email);
setAuthenticatedUser((int) $user['id']);

jsonResponse([
    'success'    => true,
    'user'       => $user,
    'csrf_token' => csrfToken(),
]);