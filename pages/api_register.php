<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/UserRepository.php';
require_once __DIR__ . '/../bll/AuthThrottle.php';
require_once __DIR__ . '/../bll/PasswordPolicy.php';

requireMethod('POST');
$input = readJsonBody();

$name     = is_string($input['name'] ?? null) ? trim($input['name']) : '';
$email    = is_string($input['email'] ?? null) ? trim($input['email']) : '';
$password = is_string($input['password'] ?? null) ? $input['password'] : '';

$nameLength = mb_strlen($name);
if ($nameLength < 2 || $nameLength > 100) {
    jsonResponse(['error' => 'Name must be between 2 and 100 characters.'], 400);
}

if (strlen($email) > 191 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Please enter a valid email address.'], 400);
}

$passwordProblem = PasswordPolicy::check($password, $email, $name);
if ($passwordProblem !== null) {
    jsonResponse(['error' => $passwordProblem], 400);
}

$ip       = clientIp();
$throttle = new AuthThrottle(new AuthAttemptRepository());

$retryAfter = $throttle->registerBlockedFor($ip);
if ($retryAfter > 0) {
    header('Retry-After: ' . $retryAfter);
    jsonResponse(['error' => 'Too many accounts created from this network. Please try again later.'], 429);
}

$userRepo = new UserRepository();

if ($userRepo->findByEmail($email)) {
    jsonResponse(['error' => 'An account with this email already exists.'], 409);
}

try {
    $userId = $userRepo->createUser($name, $email, $password);
} catch (PDOException $e) {
    // 23000 = integrity violation: two sign-ups with the same email raced past findByEmail()
    if ($e->getCode() === '23000') {
        jsonResponse(['error' => 'An account with this email already exists.'], 409);
    }
    throw $e; // anything else → global handler → generic 500
}

$throttle->recordRegistration($ip);
setAuthenticatedUser($userId);

jsonResponse([
    'success'    => true,
    'user'       => $userRepo->findById($userId),
    'csrf_token' => csrfToken(),
]);