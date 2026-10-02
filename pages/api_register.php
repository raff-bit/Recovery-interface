<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/UserRepository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST is supported.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$name     = trim($input['name'] ?? '');
$email    = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (strlen($name) < 2) {
    http_response_code(400);
    echo json_encode(['error' => 'Name must be at least 2 characters long.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 6 characters long.']);
    exit;
}

$userRepo = new UserRepository();

if ($userRepo->findByEmail($email)) {
    http_response_code(409);
    echo json_encode(['error' => 'An account with this email already exists.']);
    exit;
}

try {
    $userId = $userRepo->createUser($name, $email, $password);
    setAuthenticatedUser($userId);

    echo json_encode([
        'success' => true,
        'user'    => $userRepo->findById($userId),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create account.', 'detail' => $e->getMessage()]);
}